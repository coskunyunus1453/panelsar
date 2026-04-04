<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\DocPage;
use App\Models\Plan;
use App\Models\SitePage;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        SitePage::query()->updateOrCreate(
            ['locale' => 'tr', 'slug' => 'setup'],
            [
                'title' => 'Kurulum rehberi',
                'meta_description' => 'Hostvim panelini sunucunuza kurmak için adım adım rehber.',
                'is_published' => true,
                'sort_order' => 10,
                'content' => <<<'MD'
## Genel bakış

Hostvim, Linux sunucunuzda **Nginx**, **PHP-FPM** ve veritabanlarını tek panelden yönetmenizi sağlar. Kurulum iki ana bileşenden oluşur:

1. **Hostvim Engine** — sunucu tarafı servisler ve yapılandırma
2. **Panel** — web arayüzü ve API

## Ön koşullar

- Temiz bir **Ubuntu 22.04 LTS** veya üretim ekibinizin desteklediği bir dağıtım
- **root** veya `sudo` yetkisi
- Alan adınızın DNS kayıtlarının sunucuya işaret etmesi (SSL için)

## Tek satır kurulum (örnek)

Sunucuda aşağıdaki komut, bootstrap betiğini indirip çalıştırır:

```bash
curl -fsSL https://get.hostvim.sh | bash
```

> Üretim ortamında betiği çalıştırmadan önce resmi dokümantasyondaki checksum ve imza doğrulamasını uygulayın.

## Kurulum sonrası

- Panel URL’nizi tarayıcıda açın ve ilk yönetici hesabını oluşturun.
- Engine ile panel arasındaki API anahtarlarını `.env` üzerinden eşleştirin.
- İlk site oluşturma sihirbazı ile bir **test domain** üzerinde doğrulama yapın.

Sorularınız için [dokümantasyon](/docs) ve [blog](/blog) sayfalarına göz atın.
MD
            ]
        );

        SitePage::query()->updateOrCreate(
            ['locale' => 'en', 'slug' => 'setup'],
            [
                'title' => 'Installation guide',
                'meta_description' => 'Step-by-step guide to installing the Hostvim panel on your server.',
                'is_published' => true,
                'sort_order' => 10,
                'content' => <<<'MD'
## Overview

Hostvim lets you manage **Nginx**, **PHP-FPM**, and databases from one panel on your Linux server. Installation has two main parts:

1. **Hostvim Engine** — server-side services and configuration
2. **Panel** — web UI and API

## Prerequisites

- A clean **Ubuntu 22.04 LTS** or a distribution your operations team supports
- **root** or `sudo`
- DNS pointing your domain at the server (for SSL)

## One-line install (example)

On the server, the following downloads and runs the bootstrap script:

```bash
curl -fsSL https://get.hostvim.sh | bash
```

> In production, verify checksums and signatures from the official documentation before running the script.

## After install

- Open the panel URL and create the first admin account.
- Match API keys between Engine and panel in `.env`.
- Use the site wizard on a **test domain** to validate the stack.

See [documentation](/docs) and the [blog](/blog) for more.
MD
            ]
        );

        SitePage::query()->updateOrCreate(
            ['locale' => 'tr', 'slug' => 'pricing'],
            [
                'title' => 'Fiyatlandırma özeti',
                'meta_description' => 'Hostvim freemium ve lisanslı planlar hakkında kısa özet.',
                'is_published' => true,
                'sort_order' => 20,
                'content' => <<<'MD'
Bu sayfa, **fiyatlandırma** ekranındaki giriş metnini besler. Plan kartları veritabanındaki kayıtlardan otomatik oluşturulur.

- **Freemium**: tek sunucu ve temel özelliklerle başlayın.
- **Pro**: ajans ve yüksek trafik senaryoları için genişletilmiş limitler.
- **Vendor**: white-label ve kurumsal SLA için bizimle iletişime geçin.

Detaylı limit tabloları panel içi lisans ekranında güncellenir.
MD
            ]
        );

        SitePage::query()->updateOrCreate(
            ['locale' => 'en', 'slug' => 'pricing'],
            [
                'title' => 'Pricing overview',
                'meta_description' => 'Short overview of Hostvim freemium and licensed plans.',
                'is_published' => true,
                'sort_order' => 20,
                'content' => <<<'MD'
This page feeds the intro text on the **pricing** screen. Plan cards are built from database records.

- **Freemium**: start with one server and core features.
- **Pro**: higher limits for agencies and busy workloads.
- **Vendor**: white-label and enterprise SLA — contact us.

Detailed limits are maintained in the in-panel licensing screen.
MD
            ]
        );

        $rootTr = DocPage::query()->updateOrCreate(
            ['locale' => 'tr', 'slug' => 'getting-started'],
            [
                'parent_id' => null,
                'title' => 'Başlangıç',
                'is_published' => true,
                'sort_order' => 0,
                'content' => <<<'MD'
# Hostvim dokümantasyonu

Bu bölümde kurulum, mimari ve tipik kullanım senaryolarına dair rehberler bulunur.

Sol menüden alt başlıklara geçebilir veya doğrudan ilgili sayfaların bağlantılarını kullanabilirsiniz.
MD
            ]
        );

        $rootEn = DocPage::query()->updateOrCreate(
            ['locale' => 'en', 'slug' => 'getting-started'],
            [
                'parent_id' => null,
                'title' => 'Getting started',
                'is_published' => true,
                'sort_order' => 0,
                'content' => <<<'MD'
# Hostvim documentation

Here you will find guides for installation, architecture, and common workflows.

Use the sidebar for nested pages or follow direct links from the docs home.
MD
            ]
        );

        DocPage::query()->updateOrCreate(
            ['locale' => 'tr', 'slug' => 'server-setup'],
            [
                'parent_id' => $rootTr->id,
                'title' => 'Sunucu kurulumu',
                'is_published' => true,
                'sort_order' => 10,
                'content' => <<<'MD'
## Adımlar

1. Sunucuda güncellemeleri alın: `apt update && apt upgrade -y`
2. Hostvim bootstrap betiğini çalıştırın (resmi komut dokümantasyonda).
3. Firewall’da **80**, **443** ve panel için kullanılan portu açın.
4. İlk girişte yönetici e-postası ve güçlü bir şifre belirleyin.

## Engine ve panel

Engine sistem servislerini yönetir; panel Laravel tabanlı arayüzdür. İkisi arasında TLS ve API anahtarları ile güvenli iletişim kurulur.
MD
            ]
        );

        DocPage::query()->updateOrCreate(
            ['locale' => 'en', 'slug' => 'server-setup'],
            [
                'parent_id' => $rootEn->id,
                'title' => 'Server setup',
                'is_published' => true,
                'sort_order' => 10,
                'content' => <<<'MD'
## Steps

1. Update the server: `apt update && apt upgrade -y`
2. Run the Hostvim bootstrap script (official command is in the docs).
3. Open **80**, **443**, and the panel port in your firewall.
4. On first login, set admin email and a strong password.

## Engine and panel

The Engine manages system services; the panel is a Laravel-based UI. They communicate over TLS using API keys.
MD
            ]
        );

        DocPage::query()->updateOrCreate(
            ['locale' => 'tr', 'slug' => 'architecture'],
            [
                'parent_id' => null,
                'title' => 'Mimari genel bakış',
                'is_published' => true,
                'sort_order' => 5,
                'content' => <<<'MD'
## Bileşenler

- **Engine (Go)**: konteyner veya sistem servisi olarak çalışır; Nginx sanal host, PHP-FPM havuzu ve sertifika işlemlerini uygular.
- **Panel (Laravel)**: kullanıcı, site ve lisans yönetimi; Engine ile REST/WebSocket üzerinden konuşur.
- **Veritabanları**: MySQL/MariaDB veya PostgreSQL; panel üzerinden kullanıcı bazlı yetkilendirme.

Bu yapı sayesinde paneli güncellerken engine sürümünü bağımsız tutabilirsiniz.
MD
            ]
        );

        DocPage::query()->updateOrCreate(
            ['locale' => 'en', 'slug' => 'architecture'],
            [
                'parent_id' => null,
                'title' => 'Architecture overview',
                'is_published' => true,
                'sort_order' => 5,
                'content' => <<<'MD'
## Components

- **Engine (Go)**: runs as a container or system service; applies Nginx vhosts, PHP-FPM pools, and certificates.
- **Panel (Laravel)**: users, sites, and licensing; talks to the Engine over REST/WebSocket.
- **Databases**: MySQL/MariaDB or PostgreSQL with per-user authorization from the panel.

You can upgrade the panel and Engine on independent cadences.
MD
            ]
        );

        $catHostingTr = BlogCategory::query()->updateOrCreate(
            ['locale' => 'tr', 'slug' => 'hosting-migration'],
            [
                'name' => 'Hosting ve geçiş',
                'meta_title' => 'Hosting ve geçiş — Hostvim blog',
                'meta_description' => 'Paylaşımlı hostingden çıkış, sunucu taşıma ve panel geçişi üzerine yazılar.',
                'sort_order' => 10,
            ]
        );

        $catHostingEn = BlogCategory::query()->updateOrCreate(
            ['locale' => 'en', 'slug' => 'hosting-migration'],
            [
                'name' => 'Hosting & migration',
                'meta_title' => 'Hosting & migration — Hostvim blog',
                'meta_description' => 'Moving off shared hosting, server migrations, and panel transitions.',
                'sort_order' => 10,
            ]
        );

        $catSecurityTr = BlogCategory::query()->updateOrCreate(
            ['locale' => 'tr', 'slug' => 'security'],
            [
                'name' => 'Güvenlik',
                'meta_title' => 'Güvenlik — Hostvim blog',
                'meta_description' => 'Panel ve sunucu güvenliği, erişim ve sertifika konuları.',
                'sort_order' => 20,
            ]
        );

        $catSecurityEn = BlogCategory::query()->updateOrCreate(
            ['locale' => 'en', 'slug' => 'security'],
            [
                'name' => 'Security',
                'meta_title' => 'Security — Hostvim blog',
                'meta_description' => 'Panel and server security, access control, and certificates.',
                'sort_order' => 20,
            ]
        );

        $catScaleTr = BlogCategory::query()->updateOrCreate(
            ['locale' => 'tr', 'slug' => 'scaling'],
            [
                'name' => 'Ölçeklendirme',
                'meta_title' => 'Ölçeklendirme ve mimari — Hostvim blog',
                'meta_description' => 'Tek sunucudan çoklu düzene geçiş ve mimari notları.',
                'sort_order' => 30,
            ]
        );

        $catScaleEn = BlogCategory::query()->updateOrCreate(
            ['locale' => 'en', 'slug' => 'scaling'],
            [
                'name' => 'Scaling',
                'meta_title' => 'Scaling & architecture — Hostvim blog',
                'meta_description' => 'Growing from one server to multi-node setups.',
                'sort_order' => 30,
            ]
        );

        BlogPost::query()->updateOrCreate(
            ['locale' => 'tr', 'slug' => 'from-shared-hosting'],
            [
                'blog_category_id' => $catHostingTr->id,
                'title' => 'Shared hosting’den kendi panelime',
                'excerpt' => 'Klasik paylaşımlı hostingden çıkıp kendi sunucunuzda Hostvim ile nasıl ilerlersiniz?',
                'is_published' => true,
                'published_at' => now()->subDays(5),
                'content' => <<<'MD'
Paylaşımlı hosting uzun yıllar işinizi görür; ta ki tek panelden onlarca siteyi yönetme ihtiyacı doğana kadar.

## Geçiş stratejisi

1. **DNS TTL** düşürün; taşıma günü kesintiyi azaltır.
2. Veritabanını **mysqldump** veya panel araçlarıyla alın.
3. Dosyaları **rsync** ile senkronize edin.
4. Hostvim’de site sihirbazını çalıştırıp SSL’i doğrulayın.

Küçük projelerde önce staging subdomain ile test etmek riski ciddi şekilde azaltır.
MD
            ]
        );

        BlogPost::query()->updateOrCreate(
            ['locale' => 'en', 'slug' => 'from-shared-hosting'],
            [
                'blog_category_id' => $catHostingEn->id,
                'title' => 'From shared hosting to your own panel',
                'excerpt' => 'How to move from classic shared hosting to Hostvim on your own server.',
                'is_published' => true,
                'published_at' => now()->subDays(5),
                'content' => <<<'MD'
Shared hosting works for years — until you need to run many sites from one panel.

## Migration strategy

1. Lower **DNS TTL** to reduce cutover pain.
2. Export the database with **mysqldump** or your tools.
3. Sync files with **rsync**.
4. Run the Hostvim site wizard and verify TLS.

For smaller projects, test on a staging subdomain first.
MD
            ]
        );

        BlogPost::query()->updateOrCreate(
            ['locale' => 'tr', 'slug' => 'panel-security-basics'],
            [
                'blog_category_id' => $catSecurityTr->id,
                'title' => 'Panel güvenliğinde temel hatalar',
                'excerpt' => 'Yönetim arayüzünü internete açarken sık yapılan hatalar ve pratik önlemler.',
                'is_published' => true,
                'published_at' => now()->subDays(3),
                'content' => <<<'MD'
Panel URL’sini herkese açık bırakmak yerine:

- **İki faktörlü doğrulama** kullanın
- Yönetim yolunu **rate limit** ile koruyun
- Varsayılan portları değiştirin veya **VPN** arkasına alın

Hostvim yönetim hesapları için güçlü şifre politikası ve oturum süresi sınırları önerilir.
MD
            ]
        );

        BlogPost::query()->updateOrCreate(
            ['locale' => 'en', 'slug' => 'panel-security-basics'],
            [
                'blog_category_id' => $catSecurityEn->id,
                'title' => 'Common panel security mistakes',
                'excerpt' => 'Typical pitfalls when exposing an admin UI to the internet — and practical fixes.',
                'is_published' => true,
                'published_at' => now()->subDays(3),
                'content' => <<<'MD'
Before leaving the panel URL wide open:

- Enable **two-factor authentication**
- Protect admin routes with **rate limiting**
- Change default ports or place the panel behind a **VPN**

Strong password policy and session limits are recommended for Hostvim admin accounts.
MD
            ]
        );

        BlogPost::query()->updateOrCreate(
            ['locale' => 'tr', 'slug' => 'single-server-to-cluster'],
            [
                'blog_category_id' => $catScaleTr->id,
                'title' => 'Tek sunucudan çoklu cluster’a',
                'excerpt' => 'Büyüdükçe mimariyi nasıl parçalayabilirsiniz?',
                'is_published' => true,
                'published_at' => now()->subDay(),
                'content' => <<<'MD'
İlk aşamada tek sunucu yeterlidir. Trafik ve ekip büyüdükçe:

- Veritabanını ayrı bir **DB host**’a taşıyın
- Statik ve medya için **CDN** ekleyin
- Engine örneklerini **load balancer** arkasında çoğaltın

Hostvim bu aşamalarda aynı panel üzerinden çoklu sunucu yönetimini hedefler; roadmap’i ürün duyurularından takip edin.
MD
            ]
        );

        BlogPost::query()->updateOrCreate(
            ['locale' => 'en', 'slug' => 'single-server-to-cluster'],
            [
                'blog_category_id' => $catScaleEn->id,
                'title' => 'From one server to a multi-node setup',
                'excerpt' => 'How to split the architecture as you grow.',
                'is_published' => true,
                'published_at' => now()->subDay(),
                'content' => <<<'MD'
A single server is enough at first. As traffic and teams grow:

- Move the database to a dedicated **DB host**
- Add a **CDN** for static assets and media
- Run multiple Engine instances behind a **load balancer**

Hostvim aims to manage multiple servers from the same panel over time — follow product announcements for the roadmap.
MD
            ]
        );

        Plan::query()->updateOrCreate(
            ['slug' => 'freemium'],
            [
                'name' => 'Freemium',
                'subtitle' => 'Tek sunucu için sınırlı ama yeterli özellikler',
                'price_label' => '₺0',
                'price_note' => '/ay',
                'sort_order' => 10,
                'is_featured' => false,
                'is_active' => true,
                'features' => [
                    '1 sunucu',
                    'Temel site ve domain yönetimi',
                    'Otomatik SSL (Let\'s Encrypt)',
                    'Sınırlı log ve terminal erişimi',
                ],
            ]
        );

        Plan::query()->updateOrCreate(
            ['slug' => 'pro-lisans'],
            [
                'name' => 'Pro Lisans',
                'subtitle' => 'Ajanslar ve yoğun trafik için',
                'price_label' => '₺?',
                'price_note' => '/ay · sunucu başına',
                'sort_order' => 20,
                'is_featured' => true,
                'is_active' => true,
                'features' => [
                    'Sınırsız site ve domain',
                    'Gelişmiş güvenlik profilleri',
                    'Detaylı metrikler ve health checks',
                    'Öncelikli destek',
                ],
            ]
        );

        Plan::query()->updateOrCreate(
            ['slug' => 'vendor'],
            [
                'name' => 'Vendor / White-label',
                'subtitle' => 'Kendi markanızla sunmak isteyen paneller için',
                'price_label' => 'Özel',
                'price_note' => 'teklif',
                'sort_order' => 30,
                'is_featured' => false,
                'is_active' => true,
                'features' => [
                    'Özel fiyatlandırma ve SLA',
                    'Marka özelleştirme',
                    'Roadmap iş birliği',
                ],
            ]
        );
    }
}
