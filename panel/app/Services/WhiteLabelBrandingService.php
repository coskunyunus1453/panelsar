<?php

namespace App\Services;

use App\Models\PanelSetting;
use App\Models\ResellerWhiteLabel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class WhiteLabelBrandingService
{
    /**
     * Bayi veya müşteri için etkin white-label kaydı (müşteride parent üzerinden).
     */
    public function profileForUser(?User $user): ?ResellerWhiteLabel
    {
        if ($user === null) {
            return null;
        }

        $ownerId = null;
        if ($user->parent_id) {
            $parent = $user->relationLoaded('parentUser')
                ? $user->parentUser
                : User::query()->find($user->parent_id);
            if ($parent && $parent->isReseller()) {
                $ownerId = $parent->id;
            }
        } elseif ($user->isReseller()) {
            $ownerId = $user->id;
        }

        if ($ownerId === null) {
            return null;
        }

        return ResellerWhiteLabel::query()->where('user_id', $ownerId)->first();
    }

    /**
     * Giriş sayfası vb. için: host, ?wl= slug veya ?white_label= ile eşleşen bayi profili.
     */
    public function resolveFromRequest(Request $request): ?ResellerWhiteLabel
    {
        if (! Schema::hasTable('reseller_white_labels')) {
            return null;
        }

        $slug = $request->query('wl') ?? $request->query('white_label');
        if (is_string($slug) && trim($slug) !== '') {
            $slug = strtolower(trim($slug));
            $row = ResellerWhiteLabel::query()->where('slug', $slug)->first();
            if ($row) {
                return $row;
            }
        }

        $host = strtolower((string) $request->getHost());
        if ($host !== '') {
            $row = ResellerWhiteLabel::query()
                ->whereNotNull('hostname')
                ->get()
                ->first(function (ResellerWhiteLabel $wl) use ($host): bool {
                    return $this->normalizeHost((string) $wl->hostname) === $host;
                });
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Public /branding yanıtı: global panel ayarları + isteğe bağlı WL üzerine yazar.
     *
     * @return array<string, mixed>
     */
    public function publicPayload(Request $request): array
    {
        $base = $this->globalBrandingPayload();
        $wl = $this->resolveFromRequest($request);
        if ($wl === null) {
            return $base;
        }

        $ownerId = (int) $wl->user_id;
        $c = $this->normalizeWlLogoUrl($ownerId, $wl->logo_customer_basename);
        $a = $this->normalizeWlLogoUrl($ownerId, $wl->logo_admin_basename);

        return array_merge($base, array_filter([
            'logo_customer_url' => $c ?? $base['logo_customer_url'],
            'logo_admin_url' => $a ?? $base['logo_admin_url'],
            'primary_color' => $wl->primary_color,
            'secondary_color' => $wl->secondary_color,
            'login_title' => $wl->login_title,
            'login_subtitle' => $wl->login_subtitle,
            'white_label_slug' => $wl->slug,
        ], static fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * Oturum açıkken panel içi tema (CSS değişkenleri) için JSON.
     *
     * @return array<string, mixed>|null
     */
    public function uiPayloadForUser(?User $user): ?array
    {
        $wl = $this->profileForUser($user);
        if ($wl === null) {
            return null;
        }
        $ownerId = (int) $wl->user_id;

        return [
            'primary_color' => $wl->primary_color,
            'secondary_color' => $wl->secondary_color,
            'logo_customer_url' => $this->normalizeWlLogoUrl($ownerId, $wl->logo_customer_basename),
            'logo_admin_url' => $this->normalizeWlLogoUrl($ownerId, $wl->logo_admin_basename),
            'login_title' => $wl->login_title,
            'login_subtitle' => $wl->login_subtitle,
            'mail_footer_plain' => $wl->mail_footer_plain,
            'onboarding_html' => $wl->onboarding_html,
        ];
    }

    public function appendMailFooter(?User $recipient, string $body): string
    {
        $wl = $this->profileForUser($recipient);
        $footer = $wl?->mail_footer_plain;
        if (! is_string($footer) || trim($footer) === '') {
            return $body;
        }

        return rtrim($body)."\n\n---\n".trim($footer);
    }

    public function publicLogoCustomerUrl(?ResellerWhiteLabel $wl): ?string
    {
        if ($wl === null || ! $wl->exists) {
            return null;
        }

        return $this->normalizeWlLogoUrl((int) $wl->user_id, $wl->logo_customer_basename);
    }

    public function publicLogoAdminUrl(?ResellerWhiteLabel $wl): ?string
    {
        if ($wl === null || ! $wl->exists) {
            return null;
        }

        return $this->normalizeWlLogoUrl((int) $wl->user_id, $wl->logo_admin_basename);
    }

    /**
     * @return array{logo_customer_url: ?string, logo_admin_url: ?string}
     */
    private function globalBrandingPayload(): array
    {
        if (! Schema::hasTable('panel_settings')) {
            return [
                'logo_customer_url' => null,
                'logo_admin_url' => null,
            ];
        }

        $c = PanelSetting::query()->where('key', 'branding.logo_customer_url')->value('value');
        $a = PanelSetting::query()->where('key', 'branding.logo_admin_url')->value('value');

        return [
            'logo_customer_url' => $this->normalizeGlobalLogoUrl($c),
            'logo_admin_url' => $this->normalizeGlobalLogoUrl($a),
        ];
    }

    private function normalizeGlobalLogoUrl(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $basename = $this->extractBrandingBasename(trim($value));
        if ($basename === null || ! preg_match('/^[A-Za-z0-9._-]+$/', $basename)) {
            return null;
        }

        try {
            if (Storage::disk('public')->exists('branding/'.$basename)) {
                return 'api/branding/files/'.$basename;
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function normalizeWlLogoUrl(int $ownerUserId, mixed $basename): ?string
    {
        if (! is_string($basename) || trim($basename) === '') {
            return null;
        }
        $b = trim($basename);
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $b)) {
            return null;
        }
        $rel = 'branding/wl/'.$ownerUserId.'/'.$b;
        try {
            if (Storage::disk('public')->exists($rel)) {
                return 'api/branding/wl/'.$ownerUserId.'/'.$b;
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function extractBrandingBasename(string $value): ?string
    {
        if (preg_match('#^/?api/branding/files/([A-Za-z0-9._-]+)$#', $value, $m)) {
            return $m[1];
        }
        if (preg_match('#/(?:storage/branding|api/branding/files)/([A-Za-z0-9._-]+)(?:[?\#]|$)#', $value, $m)) {
            return $m[1];
        }
        if (preg_match('#^https?://#i', $value)) {
            $path = (string) (parse_url($value, PHP_URL_PATH) ?? '');

            return $this->extractBrandingBasename($path);
        }

        return null;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }
        // IPv6 bracket [::1] — getHost usually without brackets
        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $parts = explode(':', $host);
            if (count($parts) > 2) {
                return $host;
            }
            // host:port
            return $parts[0] ?? $host;
        }

        return $host;
    }
}
