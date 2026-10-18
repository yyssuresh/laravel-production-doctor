<?php

declare(strict_types=1);

namespace LaravelProductionDoctor\Support;

/**
 * A conservative dotenv reader for diagnostics.
 *
 * It parses assignments only; it never evaluates shell/PHP expressions or expands
 * variable references. This is intentionally not a replacement for Laravel's
 * dotenv loader. Unsupported or malformed lines are ignored rather than executed.
 */
final class EnvFile
{
    /** @return array<string,string> */
    public static function parse(string $contents): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $contents) ?: [];
        $values = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^export\s+/', $line) === 1) {
                $line = preg_replace('/^export\s+/', '', $line, 1) ?? $line;
            }

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            $key = $matches[1];
            $raw = ltrim($matches[2]);
            $value = '';

            if ($raw !== '' && ($raw[0] === '"' || $raw[0] === "'")) {
                $quote = $raw[0];
                $body = substr($raw, 1);
                $closed = false;

                while (true) {
                    $closing = self::findClosingQuote($body, $quote);
                    if ($closing !== null) {
                        $tail = trim(substr($body, $closing + 1));
                        // After a quoted value, allow only whitespace or a comment.
                        // Reject ambiguous suffixes instead of silently accepting them.
                        if ($tail !== '' && !str_starts_with($tail, '#')) {
                            break;
                        }
                        $quotedValue = substr($body, 0, $closing);
                        $value = $quote === '"' ? self::decodeDoubleQuoted($quotedValue) : $quotedValue;
                        $closed = true;
                        break;
                    }

                    if ($i + 1 >= $count) {
                        break;
                    }

                    // Do not let an unterminated quote consume later dotenv assignments.
                    // Multiline values remain supported when continuation lines are not
                    // recognizable as a new KEY=value assignment.
                    $nextLine = ltrim($lines[$i + 1]);
                    if (preg_match('/^(?:export\s+)?[A-Za-z_][A-Za-z0-9_]*\s*=/', $nextLine) === 1) {
                        break;
                    }

                    $body .= "\n" . $lines[++$i];
                }

                if (!$closed) {
                    // Do not guess at a malformed quoted value.
                    continue;
                }
            } else {
                // A # starts an inline comment only when preceded by whitespace.
                $value = preg_replace('/\s+#.*$/', '', $raw) ?? $raw;
                $value = trim($value);
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /** @return array<string,string> */
    public static function fromFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $contents = @file_get_contents($path);
        return $contents === false ? [] : self::parse($contents);
    }

    /**
     * Merge a parsed file with process environment values.
     * Process values take precedence over .env values, matching deployment use.
     * Optional arguments make precedence deterministic in tests.
     *
     * @param array<string,string> $fileValues
     * @param array<string,mixed>|null $server
     * @param array<string,mixed>|null $environment
     * @param (callable(string): (string|false))|null $getEnv
     * @param array<string,mixed>|null $processEnvironment Explicit process values/keys for deterministic tests.
     * @return array<string,string>
     */
    public static function withProcessEnvironment(
        array $fileValues,
        ?array $server = null,
        ?array $environment = null,
        ?callable $getEnv = null,
        ?array $processEnvironment = null,
    ): array {
        $server ??= $_SERVER;
        $environment ??= $_ENV;
        $customLookup = $getEnv !== null;
        $getEnv ??= static fn (string $key): string|false => getenv($key);

        // When a lookup callback is injected, do not consult the real process
        // environment for key discovery unless an explicit snapshot is supplied.
        // This keeps tests hermetic and makes precedence deterministic.
        if ($processEnvironment === null) {
            if ($customLookup) {
                $processEnvironment = [];
            } else {
                $actualEnvironment = getenv();
                $processEnvironment = $actualEnvironment;
            }
        }
        $processKeys = array_keys($processEnvironment);
        $keys = array_unique(array_merge(array_keys($fileValues), array_keys($server), array_keys($environment), $processKeys));
        $resolved = $fileValues;

        foreach ($keys as $key) {
            if (!is_string($key) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                continue;
            }

            $value = $getEnv($key);
            if (is_string($value)) {
                $resolved[$key] = $value;
                continue;
            }

            if (array_key_exists($key, $processEnvironment)
                && (is_string($processEnvironment[$key]) || is_numeric($processEnvironment[$key]) || is_bool($processEnvironment[$key]))) {
                $rawProcessValue = $processEnvironment[$key];
                $resolved[$key] = is_bool($rawProcessValue) ? ($rawProcessValue ? '1' : '0') : (string) $rawProcessValue;
                continue;
            }

            if (isset($environment[$key]) && (is_string($environment[$key]) || is_numeric($environment[$key]) || is_bool($environment[$key]))) {
                $resolved[$key] = is_bool($environment[$key]) ? ($environment[$key] ? '1' : '0') : (string) $environment[$key];
                continue;
            }

            if (isset($server[$key]) && (is_string($server[$key]) || is_numeric($server[$key]) || is_bool($server[$key]))) {
                $resolved[$key] = is_bool($server[$key]) ? ($server[$key] ? '1' : '0') : (string) $server[$key];
            }
        }

        return $resolved;
    }

    private static function findClosingQuote(string $value, string $quote): ?int
    {
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            if ($value[$i] !== $quote) {
                continue;
            }

            if ($quote === '"') {
                $slashes = 0;
                for ($j = $i - 1; $j >= 0 && $value[$j] === '\\'; $j--) {
                    $slashes++;
                }
                if ($slashes % 2 === 1) {
                    continue;
                }
            }

            return $i;
        }

        return null;
    }

    private static function decodeDoubleQuoted(string $value): string
    {
        return strtr($value, [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\"' => '"',
            '\\\\' => '\\',
        ]);
    }
}
