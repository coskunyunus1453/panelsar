# Vendor Control Plane Architecture

## 1) Kapsam

Vendor Control Plane, musteri sunucularinda calisan Panelsar node'larini merkezi olarak yoneten katmandir.

Ana sorumluluklar:
- Tenant/musteri yonetimi
- Lisans uretimi ve dogrulama
- Plan/ozellik (entitlement) yonetimi
- Node kaydi, heartbeat ve durum takibi
- Faturalama ve abonelik olaylari
- Audit ve guvenlik olaylari

## 2) Sistem Bilesenleri

1. **Vendor API (Laravel)**
   - Lisans, plan, tenant, node, billing endpointleri
   - RBAC, 2FA, audit log
2. **Vendor UI (React + TypeScript)**
   - Operasyon dashboard
   - Lisans/plan/musteri ekranlari
3. **Node Agent Integration (mevcut panel + engine)**
   - Aktivasyon endpointi
   - Periyodik lisans check
   - Feature flag cekme ve local cache

## 3) Veri Modeli (Ilk Surum)

- `tenants`
- `vendor_users`
- `plans`
- `features`
- `plan_features`
- `licenses`
- `license_activations`
- `nodes`
- `subscriptions`
- `invoices`
- `payments`
- `audit_events`
- `security_events`

## 4) Lisans Akisi

1. Node ilk kurulumda `license_key + instance_id + fingerprint` ile aktivasyon ister.
2. Vendor API lisansi ve plani dogrular.
3. Imzali (signed) bir lisans cevabi doner:
   - durum (`active`, `expired`, `suspended`, `revoked`)
   - `expires_at`
   - `features`
   - `limits`
4. Node bu cevabi cache'ler, belirli aralikla yeniler.
5. Lisans sunucusu erisilemezse grace period uygulanir.

## 5) Guvenlik Gereksinimleri

- Admin girislerinde 2FA
- Kritik endpointlerde rate-limit
- HMAC imzali node/vendor haberlesmesi
- Timestamp + nonce ile replay korumasi
- Secretlar sifreli saklama
- Tüm kritik aksiyonlar için audit log
- RBAC hem UI hem API seviyesinde zorunlu

## 6) Operasyonel Ilkeler

- Backward compatible API versiyonlama
- Feature flags ile kontrollu yayin
- Health endpoint + telemetry
- Incident durumunda lisans fail-safe davranisi (grace mode)
