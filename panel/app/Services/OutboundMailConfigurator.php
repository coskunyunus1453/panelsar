<?php

namespace App\Services;

use App\Models\PanelSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class OutboundMailConfigurator
{
    /**
     * Panel veritabanındaki outbound_mail.* değerlerini çalışma anında Laravel mail
     * yapılandırmasına uygular (Laravel 11+ Symfony Mailer: smtp/smtps scheme).
     *
     * Not: MailManager çözümlenmiş taşıyıcıları önbelleğe alır; apply() sonunda
     * önbellek temizlenir (özellikle uzun ömürlü queue worker süreçleri için).
     */
    public static function apply(): void
    {
        try {
            if (! Schema::hasTable('panel_settings')) {
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
                Config::set('mail.mailers.smtp.host', is_string($host) && $host !== '' ? $host : '127.0.0.1');

                $port = $settings->get('outbound_mail.smtp_port');
                $port = is_numeric($port) ? (int) $port : 587;
                Config::set('mail.mailers.smtp.port', $port);

                $user = $settings->get('outbound_mail.smtp_username');
                Config::set('mail.mailers.smtp.username', is_string($user) && $user !== '' ? $user : null);

                $encRaw = $settings->get('outbound_mail.smtp_encryption');
                $enc = is_string($encRaw) ? strtolower(trim($encRaw)) : '';
                if (! in_array($enc, ['', 'tls', 'ssl'], true)) {
                    $enc = '';
                }

                /*
                 * Laravel 11 MailManager / Symfony EsmtpTransport: "encryption" anahtarı yok sayılır;
                 * TLS modu scheme (smtp = STARTTLS opsiyonel/zorunluluğa göre, smtps = implicit TLS) ile belirlenir.
                 */
                if ($enc === 'ssl' || $port === 465) {
                    Config::set('mail.mailers.smtp.scheme', 'smtps');
                } else {
                    Config::set('mail.mailers.smtp.scheme', 'smtp');
                }

                // Panelden host/port set edilirken MAIL_URL birleşmesin (öncelik panel ayarı).
                Config::set('mail.mailers.smtp.url', null);

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
        } catch (\Throwable) {
            // panel_settings erişimi vb. — dosya tabanlı mail yapılandırması geçerli kalır
        } finally {
            self::forgetResolvedMailers();
        }
    }

    private static function forgetResolvedMailers(): void
    {
        if (app()->bound('mail.manager')) {
            app('mail.manager')->forgetMailers();
        }
    }
}
