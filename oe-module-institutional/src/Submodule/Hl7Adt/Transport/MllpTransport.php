<?php

namespace OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Transport;

/**
 * MLLP (Minimal Lower Layer Protocol) transport.
 *
 * MLLP framing:
 *   0x0B  <HL7 message bytes>  0x1C 0x0D
 *
 * Used by Mirth Connect, Rhapsody, Ensemble, Azure Health Data Services,
 * and virtually all hospital integration engines on the wire.
 */
final class MllpTransport
{
    private const START_BLOCK = "\x0B";
    private const END_BLOCK   = "\x1C\x0D";

    private string $host;
    private int    $port;
    private int    $timeoutSec;

    public function __construct(
        string $host        = '127.0.0.1',
        int    $port        = 2575,
        int    $timeoutSec  = 5
    ) {
        $this->host       = $host;
        $this->port       = $port;
        $this->timeoutSec = $timeoutSec;
    }

    /**
     * Send an HL7 message over MLLP.
     * Returns the ACK message string, or throws on failure.
     *
     * @throws \RuntimeException on connection or send failure
     */
    public function send(string $hl7Message): string
    {
        $socket = @fsockopen(
            $this->host,
            $this->port,
            $errno,
            $errstr,
            $this->timeoutSec
        );

        if ($socket === false) {
            throw new \RuntimeException(
                "MLLP connect failed [{$this->host}:{$this->port}]: {$errstr} ({$errno})"
            );
        }

        stream_set_timeout($socket, $this->timeoutSec);

        // Wrap in MLLP framing and send
        $framed = self::START_BLOCK . $hl7Message . self::END_BLOCK;
        $written = fwrite($socket, $framed);

        if ($written === false || $written < strlen($framed)) {
            fclose($socket);
            throw new \RuntimeException("MLLP write failed: only {$written} of " . strlen($framed) . " bytes sent");
        }

        // Read ACK response
        $ack = '';
        while (!feof($socket)) {
            $chunk = fread($socket, 4096);
            if ($chunk === false) {
                break;
            }
            $ack .= $chunk;
            // Stop reading after MLLP end block
            if (str_contains($ack, self::END_BLOCK)) {
                break;
            }
        }

        fclose($socket);

        // Strip MLLP framing from ACK
        $ack = ltrim($ack, self::START_BLOCK);
        $ack = rtrim($ack, self::END_BLOCK);

        return $ack;
    }

    /**
     * Validate an HL7 ACK response.
     * Returns true if AA (Application Accept), false on AE/AR.
     */
    public static function isAcknowledged(string $ack): bool
    {
        // MSA segment: MSA|AA|... = accepted, MSA|AE|... = error, MSA|AR|... = rejected
        if (preg_match('/MSA\|([A-Z]{2})\|/', $ack, $m)) {
            return $m[1] === 'AA';
        }
        return false;
    }
}


