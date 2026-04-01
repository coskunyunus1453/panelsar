# Execution Checklist

Bu dosya aktif gelistirme sirasinda her adimi takip etmek icin kullanilir.

## A) Hazirlik
- [x] Vendor dokuman klasoru olusturuldu
- [x] Mimari taslak yazildi
- [x] Yol haritasi yazildi
- [x] Backlog issue listesi olusturuldu

## B) Uygulama Sirası (Yuksek Oncelik)
- [x] Faz 1: Auth + RBAC + 2FA (admin+ability temelli vendor route korumasi)
- [x] Faz 1: Audit events
- [x] Faz 2: Plan/Feature/License tablolari
- [x] Faz 2: Lisans signature endpoint
- [x] Faz 3: Node activation endpoint
- [x] Faz 3: Heartbeat endpoint

## C) Guvenlik Kontrol Kapilari
- [x] Endpoint rate-limit kontrolu
- [x] Authorization policy coverage (role+ability+admin2fa)
- [x] Secret encryption kontrolu
- [x] Replay korumasi (timestamp/nonce)
- [x] Audit tamligi testi (vendor kritik aksiyonlari)

## D) Cikis Kriteri (MVP)
- [x] Lisans olusturma -> node aktivasyonu -> feature apply zinciri API seviyesinde hazir
- [x] Lisans askiya alma node tarafina verify/heartbeat payload ile yansir
- [x] Vendor panelden tenant + lisans + node yonetimi backend API seviyesinde tamam

## E) Faz 4 / Faz 5 / Faz 6 Durumu
- [x] Faz 4: Billing temel modeller (subscription, invoice, payment)
- [x] Faz 4: Lisans suspend/reactivate otomasyonu (subscription status tabanli)
- [x] Faz 4: Vendor billing webhook (HMAC imza kontrollu)
- [x] Faz 5: Musteri 360 endpoint
- [x] Faz 5: Lisans timeline endpoint
- [x] Faz 5: Support ticket + message endpointleri
- [x] Faz 6: Security audit feed/export endpointleri
- [x] Faz 5: Tenant 360 icinde domain/modul envanteri gorunumu
- [x] Faz 1: Vendor rol ayrimi (vendor_admin/support/finance/devops)
- [x] Faz 6: SIEM webhook config + test endpointleri
- [x] Faz 0: Paketleme/fiyat modeli (Community/Pro/Reseller, EUR)
