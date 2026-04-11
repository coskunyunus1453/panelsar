# Hostvim ↔ WHMCS entegrasyonu

**Panel arayüzü:** Yönetici olarak giriş yapın → sol menü **Erişim ve yönetim** → **WHMCS** (`/admin/whmcs`). Oradan ZIP indirip aşağıdaki adımlarla kurulumu tamamlayın.

Plesk ve aaPanel dokümantasyonlarıyla **aynı sınıfta** bir kurulumdur: WHMCS’e bir **sunucu modülü** (`modules/servers/…`) eklersiniz; faturalama ile panel arasındaki konuşmayı **sizin sunucu kaydınızdaki gizli anahtar** yapar. Müşteri API key girmez.

Referans (aynı mantık, farklı ürün):

- [Plesk Multi Server + WHMCS](https://docs.plesk.com/en-US/onyx/multi-server-guide/integration-with-whmcs.77894/) — modülü `modules/servers/` altına koyma ve sunucu tanımlama.
- [aaPanel WHMCS](https://www.aapanel.com/docs/Function/whms/whms.html) — zip → `modules/servers`, sunucuda modül + erişim anahtarı, ürün bağlama.

## Hangisi “daha kolay”?

| | Plesk / aaPanel | Hostvim |
|---|------------------|---------|
| Yer | `modules/servers/<modül>/` | `modules/servers/hostvim/` |
| Panel tarafı | Plesk API / aaPanel API | Hostvim panel API + `HOSTVIM_WHMCS_SECRET` |
| WHMCS tarafı | Sunucu + ürün + modül ayarları | Aynı |

**Teknik olarak hepsi aynı yol.** aaPanel sayfası operatör için genelde en anlaşılır çünkü adım adım ekran mantığıyla yazılmış; Hostvim’de de aşağıdaki sırayı izlemeniz yeterli.

## Ön koşullar

- WHMCS kurulu ve çalışır.
- Hostvim **panel** kurulu; `.env` düzenlenebilir.
- WHMCS sunucusundan panele HTTPS ile erişim (firewall / DNS tamam).

## 1) Hostvim panelde gizli anahtar

Panel `.env`:

```env
HOSTVIM_WHMCS_SECRET=buraya-uzun-rastgele-gizli-deger
```

Bu değer **yalnızca** sizde kalır; WHMCS sunucu kaydının şifre alanına yazılacak.

## 2) WHMCS’e modül dosyalarını kopyalama

Bu repodan şu klasörü alın:

```text
integrations/whmcs/modules/servers/hostvim/
```

WHMCS kurulumunuzda hedef:

```text
<WHMCS_KÖKÜ>/modules/servers/hostvim/
```

İçinde doğrudan `hostvim.php` görünmeli:

```text
.../modules/servers/hostvim/hostvim.php
```

İsteğe bağlı örnek hook:

```text
integrations/whmcs/includes/hooks/hostvim_example_automation.php
→ <WHMCS_KÖKÜ>/includes/hooks/
```

## 3) WHMCS’te sunucu ekleme

**Yapılandırma → Sistem ayarları → Sunucular** (veya sürümünüze göre eşdeğer menü) → **Yeni sunucu**.

| Alan | Değer |
|------|--------|
| **Modül adı** | `hostvim` |
| **Hostname** | Panel kök URL (örn. `https://panel.ornek.com`) — modül API yolunu kendisi birleştirir |
| **Şifre** | `HOSTVIM_WHMCS_SECRET` ile **aynı** |
| **Kullanıcı adı** | (İsteğe bağlı) Hostvim **admin** e-postası — yönetici SSO için |

**Bağlantıyı test et** (Test Connection).

Paket ID eşlemesi için panel uç noktası (Bearer = aynı secret):

`GET .../api/integrations/whmcs/packages`

## 4) Hosting ürününü bağlama

**Ürünler / Hizmetler → Ürün oluştur** → ürün tipi hosting → **ilgili sunucuyu** ve modülü seçin.

Modül ayarlarında örnek: **Hosting Package ID**, PHP sürümü, web sunucusu, Let’s Encrypt (evet/hayır).

Siparişte **birincil alan adı** dolu olmalı (site oluşturma için).

## 5) Sipariş / hesap oluşturma

Ödeme veya manuel onay akışınız nasılsa, hizmet **Module Create** çalıştığında panelde kullanıcı + site oluşturulur. WHMCS müşteri **e-postası** = panel kullanıcı **e-postası** olur.

aaPanel dokümanındaki gibi, gerekirse siparişte **“Run Module Create”** / otomatik kurulum seçeneklerini açın.

## 6) Müşteri paneline giriş (SSO)

Müşteri WHMCS müşteri alanına girer → **Hizmetlerim** → ilgili hosting → **Panele git / SSO** (tema ve WHMCS sürümüne göre etiket değişir).

Modül `templates/clientarea.tpl` ile ürün sayfasında ek bir “Panele git” kutusu da gösterebilir.

---

**Özet:** Hostvim yaklaşımı Plesk/aaPanel’den kötü veya farklı bir “mimari” değil; hepsi **sunucu modülü + gizli anahtar + ürün**. Kolaylık için bu README, aaPanel tarzı tek sayfada toplandı; ürün paketinizde PDF veya wiki’ye aynı adımları kopyalayabilirsiniz.
