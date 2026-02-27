<?php

namespace OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Transport;

/**
 * HTTP/HTTPS POST transport for HL7 messages.
 *
 * Useful for:
 *   - Azure Health Data Services (AHDS) FHIR/HL7 ingest
 *   - AWS HealthLake
 *   - Cloud integration engines (Rhapsody Cloud, Mirth Connect REST)
 *   - Local test endpoints during development
 *
 * Content-Type: application/hl7-v2; charset=UTF-8  (per RFC 5234 / IANA)
 */
final class HttpTransport
{
    private string  $url;
    private int     $timeoutSec;
    private array   $headers;
    private ?string $bearerToken;

    public function __construct(
        string  $url,
        int     $timeoutSec  = 10,
        array   $extraHeaders = [],
        ?string $bearerToken  = null
    ) {
        $this->url         = $url;
        $this->timeoutSec  = $timeoutSec;
        $this->headers     = $extraHeaders;
        $this->bearerToken = $bearerToken;
    }

    /**
     * POST an HL7 message to the configured URL.
     * Returns the response body.
     *
     * @throws \RuntimeException on transport failure or non-2xx response
     */
    public function send(string $hl7Message): string
    {
        $headers = array_merge(
            [
                'Content-Type: application/hl7-v2; charset=UTF-8',
                'Accept: application/hl7-v2',
                'Content-Length: ' . strlen($hl7Message),
            ],
            $this->headers
        );

        if ($this->bearerToken !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->bearerToken;
        }

        $ctx = stream_context_create([
            'http' => [
                'method'          => 'POST',
                'header'          => implode("\r\n", $headers),
                'content'         => $hl7Message,
                'timeout'         => $this->timeoutSec,
                'ignore_errors'   => true,    // get body even on 4xx/5xx
            ],
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
            ],
        ]);

        $response = @file_get_contents($this->url, false, $ctx);

        if ($response === false) {
            throw new \RuntimeException("HTTP transport failed: could not connect to {$this->url}");
        }

        // Check HTTP status from $http_response_header (populated by file_get_contents)
        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/HTTP\/[\d.]+ (\d{3})/', $statusLine, $m)) {
            $code = (int)$m[1];
            if ($code < 200 || $code >= 300) {
                throw new \RuntimeException(
                    "HTTP transport received {$code} from {$this->url}: " . substr($response, 0, 256)
                );
            }
        }

        return $response;
    }
}


