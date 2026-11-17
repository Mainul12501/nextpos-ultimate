<?php

namespace App\Services;

use RuntimeException;

/**
 * Reads and rewrites individual keys in the .env file without disturbing the
 * rest of it (comments, ordering and line endings are kept).
 *
 * phpdotenv loads the file immutably, so the FIRST definition of a key wins.
 * get() mirrors that, and set() rewrites the first line and drops any later
 * duplicates so an edit can never be silently shadowed.
 */
class EnvEditor
{
    private const BACKUP_DIR = 'app/env-backups';
    private const BACKUPS_KEPT = 10;

    public function path(): string
    {
        return app()->environmentFilePath();
    }

    public function isWritable(): bool
    {
        $path = $this->path();

        return file_exists($path) ? is_writable($path) : is_writable(dirname($path));
    }

    /**
     * Current raw values for the given keys (null when the key is absent).
     *
     * @param  string[]  $keys
     * @return array<string, string|null>
     */
    public function get(array $keys): array
    {
        $out = array_fill_keys($keys, null);
        $lines = $this->lines();

        foreach ($keys as $key) {
            foreach ($lines as $line) {
                if (preg_match($this->linePattern($key), $line, $m)) {
                    $out[$key] = $this->parseValue($m[1]);
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * Write the given keys. A null value removes the key, so the app falls
     * back to its built-in default. Missing keys are appended at the end.
     *
     * @param  array<string, scalar|null>  $values
     */
    public function set(array $values): void
    {
        if (! $this->isWritable()) {
            throw new RuntimeException('The .env file is not writable by the web server.');
        }

        $path = $this->path();
        $content = file_exists($path) ? (string) file_get_contents($path) : '';
        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";
        $lines = $content === '' ? [] : preg_split('/\r\n|\n|\r/', $content);

        // A trailing newline produces one empty last element; keep it aside.
        $trailing = $lines && end($lines) === '';
        if ($trailing) {
            array_pop($lines);
        }

        foreach ($values as $key => $value) {
            $this->assertKey($key);
            $formatted = $value === null ? null : $key.'='.$this->formatValue($value);
            $written = false;

            foreach ($lines as $i => $line) {
                if (! preg_match($this->linePattern($key), $line)) {
                    continue;
                }
                if ($formatted !== null && ! $written) {
                    $lines[$i] = $formatted;
                    $written = true;
                } else {
                    unset($lines[$i]);
                }
            }

            if ($formatted !== null && ! $written) {
                $lines[] = $formatted;
            }
            $lines = array_values($lines);
        }

        $this->backup($path);

        $new = implode($eol, $lines).$eol;
        if (file_put_contents($path, $new, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the .env file.');
        }
    }

    /** @return string[] */
    private function lines(): array
    {
        $path = $this->path();
        if (! is_readable($path)) {
            return [];
        }

        return preg_split('/\r\n|\n|\r/', (string) file_get_contents($path));
    }

    private function linePattern(string $key): string
    {
        return '/^\s*(?:export\s+)?'.preg_quote($key, '/').'\s*=(.*)$/';
    }

    private function parseValue(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $quote = $raw[0];
        if ($quote === '"' || $quote === "'") {
            $end = strrpos($raw, $quote);
            $inner = $end > 0 ? substr($raw, 1, $end - 1) : substr($raw, 1);

            return $quote === '"' ? stripcslashes($inner) : $inner;
        }

        // Unquoted: an inline comment starts at " #".
        return trim(preg_replace('/\s+#.*$/', '', $raw));
    }

    private function formatValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;
        if (preg_match('/[\r\n]/', $value)) {
            throw new RuntimeException('Values cannot contain line breaks.');
        }

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/\-,@+]+$/', $value)) {
            return $value;
        }

        return '"'.addcslashes($value, '"\\$').'"';
    }

    private function assertKey(string $key): void
    {
        if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            throw new RuntimeException("Invalid environment key [$key].");
        }
    }

    /** Timestamped copy before every write; only the newest few are kept. */
    private function backup(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }

        $dir = storage_path(self::BACKUP_DIR);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            return;
        }

        @copy($path, $dir.DIRECTORY_SEPARATOR.'.env.'.date('Ymd-His'));

        $files = glob($dir.DIRECTORY_SEPARATOR.'.env.*') ?: [];
        rsort($files);
        foreach (array_slice($files, self::BACKUPS_KEPT) as $old) {
            @unlink($old);
        }
    }
}
