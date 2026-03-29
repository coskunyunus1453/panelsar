<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PanelSetting;
use App\Services\OutboundMailConfigurator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class OutboundMailSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $rows = PanelSetting::query()
            ->where('key', 'like', 'outbound_mail.%')
            ->pluck('value', 'key');

        $pass = $rows->get('outbound_mail.smtp_password');

        return response()->json([
            'driver' => $rows->get('outbound_mail.driver', config('mail.default', 'log')),
            'smtp_host' => $rows->get('outbound_mail.smtp_host', ''),
            'smtp_port' => (int) ($rows->get('outbound_mail.smtp_port', 587) ?: 587),
            'smtp_username' => $rows->get('outbound_mail.smtp_username', ''),
            'smtp_password_set' => is_string($pass) && $pass !== '',
            'smtp_encryption' => $rows->get('outbound_mail.smtp_encryption', '') ?: '',
            'from_address' => $rows->get('outbound_mail.from_address', config('mail.from.address')),
            'from_name' => $rows->get('outbound_mail.from_name', config('mail.from.name')),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver' => ['required', 'string', Rule::in(['smtp', 'sendmail', 'log'])],
            'smtp_host' => ['required_if:driver,smtp', 'nullable', 'string', 'max:255'],
            'smtp_port' => ['required_if:driver,smtp', 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'smtp_encryption' => ['nullable', 'string', Rule::in(['', 'tls', 'ssl'])],
            'clear_smtp_password' => ['sometimes', 'boolean'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:120'],
        ]);

        $set = function (string $key, ?string $value): void {
            PanelSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value ?? '']
            );
        };

        $set('outbound_mail.driver', $validated['driver']);
        $set('outbound_mail.smtp_host', $validated['smtp_host'] ?? '');
        $set('outbound_mail.smtp_port', isset($validated['smtp_port']) ? (string) $validated['smtp_port'] : '587');
        $set('outbound_mail.smtp_username', $validated['smtp_username'] ?? '');
        $enc = $validated['smtp_encryption'] ?? '';
        $set('outbound_mail.smtp_encryption', $enc === '' ? '' : $enc);
        $set('outbound_mail.from_address', $validated['from_address']);
        $set('outbound_mail.from_name', $validated['from_name']);

        if ($request->boolean('clear_smtp_password')) {
            PanelSetting::query()->where('key', 'outbound_mail.smtp_password')->delete();
        } elseif (! empty($validated['smtp_password'])) {
            $set('outbound_mail.smtp_password', encrypt($validated['smtp_password']));
        }

        OutboundMailConfigurator::apply();

        return $this->show();
    }

    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'to' => ['nullable', 'email', 'max:255'],
        ]);

        OutboundMailConfigurator::apply();

        $to = $request->input('to') ?: $request->user()->email;

        try {
            Mail::raw(__('stack.mail_test_body'), function ($message) use ($to): void {
                $message->to($to)->subject(__('stack.mail_test_subject'));
            });
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['message' => __('stack.mail_test_sent', ['email' => $to])]);
    }
}
