<?php

namespace App\Services\Admin;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class LogExplorerService
{
    /**
     * @return array{
     *   tabs: array<string, array{label:string,count:int,available:int}>,
     *   entries: list<array<string,mixed>>,
     *   levels: array{error:int,warning:int,info:int,other:int},
     *   sites: list<string>,
     *   active_tab: string,
     *   active_level: string,
     *   active_site: string,
     *   active_today: bool,
     *   q: string
     * }
     */
    public function list(array $filters): array
    {
        $sources = (array) config('system_logs.sources', []);
        $tab = (string) ($filters['tab'] ?? 'all');
        $level = (string) ($filters['level'] ?? 'all');
        $site = (string) ($filters['site'] ?? 'all');
        $q = trim((string) ($filters['q'] ?? ''));
        $todayOnly = (bool) ($filters['today'] ?? false);

        $maxLines = max(20, (int) config('system_logs.max_lines_per_file', 180));
        $maxBytes = max(30_000, (int) config('system_logs.max_bytes_per_file', 1_500_000));

        $allEntries = [];
        $tabCounters = ['all' => ['label' => 'Tümü', 'count' => 0, 'available' => 0]];

        foreach ($sources as $sourceKey => $source) {
            $label = (string) Arr::get($source, 'label', $sourceKey);
            $files = $this->resolveFiles($source);
            $available = count($files);
            $tabCounters[$sourceKey] = ['label' => $label, 'count' => 0, 'available' => $available];

            if ($available === 0) {
                continue;
            }

            foreach ($files as $file) {
                foreach ($this->readTailLines($file, $maxLines, $maxBytes) as $line) {
                    $entry = $this->parseLine($line, $sourceKey, $label, $file);
                    $allEntries[] = $entry;
                    $tabCounters[$sourceKey]['count']++;
                    $tabCounters['all']['count']++;
                }
            }
        }

        $activeTab = array_key_exists($tab, $tabCounters) ? $tab : 'all';

        $filtered = collect($allEntries)
            ->when($activeTab !== 'all', fn (Collection $c) => $c->where('source_key', $activeTab))
            ->when($level !== 'all', fn (Collection $c) => $c->where('level', $level))
            ->when($site !== 'all', fn (Collection $c) => $c->where('site', $site))
            ->when($todayOnly, function (Collection $c) {
                $today = date('Y-m-d');

                return $c->filter(fn (array $e): bool => str_starts_with((string) $e['timestamp'], $today));
            })
            ->when($q !== '', function (Collection $c) use ($q) {
                $needle = Str::lower($q);

                return $c->filter(function (array $e) use ($needle): bool {
                    return Str::contains(Str::lower($e['message']), $needle)
                        || Str::contains(Str::lower($e['source_label']), $needle)
                        || Str::contains(Str::lower($e['site']), $needle)
                        || Str::contains(Str::lower($e['file_name']), $needle);
                });
            });

        $sorted = $filtered
            ->sortByDesc(fn (array $e) => $e['sort_ts'])
            ->take(1000)
            ->values();

        $levels = [
            'error' => (int) $sorted->where('level', 'error')->count(),
            'warning' => (int) $sorted->where('level', 'warning')->count(),
            'info' => (int) $sorted->where('level', 'info')->count(),
            'other' => (int) $sorted->where('level', 'other')->count(),
        ];

        $sites = $sorted
            ->pluck('site')
            ->filter(fn (string $s) => $s !== 'genel')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'tabs' => $tabCounters,
            'entries' => $sorted->all(),
            'levels' => $levels,
            'sites' => $sites,
            'active_tab' => $activeTab,
            'active_level' => in_array($level, ['all', 'error', 'warning', 'info', 'other'], true) ? $level : 'all',
            'active_site' => $site === '' ? 'all' : $site,
            'active_today' => $todayOnly,
            'q' => $q,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return list<string>
     */
    private function resolveFiles(array $source): array
    {
        $files = [];
        foreach ((array) ($source['files'] ?? []) as $f) {
            $p = (string) $f;
            if ($p !== '' && is_file($p) && is_readable($p)) {
                $files[] = $p;
            }
        }
        foreach ((array) ($source['globs'] ?? []) as $pattern) {
            foreach ((array) glob((string) $pattern) as $f) {
                if (is_file($f) && is_readable($f)) {
                    $files[] = $f;
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @return list<string>
     */
    private function readTailLines(string $file, int $maxLines, int $maxBytes): array
    {
        $size = @filesize($file);
        if (! is_int($size) || $size <= 0) {
            return [];
        }

        $readBytes = min($size, $maxBytes);
        $fp = @fopen($file, 'rb');
        if ($fp === false) {
            return [];
        }

        $offset = max(0, $size - $readBytes);
        fseek($fp, $offset);
        $data = (string) fread($fp, $readBytes);
        fclose($fp);

        if ($offset > 0) {
            $pos = strpos($data, "\n");
            if ($pos !== false) {
                $data = substr($data, $pos + 1);
            }
        }

        $lines = preg_split("/\r\n|\n|\r/", $data) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $l): bool => trim($l) !== ''));

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, -$maxLines);
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseLine(string $line, string $sourceKey, string $sourceLabel, string $file): array
    {
        $ts = $this->extractTimestamp($line);
        $level = $this->extractLevel($line);
        $site = $this->extractSite($line);
        $message = Str::limit(trim($line), 1500, '...');

        return [
            'source_key' => $sourceKey,
            'source_label' => $sourceLabel,
            'file_name' => basename($file),
            'file_path' => $file,
            'timestamp' => $ts?->format('Y-m-d H:i:s') ?? '-',
            'sort_ts' => $ts?->timestamp ?? 0,
            'level' => $level,
            'site' => $site,
            'message' => $message,
        ];
    }

    private function extractTimestamp(string $line): ?\DateTimeImmutable
    {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]/', $line, $m) === 1) {
            return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $m[1]) ?: null;
        }
        if (preg_match('/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/', $line, $m) === 1) {
            return new \DateTimeImmutable($m[1]);
        }

        return null;
    }

    private function extractLevel(string $line): string
    {
        $l = Str::lower($line);
        if (Str::contains($l, [' error ', '.error', ' fatal', 'exception', 'crit', 'emerg'])) {
            return 'error';
        }
        if (Str::contains($l, [' warn', 'warning', 'deprecated', 'notice'])) {
            return 'warning';
        }
        if (Str::contains($l, [' info', '.info', ' started', 'ok'])) {
            return 'info';
        }

        return 'other';
    }

    private function extractSite(string $line): string
    {
        if (preg_match('/\b([a-z0-9][a-z0-9.-]+\.[a-z]{2,})\b/i', $line, $m) === 1) {
            return Str::lower($m[1]);
        }
        if (preg_match('#/var/www/[^/\s]+/[^/\s]+/([^/\s]+)/#', $line, $m) === 1) {
            return Str::lower($m[1]);
        }
        if (preg_match('#/var/www/([^/\s]+)/#', $line, $m) === 1) {
            return Str::lower($m[1]);
        }

        return 'genel';
    }
}
