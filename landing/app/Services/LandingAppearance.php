<?php

namespace App\Services;

use App\Models\LandingSiteSetting;
use Illuminate\Support\Facades\Storage;

final class LandingAppearance
{
    public const DEFAULT_FEATURE_CARDS = [
        ['title' => 'Site & domain yönetimi', 'body' => 'Nginx sanal host, SSL, yönlendirmeler ve PHP versiyonlarını tek ekrandan kontrol edin.', 'icon' => 'globe'],
        ['title' => 'Veritabanı & kullanıcılar', 'body' => 'MySQL / Postgres veritabanlarını, kullanıcı ve izinleri panelden yönetin.', 'icon' => 'database'],
        ['title' => 'Güvenlik & SSL', 'body' => 'Otomatik Let’s Encrypt, güvenlik profilleri ve temel hardening ayarları.', 'icon' => 'shield'],
        ['title' => 'Terminal & loglar', 'body' => 'Güvenli web terminali, gerçek zamanlı log izleme ve hızlı hata ayıklama.', 'icon' => 'terminal'],
        ['title' => 'Rol & yetkilendirme', 'body' => 'Bayi, admin ve son kullanıcı rollerini ayrıştırarak güvenli erişim modeli kurun.', 'icon' => 'users'],
        ['title' => 'Hazır stack profilleri', 'body' => 'WordPress, Laravel ve klasik PHP projeleri için ön tanımlı stack profilleri.', 'icon' => 'layers'],
    ];

    /** @var array<string, string> */
    public const NEON_DEFAULT_TOP = [
        'badge' => 'Neon · Material',
        'title' => 'Altyapınızı tek panelden yönetin',
        'lead' => 'Sade arayüz, güçlü otomasyon. Gece modu ile göz yormayan neon vurgular.',
        'cta_primary' => 'Panele git',
        'cta_secondary' => 'Kurulum rehberi',
    ];

    /** @var array{title: string, lead: string} */
    public const NEON_DEFAULT_STACK_SECTION = [
        'title' => 'Öne çıkan özellikler',
        'lead' => 'Operasyon ve geliştirme ekipleri için beş temel yetenek.',
    ];

    /** @var list<array{title: string, body: string, icon: string}> */
    public const NEON_DEFAULT_STACK_ITEMS = [
        ['title' => 'Çoklu site & domain', 'body' => 'Nginx sanal host, SSL ve yönlendirmeleri merkezi yönetin.', 'icon' => 'globe'],
        ['title' => 'Veritabanları', 'body' => 'MySQL ve Postgres için kullanıcı, yetki ve yedek akışları.', 'icon' => 'database'],
        ['title' => 'Güvenlik katmanı', 'body' => 'Profiller, sertifikalar ve temel sertleştirme seçenekleri.', 'icon' => 'shield'],
        ['title' => 'Terminal & loglar', 'body' => 'Tarayıcı terminali ve canlı log izleme ile hızlı müdahale.', 'icon' => 'terminal'],
        ['title' => 'Roller & bayi modeli', 'body' => 'Admin, bayi ve son kullanıcı için ayrıştırılmış yetkiler.', 'icon' => 'users'],
    ];

    /** @var array{title: string, lead: string} */
    public const NEON_DEFAULT_GRID_SECTION = [
        'title' => 'Daha fazla yetenek',
        'lead' => 'Genişletilebilir mimari ile büyüyen ihtiyaçlara altı ek başlık.',
    ];

    /** @var list<array{title: string, body: string, icon: string}> */
    public const NEON_DEFAULT_GRID_ITEMS = [
        ['title' => 'Stack profilleri', 'body' => 'WordPress, Laravel ve statik siteler için hazır profiller.', 'icon' => 'layers'],
        ['title' => 'Performans', 'body' => 'PHP-FPM ve önbellek ayarlarıyla optimize edilmiş istek yolu.', 'icon' => 'cpu'],
        ['title' => 'Hızlı lansman', 'body' => 'Sihirbazlar ve şablonlarla dakikalar içinde yayına çıkın.', 'icon' => 'rocket'],
        ['title' => 'API & otomasyon', 'body' => 'Engine ile panel arasında güvenli API sözleşmesi.', 'icon' => 'terminal'],
        ['title' => 'Yedekleme', 'body' => 'Zamanlanmış yedekler ve dışa aktarma seçenekleri.', 'icon' => 'database'],
        ['title' => 'Çoklu sunucu', 'body' => 'Tek panelden birden fazla host hedefi (roadmap uyumlu).', 'icon' => 'globe'],
    ];

    public static function activeTheme(): string
    {
        $t = LandingSiteSetting::getValue('landing.active_theme', 'orange');

        return in_array($t, ['orange', 'turquoise', 'neon'], true) ? $t : 'orange';
    }

    public static function themeClass(): string
    {
        return match (self::activeTheme()) {
            'turquoise' => 'hv-theme-turquoise',
            'neon' => 'hv-theme-neon',
            default => 'hv-theme-orange',
        };
    }

    public static function isNeonTheme(): bool
    {
        return self::activeTheme() === 'neon';
    }

    public static function graphicMotif(): string
    {
        $m = LandingSiteSetting::getValue('landing.graphic_motif', 'grid');
        $allowed = array_keys(config('landing_theme.graphic_motifs', []));

        return in_array($m, $allowed, true) ? $m : 'grid';
    }

    public static function graphicMotifClass(): string
    {
        if (self::activeTheme() === 'neon') {
            return '';
        }
        $m = self::graphicMotif();

        return $m === 'none' ? '' : 'hv-motif-'.$m;
    }

    /**
     * @return array{badge: string, title: string, lead: string, cta_primary: string, cta_secondary: string}
     */
    public static function neonTop(): array
    {
        $raw = json_decode((string) LandingSiteSetting::getValue('landing.theme_neon_top', '{}'), true);

        return self::mergeNeonAssoc(self::NEON_DEFAULT_TOP, is_array($raw) ? $raw : []);
    }

    /**
     * @return array{title: string, lead: string}
     */
    public static function neonStackSection(): array
    {
        $raw = json_decode((string) LandingSiteSetting::getValue('landing.theme_neon_stack_section', '{}'), true);

        return self::mergeNeonAssoc(self::NEON_DEFAULT_STACK_SECTION, is_array($raw) ? $raw : []);
    }

    /** @return list<array{title: string, body: string, icon: string}> */
    public static function neonStackItems(): array
    {
        return self::mergeNeonItemList(
            self::NEON_DEFAULT_STACK_ITEMS,
            json_decode((string) LandingSiteSetting::getValue('landing.theme_neon_stack', '[]'), true),
            5
        );
    }

    /**
     * @return array{title: string, lead: string}
     */
    public static function neonGridSection(): array
    {
        $raw = json_decode((string) LandingSiteSetting::getValue('landing.theme_neon_grid_section', '{}'), true);

        return self::mergeNeonAssoc(self::NEON_DEFAULT_GRID_SECTION, is_array($raw) ? $raw : []);
    }

    /** @return list<array{title: string, body: string, icon: string}> */
    public static function neonGridItems(): array
    {
        return self::mergeNeonItemList(
            self::NEON_DEFAULT_GRID_ITEMS,
            json_decode((string) LandingSiteSetting::getValue('landing.theme_neon_grid', '[]'), true),
            6
        );
    }

    /**
     * @param  array<string, string>  $defaults
     * @param  array<string, mixed>  $stored
     * @return array<string, string>
     */
    private static function mergeNeonAssoc(array $defaults, array $stored): array
    {
        $out = [];
        foreach ($defaults as $key => $defaultVal) {
            if (array_key_exists($key, $stored)) {
                $out[$key] = is_string($stored[$key]) ? trim($stored[$key]) : (string) $defaultVal;
            } else {
                $out[$key] = $defaultVal;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{title: string, body: string, icon: string}>  $defaults
     * @return list<array{title: string, body: string, icon: string}>
     */
    private static function mergeNeonItemList(array $defaults, mixed $raw, int $count): array
    {
        $allowedIcons = array_keys(config('landing_theme.feature_icons', []));
        $rows = is_array($raw) ? $raw : [];
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $def = $defaults[$i] ?? ['title' => '', 'body' => '', 'icon' => 'layers'];
            $row = is_array($rows[$i] ?? null) ? $rows[$i] : [];
            $icon = isset($row['icon']) && is_string($row['icon']) && in_array($row['icon'], $allowedIcons, true)
                ? $row['icon']
                : $def['icon'];
            $t = array_key_exists('title', $row) && is_string($row['title']) ? trim($row['title']) : $def['title'];
            $b = array_key_exists('body', $row) && is_string($row['body']) ? trim($row['body']) : $def['body'];
            if ($t === '' && $b === '') {
                $out[] = $def;

                continue;
            }
            $out[] = [
                'title' => $t,
                'body' => $b,
                'icon' => $icon,
            ];
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function pageOverrides(): array
    {
        $raw = LandingSiteSetting::getValue('landing.page_overrides', '{}');
        $data = json_decode((string) $raw, true);

        return is_array($data) ? $data : [];
    }

    public static function line(string $translationKey, array $replace = []): string
    {
        $bag = self::pageOverrides();
        if (array_key_exists($translationKey, $bag) && is_string($bag[$translationKey]) && $bag[$translationKey] !== '') {
            $line = $bag[$translationKey];
        } elseif ($translationKey === 'brand.name') {
            $sn = trim((string) (LandingSiteSetting::getValue('landing.site_name', '') ?? ''));
            $line = $sn !== '' ? $sn : app(LandingI18n::class)->line($translationKey, $replace);
        } elseif ($translationKey === 'brand.subtitle') {
            $st = trim((string) (LandingSiteSetting::getValue('landing.site_tagline', '') ?? ''));
            $line = $st !== '' ? $st : app(LandingI18n::class)->line($translationKey, $replace);
        } else {
            $line = app(LandingI18n::class)->line($translationKey, $replace);
        }

        if ($replace !== []) {
            foreach ($replace as $k => $v) {
                $line = str_replace([':'.$k, ':'.strtoupper($k), ':'.ucfirst($k)], [$v, $v, $v], $line);
            }
        }

        return $line;
    }

    /** @return list<array{title: string, body: string, icon: string}> */
    public static function featureCards(): array
    {
        $raw = LandingSiteSetting::getValue('landing.home_feature_cards', '[]');
        $data = json_decode((string) $raw, true);
        if (! is_array($data) || $data === []) {
            return self::DEFAULT_FEATURE_CARDS;
        }
        $allowedIcons = array_keys(config('landing_theme.feature_icons', []));
        $out = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = isset($row['title']) ? (string) $row['title'] : '';
            $body = isset($row['body']) ? (string) $row['body'] : '';
            $icon = isset($row['icon']) ? (string) $row['icon'] : 'layers';
            if ($title === '' && $body === '') {
                continue;
            }
            if (! in_array($icon, $allowedIcons, true)) {
                $icon = 'layers';
            }
            $out[] = ['title' => $title, 'body' => $body, 'icon' => $icon];
        }

        return $out !== [] ? $out : self::DEFAULT_FEATURE_CARDS;
    }

    public static function siteLogoUrl(): ?string
    {
        $path = LandingSiteSetting::getValue('landing.site_logo_path', '');
        if ($path === null || $path === '') {
            return null;
        }
        $path = ltrim((string) $path, '/');
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return self::publicDiskBrowserUrl($path);
    }

    /** Üst menü / klasik + neon header (px). */
    public static function siteLogoHeaderMaxHeightPx(): int
    {
        $raw = trim((string) (LandingSiteSetting::getValue('landing.site_logo_max_height_px', '') ?? ''));
        if ($raw === '') {
            return 44;
        }
        $v = (int) $raw;

        return max(20, min(200, $v));
    }

    /** Üst menü maks. genişlik; null = yalnızca yükseklik + %100 taşma önlemi. */
    public static function siteLogoHeaderMaxWidthPx(): ?int
    {
        $raw = trim((string) (LandingSiteSetting::getValue('landing.site_logo_max_width_px', '') ?? ''));
        if ($raw === '' || $raw === '0') {
            return null;
        }
        $v = (int) $raw;

        return max(40, min(600, $v));
    }

    /** Neon / klasik altbilgi logosu yüksekliği (px). */
    public static function siteLogoFooterMaxHeightPx(): int
    {
        $raw = trim((string) (LandingSiteSetting::getValue('landing.site_logo_footer_max_height_px', '') ?? ''));
        if ($raw === '') {
            $h = self::siteLogoHeaderMaxHeightPx();

            return max(24, min(80, (int) round($h * 0.73)));
        }
        $v = (int) $raw;

        return max(16, min(120, $v));
    }

    /** Altbilgi maks. genişlik; null = üstteki orana göre veya %100. */
    public static function siteLogoFooterMaxWidthPx(): ?int
    {
        $raw = trim((string) (LandingSiteSetting::getValue('landing.site_logo_footer_max_width_px', '') ?? ''));
        if ($raw === '' || $raw === '0') {
            $w = self::siteLogoHeaderMaxWidthPx();
            if ($w === null) {
                return null;
            }

            return max(40, min(400, (int) round($w * 0.85)));
        }
        $v = (int) $raw;

        return max(40, min(600, $v));
    }

    /** @param  'header'|'footer'  $context */
    public static function siteLogoImgStyle(string $context): string
    {
        $h = $context === 'footer' ? self::siteLogoFooterMaxHeightPx() : self::siteLogoHeaderMaxHeightPx();
        $parts = [
            'max-height: '.$h.'px',
            'height: auto',
            'width: auto',
            'object-fit: contain',
            'object-position: left center',
        ];
        $w = $context === 'footer' ? self::siteLogoFooterMaxWidthPx() : self::siteLogoHeaderMaxWidthPx();
        if ($w !== null) {
            $parts[] = 'max-width: '.$w.'px';
        } else {
            $parts[] = 'max-width: 100%';
        }

        return implode('; ', $parts);
    }

    public static function faviconUrl(): ?string
    {
        $path = LandingSiteSetting::getValue('landing.favicon_path', '');
        if ($path === null || $path === '') {
            return null;
        }
        $path = ltrim((string) $path, '/');
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return self::publicDiskBrowserUrl($path);
    }

    public static function faviconMimeType(): ?string
    {
        $path = LandingSiteSetting::getValue('landing.favicon_path', '');
        if ($path === null || $path === '') {
            return null;
        }
        $path = ltrim((string) $path, '/');
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };
    }

    public static function contactEmail(): ?string
    {
        $e = trim((string) (LandingSiteSetting::getValue('landing.contact_email', '') ?? ''));

        return $e !== '' ? $e : null;
    }

    public static function socialTwitterUrl(): ?string
    {
        return self::trimmedUrlSetting('landing.social_twitter_url');
    }

    public static function socialGithubUrl(): ?string
    {
        return self::trimmedUrlSetting('landing.social_github_url');
    }

    public static function socialLinkedinUrl(): ?string
    {
        return self::trimmedUrlSetting('landing.social_linkedin_url');
    }

    public static function analyticsMeasurementId(): ?string
    {
        $id = trim((string) (LandingSiteSetting::getValue('landing.analytics_ga4_id', '') ?? ''));
        if ($id === '' || ! preg_match('/^G-[A-Z0-9]+$/', $id)) {
            return null;
        }

        return $id;
    }

    public static function footerExtraNote(): ?string
    {
        $t = trim((string) (LandingSiteSetting::getValue('landing.footer_extra_note', '') ?? ''));

        return $t !== '' ? $t : null;
    }

    private static function trimmedUrlSetting(string $key): ?string
    {
        $u = trim((string) (LandingSiteSetting::getValue($key, '') ?? ''));
        if ($u === '') {
            return null;
        }
        if (filter_var($u, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $u;
    }

    public static function heroImageUrl(): ?string
    {
        $path = LandingSiteSetting::getValue('landing.hero_image_path', '');
        if ($path === null || $path === '') {
            return null;
        }
        $path = ltrim((string) $path, '/');
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return self::publicDiskBrowserUrl($path);
    }

    /**
     * Public disk dosyası için tarayıcı adresi. APP_URL kök dizinle uyuşmazsa (XAMPP alt klasör vb.)
     * Storage::url() yanlış host yolu üretip 404 verebilir; HTTP isteğinde gerçek base path kullanılır.
     */
    public static function publicDiskBrowserUrl(string $pathOnPublicDisk): string
    {
        $pathOnPublicDisk = ltrim($pathOnPublicDisk, '/');

        if (app()->runningInConsole()) {
            return Storage::disk('public')->url($pathOnPublicDisk);
        }

        return url('storage/'.$pathOnPublicDisk);
    }

    public static function heroImageAlt(): string
    {
        return (string) LandingSiteSetting::getValue('landing.hero_image_alt', '');
    }

    public static function heroImageCaption(): string
    {
        return (string) LandingSiteSetting::getValue('landing.hero_image_caption', '');
    }

    /** Inline --hv-brand-* RGB triplets (space-separated 0–255). */
    public static function themeInlineStyle(): string
    {
        $hex = LandingSiteSetting::getValue('landing.theme_primary_hex', '');
        $hex = is_string($hex) ? trim($hex) : '';
        if ($hex === '' || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
            return '';
        }

        $base = self::hexToRgbTriplet($hex);
        if ($base === null) {
            return '';
        }
        [$r, $g, $b] = $base;
        $lighter = self::mixRgb($r, $g, $b, 255, 255, 255, 0.35);
        $darker = self::mixRgb($r, $g, $b, 0, 0, 0, 0.22);
        $muted = self::mixRgb($r, $g, $b, 255, 255, 255, 0.65);
        $deep = self::mixRgb($r, $g, $b, 0, 0, 0, 0.35);

        $parts = [
            '--hv-brand-300: '.$muted,
            '--hv-brand-400: '.$lighter,
            '--hv-brand-500: '.$r.' '.$g.' '.$b,
            '--hv-brand-600: '.$darker,
            '--hv-brand-700: '.$deep,
            '--hv-brand-800: '.$deep,
            '--hv-blob: '.$lighter,
        ];

        return implode('; ', $parts);
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function hexToRgbTriplet(string $hex): ?array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return null;
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return [$r, $g, $b];
    }

    private static function mixRgb(int $r, int $g, int $b, int $r2, int $g2, int $b2, float $t): string
    {
        $r3 = (int) round($r + ($r2 - $r) * $t);
        $g3 = (int) round($g + ($g2 - $g) * $t);
        $b3 = (int) round($b + ($b2 - $b) * $t);

        return $r3.' '.$g3.' '.$b3;
    }
}
