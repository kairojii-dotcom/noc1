<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

/**
 * Native MikroTik RouterOS API client (binary protocol, port 8728).
 * Supports the post-6.43 plaintext login. No external dependency.
 */
final class MikrotikApiService
{
    private $socket = null;

    public function __construct(
        private string $host,
        private string $user,
        private string $pass,
        private int $port = 8728,
        private int $timeout = 5,
    ) {
    }

    public function connect(): bool
    {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        if (!$this->socket) {
            throw new \RuntimeException("MikroTik connect gagal: $errstr ($errno)");
        }
        stream_set_timeout($this->socket, $this->timeout);

        // Post-6.43 plain login
        $this->write('/login', false);
        $this->write('=name=' . $this->user, false);
        $this->write('=password=' . $this->pass);
        $resp = $this->read();
        return !$this->hasError($resp);
    }

    /** Fetch system resource (cpu, memory, uptime, board). */
    public function systemResource(): array
    {
        $this->write('/system/resource/print');
        return $this->parseFirst($this->read());
    }

    /** List active PPPoE sessions. */
    public function pppoeActive(): array
    {
        $this->write('/ppp/active/print');
        return $this->parseRecords($this->read());
    }

    /** List interfaces. */
    public function interfaces(): array
    {
        $this->write('/interface/print');
        return $this->parseRecords($this->read());
    }

    /** Run a raw export for backup. */
    public function export(): string
    {
        $this->write('/export');
        $resp = $this->read();
        return implode("\n", array_filter($resp, fn ($l) => !str_starts_with($l, '!')));
    }

    public function close(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    // ---- low-level RouterOS API word protocol ----
    private function write(string $word, bool $sentence = true): void
    {
        $this->writeLen(strlen($word));
        fwrite($this->socket, $word);
        if ($sentence) {
            fwrite($this->socket, chr(0)); // end of sentence
        }
    }

    private function writeLen(int $len): void
    {
        if ($len < 0x80) {
            fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            fwrite($this->socket, chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } elseif ($len < 0x200000) {
            $len |= 0xC00000;
            fwrite($this->socket, chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } else {
            fwrite($this->socket, chr(0xE0) . chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        }
    }

    private function readLen(): int
    {
        $b = ord(fread($this->socket, 1));
        if (($b & 0x80) === 0x00) {
            return $b;
        }
        if (($b & 0xC0) === 0x80) {
            return (($b & 0x3F) << 8) + ord(fread($this->socket, 1));
        }
        if (($b & 0xE0) === 0xC0) {
            return (($b & 0x1F) << 16) + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
        }
        return (ord(fread($this->socket, 1)) << 24) + (ord(fread($this->socket, 1)) << 16)
             + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
    }

    /** @return string[] flat list of words returned by the device. */
    private function read(): array
    {
        $out = [];
        while (true) {
            $len = $this->readLen();
            if ($len === 0) {
                // end of sentence; peek if more follows
                $meta = stream_get_meta_data($this->socket);
                if ($meta['unread_bytes'] <= 0 && feof($this->socket)) {
                    break;
                }
                // continue to next sentence; stop on !done captured below
                if (!empty($out) && in_array('!done', $out, true)) {
                    break;
                }
                continue;
            }
            $word = '';
            while (strlen($word) < $len) {
                $chunk = fread($this->socket, $len - strlen($word));
                if ($chunk === '' || $chunk === false) {
                    break;
                }
                $word .= $chunk;
            }
            $out[] = $word;
            if ($word === '!done') {
                break;
            }
        }
        return $out;
    }

    private function hasError(array $resp): bool
    {
        return in_array('!trap', $resp, true) || in_array('!fatal', $resp, true);
    }

    private function parseFirst(array $resp): array
    {
        $records = $this->parseRecords($resp);
        return $records[0] ?? [];
    }

    /** Convert flat word list into array of records keyed by attribute name. */
    private function parseRecords(array $resp): array
    {
        $records = [];
        $current = [];
        foreach ($resp as $word) {
            if ($word === '!re') {
                if ($current) {
                    $records[] = $current;
                }
                $current = [];
                continue;
            }
            if ($word === '!done') {
                if ($current) {
                    $records[] = $current;
                }
                break;
            }
            if (str_starts_with($word, '=')) {
                $kv = explode('=', substr($word, 1), 2);
                if (count($kv) === 2) {
                    $current[$kv[0]] = $kv[1];
                }
            }
        }
        return $records;
    }
}
