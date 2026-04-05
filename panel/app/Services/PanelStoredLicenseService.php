<?php

namespace App\Services;

use App\Models\PanelSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Lisans anahtarını .env yerine panel_settings içinde (APP_KEY ile şifreli) saklar.
 */
class PanelStoredLicenseService
{
    public const SETTING_KEY = 'license.stored_key_enc';

    public function envKey(): string
    {
        return trim((string) config('hostvim.license_key'));
    }

    public function storedPlaintext(): ?string
    {
        if (! Schema::hasTable('panel_settings')) {
            return null;
        }
        $raw = PanelSetting::query()->where('key', self::SETTING_KEY)->value('value');
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function effectiveKey(): string
    {
        $env = $this->envKey();
        if ($env !== '') {
            return $env;
        }

        return trim((string) ($this->storedPlaintext() ?? ''));
    }

    /**
     * @return 'env'|'database'|'none'
     */
    public function keySource(): string
    {
        if ($this->envKey() !== '') {
            return 'env';
        }
        $stored = $this->storedPlaintext();
        if ($stored !== null && trim($stored) !== '') {
            return 'database';
        }

        return 'none';
    }

    public function store(string $plainKey): void
    {
        if (! Schema::hasTable('panel_settings')) {
            throw new \RuntimeException('panel_settings table missing');
        }
        $plainKey = trim($plainKey);
        PanelSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => Crypt::encryptString($plainKey)]
        );
    }

    public function clearStored(): void
    {
        if (! Schema::hasTable('panel_settings')) {
            return;
        }
        PanelSetting::query()->where('key', self::SETTING_KEY)->delete();
    }

    public static function maskKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        if (strlen($key) <= 10) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 4).str_repeat('*', max(4, strlen($key) - 8)).substr($key, -4);
    }
}
