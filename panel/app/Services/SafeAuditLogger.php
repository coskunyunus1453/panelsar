<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Uygulama içi audit logları: ham gizli alan, tam dosya yolu ve ham IP gibi
 * verileri log dosyalarına düşürmeden yapılandırılmış kayıt üretir.
 */
final class SafeAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $channel, array $context, ?Request $request = null): void
    {
        self::write('info', $channel, $context, $request);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $channel, array $context, ?Request $request = null): void
    {
        self::write('warning', $channel, $context, $request);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(string $level, string $channel, array $context, ?Request $request): void
    {
        if (! config('hostvim.safe_audit_enabled', true)) {
            return;
        }

        $payload = self::sanitizeContext($context);

        if ($request !== null) {
            $payload['ip_hash'] = hash('sha256', (string) $request->ip());
            if ($request->user() !== null) {
                $payload['actor_user_id'] = $request->user()->id;
            }
        }

        if ($level === 'warning') {
            Log::warning($channel, $payload);
        } else {
            Log::info($channel, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function sanitizeContext(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $k = (string) $key;
            if (self::isSensitiveKey($k)) {
                $out[$k] = '[REDACTED]';

                continue;
            }
            $out[$k] = self::sanitizeValue($value);
        }

        return $out;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        return (bool) preg_match(
            '/(password|passwd|secret|token|private|credential|authorization|cookie|api[_-]?key|access[_-]?key|secret[_-]?key|ssh|webhook|bearer|credit|cvv|pan)/i',
            $lower
        );
    }

    private static function sanitizeValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_array($value)) {
            return self::sanitizeContext($value);
        }
        if (! is_string($value)) {
            return null;
        }

        $s = $value;
        // Mutlak dosya yollarını ve uzun path parçalarını maskele
        $s = (string) preg_replace('#(/|\\\\)(?:var|usr|home|Applications|private)\\\\?[/\\\\][^\s]{1,240}#i', '[PATH]', $s);
        $s = (string) preg_replace('#/[a-z0-9_.-]{1,80}/[a-z0-9_.-]{1,80}/[^\s]+#i', '[PATH]', $s);

        if (mb_strlen($s) > 400) {
            $s = mb_substr($s, 0, 400).'…';
        }

        return $s;
    }

    public static function pathFingerprint(string $siteDomain, ?string $relativePath): ?string
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return null;
        }

        return substr(hash('sha256', strtolower(trim($siteDomain)).'|'.$relativePath), 0, 16);
    }

    public static function pathBasename(?string $relativePath): ?string
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return null;
        }
        $norm = str_replace('\\', '/', $relativePath);
        $base = basename($norm);

        return $base !== '' ? $base : null;
    }

    public static function hostFingerprint(?string $host): ?string
    {
        if ($host === null || trim($host) === '') {
            return null;
        }

        return substr(hash('sha256', strtolower(trim($host))), 0, 16);
    }
}
