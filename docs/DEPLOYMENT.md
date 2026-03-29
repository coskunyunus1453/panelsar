# Panelsar — üretim dağıtımı

Yerel geliştirme doğrulamaları için bkz. [LOCAL_DEVELOPMENT.md](./LOCAL_DEVELOPMENT.md).

Bu belge, **panel (Laravel)** + **engine (Go)** + **ön yüz (Vite)** ile paylaşımlı barındırmaya yakın bir kurulum için minimum gereksinimleri özetler. Plesk/cPanel ile **özellik eşliği** hedefi uzun vadelidir; motor tarafında gerçek servisler (Postfix, BIND, gerçek yedek depolama vb.) ayrıca işletim sistemi entegrasyonu gerektirir.

Yönetici arayüzünde **Sunucu & kurulum** (`/admin/server-setup`) sayfası; engine sağlığı, MySQL/PostgreSQL provizyon bayrakları, WordPress kurulum önkoşulları ve e-posta modunun özeti için salt okunur API kullanır (`GET /api/admin/server/capabilities`). Sistem servisleri için **Sistem** sayfasına geçin.

## Altyapı otomasyonu ve “veriyi ne zaman çekiyorum?”

Repoda **`deploy/`** klasörü:

- `deploy/README.md` — kod, `.env`, migrate ve müşteri verisi akışının özeti (Türkçe).
- `deploy/scripts/deploy-panel.sh` — tek sunucuda `composer`, `migrate`, `config:cache` sırası.
- `deploy/ansible/` — örnek playbook + `env.j2` şablonu (sırlar **Ansible Vault** ile).

**Özet:** Deploy anında genelde **kod** (`git pull` veya artifact) ve **şema** (`migrate`) gelir; **sırlar** Git’e konmaz, o anda ortama yazılır. Müşteri / barındırma verisi çoğu zaman deploy ile taşınmaz; sunucu değişiminde ayrıca DB + dosya yedeği planlanır.

## Mimari

1. **Panel API** — kullanıcılar, paketler, faturalama, RBAC; `ENGINE_API_URL` üzerinden engine’e gider.
2. **Engine** — site oluşturma, SSL, dosya sistemi, nginx/apache vhost, PHP-FPM, izleme; `paths.web_root` ile `PANELSAR_HOSTING_WEB_ROOT` aynı olmalı.
3. **Frontend** — statik derleme veya Vite; API’ye `APP_URL`/CORS ile bağlanır.

## Sunucu öncesi kontrol

Panel dizininde:

```bash
php artisan migrate --force
php artisan panelsar:install-check --ping
```

- Üretimde `APP_DEBUG=false`, `APP_ENV=production`.
- `ENGINE_INTERNAL_KEY` panel `.env` ile engine yapılandırması aynı olmalı.
- `php artisan panelsar:install-check --ping` engine `/health` için HTTP dener.

## Önemli ortam değişkenleri (panel)

| Değişken | Açıklama |
|----------|----------|
| `ENGINE_API_URL` | Engine taban URL (örn. `http://127.0.0.1:9090`) |
| `ENGINE_INTERNAL_KEY` | Engine internal API anahtarı |
| `PANELSAR_HOSTING_WEB_ROOT` | `data/www` veya üretim web kökü (engine ile aynı) |
| `PANELSAR_LETS_ENCRYPT_EMAIL` | ACME için varsayılan e-posta |
| `MYSQL_PROVISION_ENABLED` | MySQL gerçek provizyon |
| `POSTGRES_PROVISION_ENABLED` | PostgreSQL provizyon |
| `PHPMYADMIN_URL` / `ADMINER_URL` | Veritabanları sayfası harici araç linkleri |
| `LICENSE_KEY` | İsteğe bağlı lisans |

## Paket limitleri (Plesk benzeri çekirdek)

`hosting_packages` tablosundaki `max_*` alanları (≥0) uygulanır; **-1** sınırsız kabul edilir. **Admin** kullanıcıları kotadan muaftır. Paketi olmayan kullanıcılar geriye dönük olarak sınırsız sayılır.

Uygulanan kaynaklar:

- Alan adı, veritabanı, e-posta, FTP, cron sayıları  
- `ssl_enabled` / `backup_enabled` bayrakları  
- `panelsar.backup.max_backups_per_user` ile yedek adedi üst sınırı  

## Yedek geri yükleme

Yeni oluşturulan yedeklerde `engine_backup_id` saklanır. `POST /api/backups/{backup}/restore` yalnızca yedeğin sahibi (veya admin) için çalışır.

## Güvenlik notları

- Engine’i yalnızca panel sunucusundan (veya güvenilen ağdan) erişilebilir yapın.
- Firewall: 80/443 (ve SSH); engine portunu dışarı açmayın.
- Stripe webhook için `STRIPE_WEBHOOK_SECRET` kullanın.

## Derleme

```bash
cd frontend && npm ci && npm run build
```

Çıktıyı panel `public` altına veya ayrı bir CDN/host üzerinden servis edin; `VITE_` değişkenleri derleme anında gömülür.
