<?php

/**
 * src/Core/Service/FormsRegistrar.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Core\Service;

/**
 * FormsRegistrar — wraps OpenEMR's addForm() from library/forms.inc.php.
 *
 * Every time this module writes a row to form_care_plan or form_clinical_notes
 * it MUST call FormsRegistrar::register() so the entry also appears in:
 *   - The OpenEMR encounter form list (patient_file/encounter/forms.php)
 *   - The CCDA Carecoordination module export pipeline
 *   - The FHIR CarePlan / DocumentReference resource generation
 *
 * Without this call, rows in form_* tables are invisible to OpenEMR's core.
 *
 * Safe to call even when forms.inc.php is not available (unit-test context)
 * — logs a warning but does not throw.
 */
final class FormsRegistrar
{
    /**
     * Register a new form entry in the OpenEMR `forms` table.
     *
     * @param int    $pid       patient_data.pid
     * @param int    $encounter encounter NUMBER stored in form_encounter.encounter / forms.encounter
     * @param int    $formId    The auto-increment id just returned from sqlInsert()
     * @param string $formdir   Registry key: 'care_plan' | 'clinical_notes'
     * @param string $formName  Display label: 'Care Plan' | 'Clinical Notes'
     * @param int    $userId    Authoring users.id
     */
    public function register(
        int    $pid,
        int    $encounter,
        int    $formId,
        string $formdir,
        string $formName,
        int    $userId
    ): void {
        // Lazy-load forms.inc.php — it is always present in a live OpenEMR
        // installation but absent in isolated unit-test environments.
        if (!function_exists('addForm')) {
            $inc = ($GLOBALS['srcdir'] ?? '') . '/forms.inc.php';
            if (file_exists($inc)) {
                require_once $inc;
            }
        }

        if (!function_exists('addForm')) {
            error_log('[OEI FormsRegistrar] addForm() not available — forms.inc.php not loaded. '
                . "formdir={$formdir} formId={$formId} pid={$pid} encounter={$encounter}");
            return;
        }

        // addForm(encounter, form_name, form_id, formdir, pid, authorized,
        //         date, user, provider_id)
        addForm(
            $encounter,
            $formName,
            $formId,
            $formdir,
            $pid,
            1,                              // authorized
            date('Y-m-d H:i:s'),
            $_SESSION['authUser'] ?? 'admin',
            $userId
        );
    }
}





