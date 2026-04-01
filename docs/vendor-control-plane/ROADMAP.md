# Vendor Control Plane Roadmap

## Faz 0 - Foundation (Hazirlik)

- [x] Vendor panel hedef kapsamlarini kilitle
- [x] Paketleme/fiyat modelini netlestir (Community, Pro, Reseller)
- [x] Guvenlik minimum kriterlerini yazili hale getir
- [x] Teknik kararlar: tek repo mu ayrik repo mu (tek repo)

Teslim ciktilari:
- Mimari dokuman
- Faz bazli backlog
- Kabul kriterleri

---

## Faz 1 - Auth, Yetki, Audit (Guvenli cekirdek)

- [x] Vendor admin auth (session + 2FA)
- [x] Rol modeli (owner/admin/support/finance/devops)
- [x] Permission middleware
- [x] Audit event altyapisi
- [x] Guvenlik olay loglama

Kabul kriteri:
- Kritik tum istekler audit event olusturur
- Yetkisiz kullanici endpointlere erisemez

---

## Faz 2 - Plan, Feature, License Core

- [x] Plan CRUD
- [x] Feature CRUD
- [x] Plan-feature map
- [x] License CRUD (olustur/askiya al/iptal)
- [x] License signature ve verify endpoint

Kabul kriteri:
- Lisans yanitinda plan + feature set dogru doner
- Signature dogrulamasi zorunlu calisir

---

## Faz 3 - Node Activation ve Heartbeat

- [x] Node activate endpoint
- [x] Instance binding (instance_id/fingerprint)
- [x] Periyodik heartbeat
- [x] Node status dashboard kartlari
- [x] Grace period mantigi

Kabul kriteri:
- Lisans degisimi node tarafina belirlenen surede yansir

---

## Faz 4 - Billing ve Abonelik

- [x] Subscription modeli
- [x] Fatura kayitlari
- [x] Odeme webhook dogrulamasi
- [x] Suspend/reactivate otomasyonu

Kabul kriteri:
- Odemesi geciken lisanslar otomatik policy ile yonetilir

---

## Faz 5 - Operasyonel Panel ve Destek

- [x] Musteri 360 gorunumu
- [x] Lisans timeline
- [x] Node health listesi
- [x] Destek/ticket temel modul

Kabul kriteri:
- Operasyon ekibi tek panelden musteri/lisans/node gorebilir

---

## Faz 6 - Hardening ve GTM Hazirlik

- [x] Pen-test checklist tamamlama
- [x] SIEM/export entegrasyonlari
- [x] SLA ve incident runbook
- [x] Dokumantasyon + onboarding

Kabul kriteri:
- Uretime cikis kontrol listesi tamamen yesil
