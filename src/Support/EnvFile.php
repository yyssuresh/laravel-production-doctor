<?php

declare(strict_types=1);

namespace LaravelProductionDoctor\Support;

/** Reads simple KEY=value lines without evaluating shell/PHP expressions. */
final class EnvFile
{
    /** @return array<string,string> */
    public static function parse(string $contents): array
    {
        $values = [];
        foreach (preg_split('/\r\n|\n|\r/', $contents) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, 'export ')) continue;
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) continue;
            $key = $matches[1];
            $value = trim($matches[2]);
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
                if ($matches[2][0] === '"') $value = stripcslashes($value);
            } else {
                $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
            }
            $values[$key] = $value;
        }
        return $values;
    }

    /** @return array<string,string> */
    public static function fromFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) return [];
        $contents = file_get_contents($path);
        return $contents === false ? [] : self::parse($contents);
    }
}
