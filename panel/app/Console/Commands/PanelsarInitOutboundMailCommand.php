<?php

namespace App\Console\Commands;

use App\Models\PanelSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PanelsarInitOutboundMailCommand extends Command
{
    protected $signature = 'panelsar:init-outbound-mail';

    protected $description = 'Varsayılan giden posta ayarlarını panel_settings içine yazar (kurulum betiği çağırır).';

    public function handle(): int
    {
        if (! Schema::hasTable('panel_settings')) {
            return self::SUCCESS;
        }

        if (PanelSetting::query()->where('key', 'like', 'outbound_mail.%')->exists()) {
            return self::SUCCESS;
        }

        $from = (string) config('mail.from.address', env('MAIL_FROM_ADDRESS', 'noreply@localhost'));
        $name = (string) config('mail.from.name', env('MAIL_FROM_NAME', config('app.name', 'Panelsar')));

        $pairs = [
            'outbound_mail.driver' => 'sendmail',
            'outbound_mail.smtp_host' => (string) env('MAIL_HOST', '127.0.0.1'),
            'outbound_mail.smtp_port' => (string) ((int) env('MAIL_PORT', 587)),
            'outbound_mail.smtp_username' => (string) (env('MAIL_USERNAME') ?? ''),
            'outbound_mail.smtp_encryption' => (string) (env('MAIL_ENCRYPTION') ?? ''),
            'outbound_mail.from_address' => $from,
            'outbound_mail.from_name' => $name,
        ];

        foreach ($pairs as $key => $value) {
            PanelSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $envPass = env('MAIL_PASSWORD');
        if (is_string($envPass) && $envPass !== '') {
            PanelSetting::query()->updateOrCreate(
                ['key' => 'outbound_mail.smtp_password'],
                ['value' => encrypt($envPass)]
            );
        }

        return self::SUCCESS;
    }
}
