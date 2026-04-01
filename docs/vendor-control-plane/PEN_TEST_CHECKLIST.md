# Vendor Control Plane Pen-Test Checklist

Bu liste canliya cikmadan once zorunlu guvenlik testlerini takip etmek icindir.

## 1) Kimlik dogrulama ve yetki
- [x] `/api/vendor/*` endpointleri rol disi kullanicilar icin 403 donuyor.
- [x] Vendor login disinda vendor portal erisimi engelleniyor.
- [x] 2FA kapali vendor rol hesaplari kritik endpointlerde 423 aliyor.
- [x] Token iptal/yenileme akisinda eski token tekrar kullanilamiyor.

## 2) Replay ve imza guvenligi
- [x] Node activate/heartbeat `X-Vendor-Timestamp` ve `X-Vendor-Nonce` olmadan reddediliyor.
- [x] Eski timestamp ile replay denemeleri reddediliyor.
- [x] Ayni nonce ile ikinci istek 409 donuyor.
- [x] Billing webhook imza (`X-Vendor-Signature`) olmadan reddediliyor.

## 3) Veri izolasyonu
- [x] Tenant 360 sadece ilgili tenant verisini donduruyor.
- [x] Lisans timeline farkli lisans ID ile izinsiz gorulemiyor.
- [x] Panel user baglantisinda (`panel_user_id`) tenant disi veri sizmasi yok.

## 4) Hassas veri korumasi
- [x] SIEM secret sifreli saklaniyor (plain text DB'de yok).
- [x] API cevaplari secret/token degerlerini maskeliyor.
- [x] Audit export dosyasinda gizli deger yok.

## 5) Rate limit ve dayaniklilik
- [x] Vendor endpointlerde brute force denemeleri throttling'e takiliyor.
- [x] Node endpointleri yuk altinda stabil ve limitli.
- [x] Webhook endpointi yuksek istekte kaynak tuketimi yaratmiyor.

## 6) Operasyonel testler
- [x] Lisans olustur -> activate -> heartbeat zinciri calisiyor.
- [x] Lisans askiya alindiginda node usable=false aliyor.
- [x] Grace period suresinde lisans davranisi beklenen gibi.
- [x] SIEM test endpointi hatali endpointte kontrollu hata donuyor.

