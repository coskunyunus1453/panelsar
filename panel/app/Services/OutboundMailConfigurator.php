<?php

namespace App\Services;

use App\Models\PanelSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class OutboundMailConfigurator
{
    public static function apply(): void
    {
        try {
            if (! Schema::hasTable('panel_settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $settings = PanelSetting::query()
            ->where('key', 'like', 'outbound_mail.%')
            ->pluck('value', 'key');

        if ($settings->isEmpty()) {
            return;
        }

        $driver = $settings->get('outbound_mail.driver');
        if (! is_string($driver) || ! in_array($driver, ['smtp', 'sendmail', 'log'], true)) {
            return;
        }

        Config::set('mail.default', $driver);

        if ($driver === 'smtp') {
            $host = $settings->get('outbound_mail.smtp_host');
            $port = $settings->get('outbound_mail.smtp_port');
            Config::set('mail.mailers.smtp.host', is_string($host) && $host !== '' ? $host : '127.0.0.1');
            Config::set('mail.mailers.smtp.port', is_numeric($port) ? (int) $port : 587);
            $user = $settings->get('outbound_mail.smtp_username');
            Config::set('mail.mailers.smtp.username', is_string($user) && $user !== '' ? $user : null);
            $enc = $settings->get('outbound_mail.smtp_encryption');
            if ($enc === '' || $enc === null) {
                Config::set('mail.mailers.smtp.encryption', null);
            } elseif (in_array($enc, ['tls', 'ssl'], true)) {
                Config::set('mail.mailers.smtp.encryption', $enc);
            } else {
                Config::set('mail.mailers.smtp.encryption', null);
            }
            $rawPass = $settings->get('outbound_mail.smtp_password');
            $plain = null;
            if (is_string($rawPass) && $rawPass !== '') {
                try {
                    $plain = decrypt($rawPass);
                } catch (\Throwable) {
                    $plain = $rawPass;
                }
            }
            Config::set('mail.mailers.smtp.password', $plain);
        }

        $addr = $settings->get('outbound_mail.from_address');
        if (is_string($addr) && $addr !== '') {
            Config::set('mail.from.address', $addr);
        }
        $name = $settings->get('outbound_mail.from_name');
        if (is_string($name) && $name !== '') {
            Config::set('mail.from.name', $name);
        }
    }
}
