# Hostvim — Tam ürün yol haritası

**Hedef:** Plesk + cPanel yeteneklerinin modern bir birleşimi — daha hızlı arayüz, sıkı güvenlik, API-öncelikli mimari, ileride **AI yardımcısı** (teşhis, öneri, doğal dil komutları).

**İlkeler:** Tek gerçek kaynak (engine + panel senkron), çok kiracılı güvenlik, idempotent işlemler, gözlemlenebilirlik, otomatik test.

---

## Faz 0 — Temel (mevcut iskelet) ✅

- [x] Laravel panel + Sanctum + roller (admin / reseller / user)
- [x] Go engine + internal API anahtarı
- [x] Site dizini provizyonu (`data/www/.../public_html`)
- [x] Dosya API (liste/okuma/yazma/yükleme)
- [x] Panel ↔ engine birçok modülde bağlantı (çoğu engine tarafı kısmi/stub)
- [x] React arayüz + yönetici Sistem sayfası

---

## Faz 1 — Gerçek web barındırma (kritik yol)

| # | Görev | Açıklama |
|---|--------|-----------|
| 1.1 | **Nginx sanal host** | Domain eklenince `sites-available` + `sites-enabled` şablonu; PHP-FPM socket; güvenli `server_name` / `root`; silinince kaldırma. *(Bu sürümde uygulanmaya başlandı — `hosting.nginx_manage_vhosts`)* |
| 1.2 | **Apache alternatifi** | `server_type=apache` için vhost şablonu + sites-available / sites-enabled sembolik bağ; `hosting.apache_manage_vhosts` |
| 1.3 | **PHP-FPM havuzları** | Sürüm başına pool, kullanıcı/`open_basedir`, `pm` limitleri, panelden sürüm değişince pool güncelleme *(engine: `php_fpm_manage_pools`, site meta `.hostvim/site.json`, PHP değişiminde eski pool silinir)* |
| 1.4 | **SSL (Let’s Encrypt)** | HTTP-01 webroot (`certbot certonly`); `hosting.manage_ssl` + PEM yolları; panelde hata durumunda `failed` / engine `postChecked` |
| 1.5 | **HTTP→HTTPS yönlendirme** | PEM mevcutken nginx/apache 80→443 301; nginx’de HSTS başlığı |

**Çıktı:** Tek Linux sunucuda panelden eklenen domain gerçekten 80/443 üzerinden servis edilir.

---

## Faz 2 — Veritabanı ve uygulama katmanı

| # | Görev |
|---|--------|
| 2.1 | MySQL/MariaDB provizyon: kullanıcı, şifre rotasyonu, uzaktan erişim politikası (`%` vs `localhost`) — *panel: `grant_host`, `PATCH /databases/{id}`, `POST .../rotate-password`, `MYSQL_ALLOWED_GRANT_HOSTS`* |
| 2.2 | PostgreSQL desteği — *panel: `PostgresProvisioner`, `POSTGRES_PROVISION_*`, şifre rotasyonu + silme; `pdo_pgsql`* |
| 2.3 | WordPress: engine zip indirme + `wp-config.php` + salts API; panel `database_id` + tablo öneki; diğer uygulamalar listede “manuel” |
| 2.4 | Composer/npm — *engine: `manage_site_tools`, izinli `action` listesi, `tools_max_seconds`; API `POST /sites/:domain/tools`; panel `/site-tools`* |

---

## Faz 3 — E-posta (cPanel/Plesk çekirdeği)

| # | Görev |
|---|--------|
| 3.1 | Postfix + Dovecot (veya harici SaaS entegrasyonu) ile gerçek kutu oluşturma |
| 3.2 | Kota, alias, forwarder, autoresponder |
| 3.3 | SPF, DKIM, DMARC kayıt önerileri + DNS API ile otomasyon |
| 3.4 | Spam/Rspamd entegrasyonu |

---

## Faz 4 — DNS ve ağ

| # | Görev |
|---|--------|
| 4.1 | Yetkili DNS: BIND / PowerDNS / veya Cloudflare API — bölge dosyası / API çağrıları |
| 4.2 | Alt alan adı, wildcard, TTL, çoklu NS |
| 4.3 | Ters DNS / PTR (isteğe bağlı, genelde sunucu sağlayıcı API) |

---

## Faz 5 — FTP / SFTP / SSH

| # | Görev |
|---|--------|
| 5.1 | Sistem kullanıcısı veya sanal kullanıcı + chroot ile `public_html` |
| 5.2 | SFTP-only hesaplar (OpenSSH `Match User`) |
| 5.3 | Anahtar yönetimi (isteğe bağlı) |

---

## Faz 6 — Yedekleme, güncelleme, felaket

| # | Görev |
|---|--------|
| 6.1 | Tam / dosya / DB yedekleri; zamanlama; uzak S3/Backblaze/Wasabi |
| 6.2 | Geri yükleme sihirbazı + doğrulama |
| 6.3 | Panel + engine sürüm yükseltme (migration + rollback planı) |

---

## Faz 7 — Güvenlik ve uyumluluk (Plesk’ten “daha sıkı” hedef)

| # | Görev |
|---|--------|
| 7.1 | Kiracı izolasyonu: ayrı sistem kullanıcıları veya container (uzun vadeli) |
| 7.2 | WAF / ModSecurity veya CDN WAF entegrasyonu |
| 7.3 | Rate limit, brute-force koruması, audit log (panel + engine) |
| 7.4 | Gizli yönetim: 2FA zorunluluğu admin için, IP allowlist (isteğe bağlı) |
| 7.5 | KVKK / yedekleme politikası dokümantasyonu |

---

## Faz 8 — Ticari ve operasyon

| # | Görev |
|---|--------|
| 8.1 | Paket limitleri gerçek enforce (domain sayısı, disk kotası — `quota` projesi veya XFS quota) |
| 8.2 | Faturalama (Stripe + yerel e-fatura entegrasyonu ihtiyaca göre) |
| 8.3 | Bayi hiyerarşisi: alt kullanıcı, marka (white-label) |
| 8.4 | Lisans / güncelleme sunucusu (isteğe bağlı) |

---

## Faz 9 — Performans ve gözlemlenebilirlik

| # | Görev |
|---|--------|
| 9.1 | Prometheus metrikleri (engine + panel) |
| 9.2 | Uyarılar (Disk, servis down, sertifika süresi, yedek hatası) |
| 9.3 | Merkezi log (Loki/ELK) — isteğe bağlı |

---

## Faz 10 — AI desteği (ürün farklılaştırıcı)

| # | Görev |
|---|--------|
| 10.1 | **Teşhis asistanı:** log + metrik özetini modele verip öneri (ör. “502 → PHP-FPM pool dolu”) |
| 10.2 | **Güvenlik:** anormal trafik özetleri, şüpheli cron/dosya değişikliği uyarısı |
| 10.3 | **Doğal dil:** “example.com için SSL yenile” → onaylı komut üretimi (RBAC + onay akışı şart) |
| 10.4 | **Dokümantasyon botu:** panel içi yardım, Türkçe/İngilizce |

**Not:** AI için veri minimizasyonu, API anahtarlarının panel dışında tutulması ve tüm tehlikeli işlemlerde **insan onayı** zorunlu olmalı.

---

## Geliştirme sırası (özet)

1. **Faz 1** tamamlanmadan “paylaşımlı hosting satışı” yapılmamalı.  
2. Paralel olabilecekler: Faz 9’un bir kısmı (metrik) + Faz 7.3 audit log.  
3. AI (Faz 10), en az **Faz 1 + 7.1/7.4** ile birlikte planlanmalı (yetki sınırları net olmalı).

**İlerleme:** Faz **1** tamam. Faz **2.1–2.4** uygulandı: MySQL + PostgreSQL provizyon, WordPress otomatik kurulum (engine), kısıtlı composer/npm (engine + panel Site tools). Kuyruk işi (2.4) istenirse `ShouldQueue` job ile genişletilebilir. **Paket kotası:** barındırma paketindeki `max_*`, SSL/yedek bayrakları ve yedek adedi üst sınırı panel API’de uygulanır (admin muaf).

- **Faz 6 (kısmi):** Engine’de `hosting.execute_backups: true` iken panel yedeği için `paths.web_root/<domain>` dizini `tar.gz` olarak `engine-state/backup-files/` altına yazılır; panel yedek kaydı `completed` + `size_mb` ile kapanır. `hosting.execute_backup_restore` ile aynı arşiv `tar -xzf` ile web köküne açılır (tehlikeli; prod’da dikkatli). Uzak S3 vb. hâlâ açık iş.
- **Faz 9.1 (kısmi):** Engine `server.prometheus_enabled: true` olduğunda kimlik doğrulamasız `GET /metrics` (Go + process collector); ağ erişimini firewall ile kısıtlayın.

Tam Plesk/cPanel eşliği için Faz **3–5** ve Faz **6**’nın kalanı (uzak hedef, geri yükleme sihirbazı), **7–8**, **9.2+**, **10** için gerçek servis / LLM entegrasyonları gerekir; ayrıntı için `docs/DEPLOYMENT.md`.

---

## Canlıya alma kontrol listesi (kısa)

- [ ] Ayrı sunucu veya VM; engine root veya yetkili kullanıcı ile nginx yazımı  
- [ ] `ENGINE_INTERNAL_KEY` ve panel `.env` eşleşmesi  
- [ ] Firewall: yalnızca 80/443/22 (veya VPN)  
- [ ] Yedek ve izleme minimum düzeyde bile olsa açık  
- [ ] İsteğe bağlı: `PHPMYADMIN_URL`, `ADMINER_URL` (panel `GET /api/config/ui-links`)  
- [ ] İsteğe bağlı: `LICENSE_KEY` + admin menüden Lisans sayfası ile doğrulama  
- [ ] Tekrarlanabilir deploy: repodaki `deploy/` (script + örnek Ansible) ve `docs/DEPLOYMENT.md`  

Bu dosya; ürün vizyonu ve sprint planlaması için canlı tutulmalıdır.
