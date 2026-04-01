# Vendor Security Runbook

Bu belge Vendor Control Plane icin operasyonel guvenlik kontrol adimlarini ozetler.

## 1) Kurulum Sonrasi Zorunlu Kontroller

1. `VENDOR_LICENSE_SIGNING_KEY` tanimli olmali.
2. `VENDOR_BILLING_WEBHOOK_SECRET` tanimli olmali.
3. Vendor endpointlerine erisen admin hesaplarinda 2FA acik olmali.
4. `php artisan panelsar:sync-abilities` calistirilmali.

## 2) Periyodik Kontroller

- Haftalik:
  - `/api/vendor/security/audit?severity=critical` incelemesi
  - Beklenmeyen lisans status degisimi kontrolu
- Gunluk:
  - Heartbeat almayan node listesi
  - Failed webhook denemeleri

## 3) Incident Akisi

1. Kritik audit eventi tespit et
2. Ilgili tenant/license/node baglamini cikar
3. Gerekirse lisansi `suspended` yap
4. Support ticket ile olay kaydini ac
5. Root cause ve kalici aksiyonu runbook'a ekle

## 4) API Guvenlik Notlari

- Public webhook endpointi HMAC imzasi olmadan kabul edilmez.
- Node heartbeat token hash olarak saklanir.
- Vendor route'lari role+ability+2FA kombosu ile korunur.

