# Hostvim — üretim (yayın) kontrol listesi

Kurulumdan sonra veya her majör sürüm öncesi işaretleyin. Amaç: yapılandırma tutarlılığı, sırlar ve temel güvenlik.

## 1. Panel (Laravel) — ortam

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_URL` gerçek HTTPS kök URL ile aynı (yönlendirme / önbellek tutarlılığı)
- [ ] `APP_KEY` üretildi ve **asla** repoda yok (`php artisan key:generate` yalnız ilk kurulumda)
- [ ] Önbellek ve config üretimde güncel: `php artisan config:cache`, `route:cache` (mümkünse `view:cache`)

## 2. Panel ↔ Engine — sırlar ve adresler

- [ ] `ENGINE_API_URL` panelden engine’a ulaşılan adres (genelde `http://127.0.0.1:9090` veya yerel reverse proxy)
- [ ] `ENGINE_API_SECRET` ile engine `security.jwt_secret` **aynı** (terminal WebSocket JWT için zorunlu)
- [ ] `ENGINE_INTERNAL_KEY` ile engine `security.internal_api_key` **aynı**, güçlü rastgele değer (üretimde **dolu** tutun; süreç öldürme / reboot / stack install bu anahtarla korunur)
- [ ] Engine `security.allowed_origins` panelin tarayıcı kökeni(leri) ile uyumlu (CORS); virgülle liste

## 3. Engine — sunucu ve ağ

- [ ] `server.debug: false` (üretim yaml’da)
- [ ] Engine portu (varsayılan `9090`) yalnız **localhost** veya güvenilen iç ağa bağlı; internete açıksa TLS + firewall ile sıkı sınır
- [ ] `paths.web_root` ile panel `HOSTVIM_HOSTING_WEB_ROOT` (veya eski `PANELSAR_HOSTING_WEB_ROOT`) aynı hosting kökünü işaret ediyor

## 4. HTTPS ve terminal

- [ ] Panel kullanıcıları için geçerli TLS sertifikası
- [ ] Canlıda terminal kullanılıyorsa `FORCE_WSS_TERMINAL=true` ve WebSocket’in güvenli proxy ile `wss` üzerinden engine’a ulaştığı doğrulandı

## 5. Veritabanı ve migration

- [ ] `php artisan migrate --force` üretimde sorunsuz
- [ ] Yedekleme: panel DB + müşteri verisi (`data/www` vb.) için otomatik yedek ve **geri yükleme denemesi** yapıldı

## 6. Ödeme (Stripe) — kullanılıyorsa

- [ ] `STRIPE_SECRET` üretim anahtarı; test anahtarı yok
- [ ] `STRIPE_WEBHOOK_SECRET` Stripe panosundaki imza sırrı ile eşleşiyor; webhook URL erişilebilir

## 7. Vendor control plane — kullanılıyorsa

- [ ] `VENDOR_ENABLED` / `APP_PROFILE` beklendiği gibi
- [ ] `VENDOR_PORTAL_HOSTS` ve `EnforceVendorHost` davranışı üretim hostlarıyla uyumlu
- [ ] `VENDOR_BILLING_WEBHOOK_SECRET` dolu ve sağlayıcı imzası bu sırra göre üretiliyor
- [ ] Gerekirse `VENDOR_LICENSE_SIGNING_KEY` tanımlı

## 8. Güvenlik ve işletim

- [ ] Dosya izinleri: `.env` ve secret dosyaları web sunucusundan okunamıyor; MySQL şifre dosyaları `600` vb.
- [ ] Yönetici hesapları için `ENFORCE_ADMIN_2FA` politikası bilinçli (varsayılan açık)
- [ ] `HOSTVIM_SAFE_AUDIT` (maskelemeli audit; eski `PANELSAR_SAFE_AUDIT`) üretimde açık bırakıldı (`true`)
- [ ] Firewall / güvenlik grupları: yalnız 80/443 (ve ssh) dış dünyaya; engine iç arayüz kapatıldı

## 9. Bağımlılık ve sağlık taraması

- [ ] `composer install --no-dev --optimize-autoloader` (üretimde dev paket yok)
- [ ] İsteğe bağlı: `composer audit`, frontend için `npm audit` / build pipelineında güvenlik job’u
- [ ] `GET /api/health` (panel) ve engine `GET /health` izlemede kullanılıyor

## 10. Süreç ve yayın

- [ ] Gereksiz dosyalar repoda / sunucuda yok (test script’leri, geçici dump’lar)
- [ ] Sürüm / etiket: dağıtımın hangi commit’ten geldiği kayıt altında
- [ ] Dağıtım sonrası duman testi: giriş, 2FA (varsa), site listesi, dosya yöneticisi, yedek tetikleme (uygunsa), engine bağlantısı

---

**Not:** Bu liste hukuki veya sertifikasyon (SOC2, ISO vb.) kapsamında değildir; operasyonel minimumdur. Kurulum betikleri için `deploy/README.md` dosyasına bakın.
