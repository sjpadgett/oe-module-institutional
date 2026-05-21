/**
 * public/sw.js
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

/**
 * sw.js — OEI Institutional Module Service Worker
 *
 * Strategy
 * ────────
 * Shell pages (ed_board, tasks, diversion…)  → Network-first, cache fallback
 * Snapshot API                               → Network-first, cache fallback; refresh every 5 min
 * Static assets (Bootstrap CDN)              → Cache-first
 * POST writes (sync, save, etc.)             → Network only; fail clearly
 *
 * The worker maintains two caches:
 *   oei-shell-v1    — module HTML pages
 *   oei-snapshot-v1 — latest snapshot JSON
 *
 * Offline event broadcasting
 * ──────────────────────────
 * When the snapshot fetch fails, the worker posts { type: 'oei:offline' }
 * to all controlled clients.  When the next fetch succeeds after an offline
 * period, it posts { type: 'oei:online' }.
 */

'use strict';

const SHELL_CACHE = 'oei-shell-v2';
const SNAPSHOT_CACHE = 'oei-snapshot-v1';
const SNAPSHOT_TTL = 5 * 60 * 1000; // 5 minutes in ms

// Pages to pre-cache on install (relative to sw.js location)
const SHELL_URLS = [
    './',
    './downtime.php',
    './ed_board.php',
    './tasks.php',
    './alerts.php',
    './diversion.php',
    './downtime_snapshot.php',
    // ── HBC pages (v0.24.0) ───────────────────────────────────────
    './hbc/board.php',
    './hbc/visit.php',
    './hbc/schedule.php',
    './hbc/vitals.php',
    './hbc/profile.php',
];

// ── Lifecycle ────────────────────────────────────────────────────────────────

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL_URLS.filter(u => u !== './'))).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    const keep = [SHELL_CACHE, SNAPSHOT_CACHE];
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((k) => !keep.includes(k)).map((k) => caches.delete(k))
        )).then(() => self.clients.claim())
    );
});

// ── Fetch interception ───────────────────────────────────────────────────────

self.addEventListener('fetch', (event) => {
    const {request} = event;
    const url = new URL(request.url);

    // Never intercept non-GET or cross-origin (e.g. Bootstrap CDN)
    if (request.method !== 'GET') return;

    // Snapshot API — network-first, cache fallback, broadcast connectivity state
    if (url.pathname.endsWith('downtime_snapshot.php')) {
        event.respondWith(networkFirstSnapshot(request));
        return;
    }

    // Static CDN assets — cache-first
    if (!url.hostname.includes(self.location.hostname)) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // Module shell pages — network-first, stale cache fallback
    if (url.pathname.includes('/oe-module-institutional/public/')) {
        event.respondWith(networkFirstShell(request));
        return;
    }
});

// ── Background sync ───────────────────────────────────────────────────────────

self.addEventListener('sync', (event) => {
    if (event.tag === 'oei-sync-queue') {
        event.waitUntil(flushIndexedDbQueue());
    }
    if (event.tag === 'oei-hbc-sync') {
        event.waitUntil(flushHbcVisitQueue());
    }
});

// ── Message channel ────────────────────────────────────────────────────────

self.addEventListener('message', (event) => {
    if (event.data?.type === 'oei:request-snapshot') {
        const facilityId = event.data.facility_id ?? 1;
        refreshSnapshot(facilityId);
    }
});

// ── Strategy implementations ─────────────────────────────────────────────────

let _wasOffline = false;

async function networkFirstSnapshot(request) {
    try {
        const response = await fetch(request.clone(), {cache: 'no-store'});
        if (response.ok) {
            const cache = await caches.open(SNAPSHOT_CACHE);
            cache.put(request, response.clone());
            if (_wasOffline) {
                _wasOffline = false;
                broadcastToClients({type: 'oei:online', ts: Date.now()});
            }
        }
        return response;
    } catch (_) {
        // Network failed — serve stale cache and broadcast offline
        const cached = await caches.match(request);
        if (!_wasOffline) {
            _wasOffline = true;
            broadcastToClients({type: 'oei:offline', ts: Date.now()});
        }
        if (cached) return cached;
        return new Response(
            JSON.stringify({error: 'offline', generated: null}),
            {status: 503, headers: {'Content-Type': 'application/json'}}
        );
    }
}

async function networkFirstShell(request) {
    try {
        const response = await fetch(request.clone());
        if (response.ok) {
            const cache = await caches.open(SHELL_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (_) {
        const cached = await caches.match(request);
        if (cached) return cached;
        // Serve the offline viewer as last-resort fallback for any module page
        const fallback = await caches.match('./downtime.php');
        return fallback ?? new Response('<h1>Offline</h1>', {
            status: 503,
            headers: {'Content-Type': 'text/html'},
        });
    }
}

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    try {
        const response = await fetch(request.clone());
        if (response.ok) {
            const cache = await caches.open(SHELL_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (_) {
        return new Response('', {status: 503});
    }
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function broadcastToClients(msg) {
    self.clients.matchAll({type: 'window', includeUncontrolled: true}).then((clients) => clients.forEach((c) => c.postMessage(msg)));
}

async function refreshSnapshot(facilityId) {
    const url = `./downtime_snapshot.php?facility_id=${facilityId}`;
    try {
        const response = await fetch(url, {cache: 'no-store'});
        if (response.ok) {
            const cache = await caches.open(SNAPSHOT_CACHE);
            cache.put(url, response.clone());
        }
    } catch (_) {
        // silent — offline state is managed by networkFirstSnapshot
    }
}

/**
 * Flush the IndexedDB pending queue to the server.
 * The browser page writes entries to IDB; this background sync sends them.
 */
async function flushIndexedDbQueue() {
    let db;
    try {
        db = await openIdb();
        const entries = await idbGetAll(db, 'pendingQueue');
        if (entries.length === 0) return;

        // Group by facility
        const byFacility = {};
        for (const e of entries) {
            const fid = e.facility_id ?? 1;
            (byFacility[fid] = byFacility[fid] ?? []).push(e);
        }

        for (const [facilityId, batch] of Object.entries(byFacility)) {
            // Read CSRF token from IDB (stored there by the page on login)
            const csrfToken = await idbGet(db, 'meta', 'csrf_token') ?? '';

            const resp = await fetch(
                `./downtime_sync.php?facility_id=${facilityId}`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: JSON.stringify({facility_id: parseInt(facilityId), entries: batch}),
                }
            );

            if (resp.ok) {
                // Remove synced entries from IDB
                const ids = batch.map((e) => e.idb_id).filter(Boolean);
                for (const id of ids) {
                    await idbDelete(db, 'pendingQueue', id);
                }
                broadcastToClients({type: 'oei:sync-complete', synced: ids.length});
            }
        }
    } catch (err) {
        console.warn('[OEI-SW] flushIndexedDbQueue error:', err);
    } finally {
        if (db) db.close();
    }
}

// ── IndexedDB helpers ─────────────────────────────────────────────────────────

function openIdb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('oei-downtime', 2);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('pendingQueue')) {
                db.createObjectStore('pendingQueue', {keyPath: 'idb_id', autoIncrement: true});
            }
            if (!db.objectStoreNames.contains('meta')) {
                db.createObjectStore('meta');
            }
            // v2 — HBC visit offline queue
            if (!db.objectStoreNames.contains('hbcVisitQueue')) {
                db.createObjectStore('hbcVisitQueue', {keyPath: 'idb_id', autoIncrement: true});
            }
        };
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror = (e) => reject(e.target.error);
    });
}

function idbGetAll(db, storeName) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readonly');
        const req = tx.objectStore(storeName).getAll();
        req.onsuccess = () => resolve(req.result ?? []);
        req.onerror = () => reject(req.error);
    });
}

function idbGet(db, storeName, key) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readonly');
        const req = tx.objectStore(storeName).get(key);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

function idbDelete(db, storeName, key) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readwrite');
        const req = tx.objectStore(storeName).delete(key);
        req.onsuccess = () => resolve();
        req.onerror = () => reject(req.error);
    });
}

function idbAdd(db, storeName, value) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readwrite');
        const req = tx.objectStore(storeName).add(value);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

/**
 * Flush the HBC visit queue to the server.
 * Entries written by visit.php when the finalise POST fails offline.
 * Each entry carries all visit fields needed to reconstruct the write.
 */
async function flushHbcVisitQueue() {
    let db;
    try {
        db = await openIdb();
        const entries = await idbGetAll(db, 'hbcVisitQueue');
        if (entries.length === 0) return;

        const csrfToken = await idbGet(db, 'meta', 'csrf_token') ?? '';

        // Group by facility_id
        const byFacility = {};
        for (const e of entries) {
            const fid = e.facility_id ?? 1;
            (byFacility[fid] = byFacility[fid] ?? []).push(e);
        }

        for (const [facilityId, batch] of Object.entries(byFacility)) {
            const resp = await fetch(
                `./hbc/hbc_visit_sync.php?facility_id=${facilityId}`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: JSON.stringify({facility_id: parseInt(facilityId), entries: batch}),
                }
            );

            if (resp.ok) {
                const data = await resp.json();
                const ids = batch.map((e) => e.idb_id).filter(Boolean);
                for (const id of ids) {
                    await idbDelete(db, 'hbcVisitQueue', id);
                }
                broadcastToClients({
                    type: 'oei:hbc-sync-complete',
                    synced: ids.length,
                    results: data.results ?? [],
                });
            }
        }
    } catch (err) {
        console.warn('[OEI-SW] flushHbcVisitQueue error:', err);
    } finally {
        if (db) db.close();
    }
}






