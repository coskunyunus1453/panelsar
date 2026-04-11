<?php

namespace App\Services;

/**
 * BIND-benzeri zone metninden panel DNS kayıtları (göreli isim).
 *
 * @phpstan-type ParsedRecord array{type: string, name: string, value: string, ttl: int|null, priority: int|null}
 */
class BindZoneTextParser
{
    /**
     * @return list<ParsedRecord>
     */
    public static function parse(string $text, string $zoneFqdn): array
    {
        $origin = strtolower(rtrim(trim($zoneFqdn), '.'));
        if ($origin === '' || str_contains($origin, '..')) {
            return [];
        }

        $defaultTtl = 3600;
        $out = [];
        $skipped = [];

        foreach (preg_split("/\r\n|\n|\r/", $text) as $rawLine) {
            $line = self::stripComment(trim($rawLine));
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\$ORIGIN\s+/i', $line)) {
                $rest = trim(preg_replace('/^\$ORIGIN\s+/i', '', $line) ?? '');
                $rest = strtolower(rtrim(trim($rest, " \t"), '.'));
                if ($rest !== '' && ! str_contains($rest, '..')) {
                    $origin = $rest;
                }

                continue;
            }
            if (preg_match('/^\$TTL\s+(\d+)/i', $line, $tm)) {
                $defaultTtl = max(60, (int) $tm[1]);

                continue;
            }
            if (str_starts_with($line, '$')) {
                $skipped[] = $line;

                continue;
            }

            $rec = self::parseRecordLine($line, $origin, $defaultTtl);
            if ($rec === null) {
                $skipped[] = $line;

                continue;
            }
            $out[] = $rec;
        }

        return $out;
    }

    /**
     * @return ParsedRecord|null
     */
    private static function parseRecordLine(string $line, string $origin, int $defaultTtl): ?array
    {
        if (preg_match('/^(\S+)\s+(\d+)\s+IN\s+MX\s+(\d+)\s+(\S+)\s*$/i', $line, $m)) {
            return [
                'type' => 'MX',
                'name' => self::toRelativeName($m[1], $origin),
                'value' => self::normalizeMxTarget($m[4]),
                'ttl' => (int) $m[2],
                'priority' => (int) $m[3],
            ];
        }

        if (preg_match('/^(\S+)\s+IN\s+MX\s+(\d+)\s+(\S+)\s*$/i', $line, $m)) {
            return [
                'type' => 'MX',
                'name' => self::toRelativeName($m[1], $origin),
                'value' => self::normalizeMxTarget($m[3]),
                'ttl' => $defaultTtl,
                'priority' => (int) $m[2],
            ];
        }

        if (preg_match('/^(\S+)\s+(\d+)\s+IN\s+TXT\s+/i', $line, $m)) {
            $rest = trim(substr($line, strlen($m[0])));

            return [
                'type' => 'TXT',
                'name' => self::toRelativeName($m[1], $origin),
                'value' => self::parseTxtConcatenated($rest),
                'ttl' => (int) $m[2],
                'priority' => null,
            ];
        }

        if (preg_match('/^(\S+)\s+IN\s+TXT\s+/i', $line, $m)) {
            $rest = trim(substr($line, strlen($m[0])));

            return [
                'type' => 'TXT',
                'name' => self::toRelativeName($m[1], $origin),
                'value' => self::parseTxtConcatenated($rest),
                'ttl' => $defaultTtl,
                'priority' => null,
            ];
        }

        if (preg_match('/^(\S+)\s+(\d+)\s+IN\s+([A-Z0-9]+)\s+(.+)$/i', $line, $m)) {
            $type = strtoupper($m[3]);
            if (in_array($type, ['SOA', 'NS', 'CAA', 'SRV', 'PTR'], true)) {
                return null;
            }

            return [
                'type' => $type,
                'name' => self::toRelativeName($m[1], $origin),
                'value' => self::normalizeValue($type, trim($m[4])),
                'ttl' => (int) $m[2],
                'priority' => null,
            ];
        }

        if (preg_match('/^(\S+)\s+IN\s+([A-Z0-9]+)\s+(.+)$/i', $line, $m)) {
            $type = strtoupper($m[2]);
            if (in_array($type, ['SOA', 'NS', 'CAA', 'SRV', 'PTR', 'MX', 'TXT'], true)) {
                return null;
            }

            return [
                'type' => $type,
                'name' => self::toRelativeName($m[1], $origin),
                'value' => self::normalizeValue($type, trim($m[3])),
                'ttl' => $defaultTtl,
                'priority' => null,
            ];
        }

        return null;
    }

    private static function stripComment(string $line): string
    {
        $line = trim($line);
        if ($line === '') {
            return '';
        }
        $parts = preg_split('/"(?:\\\\.|[^"\\\\])*"/', $line, -1, PREG_SPLIT_OFFSET_CAPTURE);
        // Basit: tırnak dışındaki ilk ; sonrasını at
        $inQuote = false;
        $escaped = false;
        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            $c = $line[$i];
            if ($escaped) {
                $escaped = false;

                continue;
            }
            if ($c === '\\' && $inQuote) {
                $escaped = true;

                continue;
            }
            if ($c === '"') {
                $inQuote = ! $inQuote;

                continue;
            }
            if (! $inQuote && $c === ';') {
                return trim(substr($line, 0, $i));
            }
        }

        return $line;
    }

    private static function parseTxtConcatenated(string $rest): string
    {
        $rest = trim($rest);
        if ($rest === '') {
            return '';
        }
        $chunks = [];
        $offset = 0;
        $len = strlen($rest);
        while ($offset < $len) {
            while ($offset < $len && ($rest[$offset] === ' ' || $rest[$offset] === "\t")) {
                $offset++;
            }
            if ($offset >= $len) {
                break;
            }
            if ($rest[$offset] === '"') {
                $i = $offset + 1;
                $buf = '';
                while ($i < $len) {
                    if ($rest[$i] === '\\' && $i + 1 < $len) {
                        $buf .= $rest[$i + 1];
                        $i += 2;

                        continue;
                    }
                    if ($rest[$i] === '"') {
                        $chunks[] = $buf;
                        $offset = $i + 1;
                        continue 2;
                    }
                    $buf .= $rest[$i];
                    $i++;
                }

                return implode('', $chunks);
            }
            $sp = strcspn($rest, " \t", $offset);
            $chunks[] = substr($rest, $offset, $sp);
            $offset += $sp;
        }

        return implode('', $chunks);
    }

    private static function normalizeMxTarget(string $target): string
    {
        $t = strtolower(rtrim(trim($target), '.'));

        return $t;
    }

    private static function normalizeValue(string $type, string $value): string
    {
        $type = strtoupper($type);
        $value = trim($value);
        if (in_array($type, ['A', 'AAAA'], true)) {
            return $value;
        }
        if ($type === 'CNAME') {
            return strtolower(rtrim($value, '.'));
        }

        return $value;
    }

    private static function toRelativeName(string $label, string $origin): string
    {
        $label = strtolower(trim($label));
        if ($label === '@' || $label === '') {
            return '@';
        }
        $label = rtrim($label, '.');
        $origin = strtolower(rtrim($origin, '.'));
        if ($label === $origin) {
            return '@';
        }
        $suffix = '.'.$origin;
        if (str_ends_with($label, $suffix)) {
            $rel = substr($label, 0, -strlen($suffix));

            return $rel !== '' ? $rel : '@';
        }

        return $label;
    }
}
