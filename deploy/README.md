# Hostvim — canlı sunucu kurulumu

Bu klasör, paneli **tek sunucuda** güvenli ve tekrarlanabilir şekilde ayağa kaldırmak için dosyalar içerir.

**Yayın öncesi:** [PRODUCTION_CHECKLIST.md](PRODUCTION_CHECKLIST.md) — ortam değişkenleri, engine hizalama, TLS ve duman testi maddeleri.

## Sizin tarafınız (müşteriye vermeden önce)

Müşteri **sizin barındırdığınız** kaynaktan kodu çekecek. Yaygın seçenekler:

| Yöntem | Açıklama |
|--------|-----------|
| **Genel Git** | GitHub / GitLab **public** repo → müşteri `git clone` veya `remote-install.sh` ile çeker. |
| **Özel Git** | Private repo → müşteriye **deploy key** veya **sınırlı PAT** verirsiniz; `HOSTVIM_REPO_URL` içinde token kullanılabilir (risk: shell geçmişi). Daha iyisi: müşteriye salt okunur deploy key. |
| **Sabit arşiv** | `hostvim-v1.tar.gz` üretip CDN/S3’e koyarsınız; müşteri `curl … \| tar` ile açar (ayrı betik gerekir). |
| **Kurulum script’i CDN** | Sadece `remote-install.sh` dosyasını kendi domain’inize koyarsınız (`https://install.sirketiniz.com/remote-install.sh`); script içinde `HOSTVIM_REPO_URL` sizin repo adresinize ayarlı olur. |

Öneri: **Public veya müşteriye özel erişimli** bir Git URL + müşterinin indirdiği **tek** kurulum dosyasını kendi domain’inizde host edin.

### `kodsar.com/panel` üzerine ne yükleyeceksiniz?

Müşteriye vereceğiniz **iki giriş betiği** (önerilen): `deploy/host/install-community.sh` ve `deploy/host/install-pro.sh`. İsteğe bağlı: ortak motor `install.sh` veya eski adlar (`install-customer.sh`, `install-vendor.sh` — yönlendirme).

- **Tüm projeyi** bu dizine atmanız gerekmez.
- `install.sh` içindeki `HOSTVIM_REPO_URL` satırını **kendi Git adresinizle** düzenleyin (veya müşteriye env ile verin; eski: `PANELSAR_REPO_URL`).
- Asıl kod **`git clone` ile Git’ten** iner.

aaPanel’in `curl -ksSO` kullanımı **sertifika doğrulamasını kapattığı** için (`-k`) orta düzeyde risklidir. Hostvim örneğinde **doğrulama açık** bırakıldı (daha güvenli).

## Müşteri tarafı (aaPanel tarzı tek satır)

Müşteri **root** (veya `sudo`) ile Debian/Ubuntu sunucuda:

```bash
URL="https://kodsar.com/panel/install.sh" && if command -v curl >/dev/null 2>&1; then curl -fsSL "$URL" | sudo bash; else wget -qO- "$URL" | sudo bash; fi
```

### İki sürüm komutu (önerilen)

Komutu yapıştırırken satır başına `*` veya madde işareti **eklemeyin**; kabukta `*` dosya adlarını genişletir ve komut bozulur (ör. `go hostvim-admin-login.txt` hatası). Sorun yaşarsanız: `cd /tmp && curl … | bash`.

**Plesk/cPanel benzeri davranış:** `install.sh` varsayılanında panel MySQL’i ve `data/www` **silinmez**; aynı komutla tekrar çalıştırmak kod + `migrate` güncellemesidir. Sunucuyu baştan sıfırlamak (fabrika) için bilinçli olarak `HOSTVIM_FRESH_INSTALL=1` veya `RESET_PANEL_DB=1` verin.

| Sürüm | Açıklama | Komut (örnek URL) |
|--------|-----------|-------------------|
| **Community** | Freemium / barındırma paneli; `APP_PROFILE=customer`, vendor kapalı | `curl -fsSL …/install-community.sh \| sudo bash` |
| **Pro** | Lisans + tam özellik; `APP_PROFILE=customer`, vendor kapalı. Lisans/SaaS müşterileri merkezi sitede. İsteğe bağlı: `HOSTVIM_LICENSE_KEY=...` | `HOSTVIM_LICENSE_KEY="..." curl -fsSL …/install-pro.sh \| sudo bash` |

Eski dosya adları yönlendirme: `install-customer.sh` → community, `install-vendor.sh` → pro.

```bash
# Community (kodsar.com örneği — dosyayı kendi domain’inize kopyalayın)
curl -fsSL https://kodsar.com/panel/install-community.sh | sudo bash

# Pro
curl -fsSL https://kodsar.com/panel/install-pro.sh | sudo bash
# veya anahtar ile:
# HOSTVIM_LICENSE_KEY="hv_..." curl -fsSL https://kodsar.com/panel/install-pro.sh | sudo bash
```

## Profil artifact üretimi (güncelleme güvenli)

Sürekli dosya değişimlerinde manuel ayıklama yapmamak için profile göre otomatik paket üretin:

```bash
cd /var/www/hostvim
bash deploy/scripts/build-profile-artifact.sh customer   # Community sürüm paketi
bash deploy/scripts/build-profile-artifact.sh vendor    # Pro / tam kod paketi (artifact içi APP_PROFILE=customer)
```

Üretilen paketler `dist-artifacts/` altında oluşur:

- `hostvim-customer-*.tar.gz` — Community (vendor backend dosyaları hariç)
- `hostvim-vendor-*.tar.gz` — Pro (tam paket; panel arayüzü yine müşteri profili)

Exclude listeleri:

- `deploy/profiles/common.exclude`
- `deploy/profiles/customer.exclude`
- `deploy/profiles/vendor.exclude`

Yeni dosya/klasör eklendikçe sadece bu listeleri güncellemeniz yeterli olur.

### CI otomasyonu

Repository icinde `.github/workflows/profile-artifacts.yml` eklidir.

- `main` push: customer + vendor artifact build eder ve Actions artifact olarak saklar.
- `v*` tag push: build eder, sonra `.tar.gz` dosyalarini otomatik GitHub Release'e ekler.

Ornek release tetikleme:

```bash
git tag v0.1.0
git push origin v0.1.0
```

Önce dosyayı indirip çalıştırmak isterseniz (aaPanel’e daha çok benzer):

```bash
URL="https://kodsar.com/panel/install.sh" && if command -v curl >/dev/null 2>&1; then curl -fsSL "$URL" -o /tmp/hostvim-install.sh; else wget -q "$URL" -O /tmp/hostvim-install.sh; fi && sudo bash /tmp/hostvim-install.sh
```

Alternatif (GitHub’daki `remote-install.sh` doğrudan):

```bash
curl -fsSL https://install.SIRKETINIZ.com/remote-install.sh | sudo bash
```

`install.sh` / `remote-install.sh` sonrası sıra:

1. `git`, `curl` ve gerekirse **Go** kurulur.
2. `HOSTVIM_REPO_URL` / `HOSTVIM_BRANCH` ile kod `/var/www/hostvim` altına **klonlanır** (veya güncellenir; eski: `PANELSAR_*`).
3. `install-production.sh` nginx, PHP, MariaDB, engine, ön yüz derlemesi vb. kurar.

**Repo URL’nizi sabitlemek için** müşteriye şunu da verebilirsiniz (tek satır):

```bash
curl -fsSL https://install.SIRKETINIZ.com/remote-install.sh | sudo HOSTVIM_REPO_URL=https://github.com/SIRKET/hostvim.git HOSTVIM_BRANCH=main bash
```

Özel repoda token kullanmak zorundaysanız (önerilmez, geçici):

```bash
sudo HOSTVIM_REPO_URL=https://TOKEN@github.com/SIRKET/hostvim.git bash -s <<< "$(curl -fsSL https://install.SIRKETINIZ.com/remote-install.sh)"
```

Daha güvenlisi: sunucuya **SSH deploy key** ekletmek ve normal `git@github.com:SIRKET/hostvim.git` URL kullanmak.

## Sıfırdan sunucu (temiz Debian/Ubuntu)

Yeni VPS veya formatlanmış makinede tipik sıra:

1. **SSH ile root veya sudo** kullanıcısı; saat dilimi / hostname isteğe bağlı.
2. Repoyu **tek kök dizinde** tutun (panel ve engine aynı repo içinde):

   ```bash
   sudo mkdir -p /var/www
   sudo chown "$USER":"$USER" /var/www
   git clone <SIZIN_REPO_URL> /var/www/hostvim
   cd /var/www/hostvim
   git checkout main   # veya kullandığınız dal
   ```

3. **Tam kurulum** (Go, PHP, Nginx, MariaDB, engine derlemesi, `hostvim-stack-install` + sudoers, frontend build dahil):

   ```bash
   sudo bash deploy/bootstrap/install-production.sh
   ```

   İlk kurulumda `SKIP_APT=0` (varsayılan) bırakın; sadece kod güncellediğinizde aşağıdaki “Güncelleme” bölümüne bakın.

4. Tarayıcıdan paneli açın. **HTTPS**:

   ```bash
   sudo certbot --nginx -d panel.ornek.com
   ```

   Sonra `panel/.env` içinde `APP_URL=https://panel.ornek.com` ve gerekirse `MAIL_*` (aşağıda “E-posta”).

5. **Admin → Giden posta** (`/admin/mail-settings`): SMTP / sendmail / log; test postası. **Admin → Sunucu paketleri** (`/admin/stack`): ek PHP-FPM, Dovecot, OpenDKIM vb. Kurulum betiği `sudoers` ve `hostvim-stack-install` dosyasını zaten yükler.

## Hızlı başlangıç (Debian 12 / Ubuntu 22.04+) — özet

1. `git clone` → `cd /var/www/hostvim`
2. `sudo bash deploy/bootstrap/install-production.sh` (Go betik içinde kurulabilir; ayrıca `apt install golang-go` da yeterli olabilir)
3. Varsayılanlar: **MariaDB**, **Node 20 kaynağı**, engine **127.0.0.1:9090**, Nginx + SPA
4. HTTPS ve `APP_URL` + `config:cache` (yukarıdaki gibi)

## Ortam değişkenleri (kurulum betiği)

| Değişken | Açıklama |
|----------|-----------|
| `HOSTVIM_HOME` | Repo kökü (varsayılan: `/var/www/hostvim`; eski betikler `PANELSAR_HOME` okur) |
| `SERVER_NAME` | Nginx `server_name`; yalnız IP için `_` (varsayılan) |
| `LETS_ENCRYPT_EMAIL` | ACME e-postası (engine şablonunda yer tutucu) |
| `SKIP_APT=1` | Paket kurulumunu atla |
| `SKIP_UFW=1` | UFW yapılandırmasını atla |
| `WITH_MARIADB=0` | MariaDB kurma / panel DB oluşturma |
| `WITH_POSTGRES=1` | Engine için PostgreSQL kur ve kullanıcı oluştur |
| `WITH_NODE_REPO=0` | NodeSource eklemeden dağıtım `nodejs` paketini kullan |
| `WITH_LOCAL_POSTFIX=0` | Varsayılan yerel Postfix + `sendmail` kurulumunu kapatır |

Barındırma **müşterisi** Git veya SSH görmez; tek adres panel arayüzüdür. Sunucu işleten siz bir kez `install-production.sh` (veya `remote-install.sh`) çalıştırırsınız.

## Güvenlik özeti

- Engine **loopback**; panel ile aynı makinede çalışır, `.env` içindeki `ENGINE_INTERNAL_KEY` `/etc/hostvim/engine.yaml` ile eşleşir.
- Nginx’te temel başlıklar (`X-Frame-Options`, `nosniff`, …) ve gzip.
- Panel şifreleri `/root/hostvim-panel-mysql.secret` (MariaDB kurulduysa; eski kurulumlar `panelsar-panel-mysql.secret`).

## Güncelleme (Git’ten kod çekme)

Repoyu **kök dizinden** güncelleyin (`panel/` altında `.git` olmayabilir):

```bash
cd /var/www/hostvim
git fetch origin
git checkout main    # veya master / kullandığınız dal
git pull --ff-only origin main
```

**Panel + ön yüz + migrate** (deploy betiği artık üst dizinde `.git` varsa otomatik `git pull` da yapar; yine de kökten çekmek net kalır):

```bash
cd /var/www/hostvim
export PANEL_ROOT=/var/www/hostvim/panel
export FRONTEND_ROOT=/var/www/hostvim/frontend
sudo -E bash deploy/scripts/deploy-panel.sh
```

**Engine ikilisini** yeniden derleyip servisi yenilemek (Go kodu değiştiyse):

```bash
cd /var/www/hostvim/engine
sudo go build -buildvcs=false -o /usr/local/bin/hostvim-engine ./cmd/hostvim-engine
sudo systemctl restart hostvim-engine
```

Nginx şablonu, systemd birimi veya `hostvim-stack-install` / sudoers değiştiyse, kökten tekrar:

```bash
cd /var/www/hostvim
SKIP_APT=1 sudo -E bash deploy/bootstrap/install-production.sh
```

`SKIP_APT=1` apt adımını atlar; yine de engine derlemesi ve dosya kopyaları çalışır (makinenizde zaten paketler kurulu varsayılır).

## E-posta (Plesk benzeri “hazır altyapı”)

1. **Panel bildirimleri (şifre sıfırlama vb.)**  
   Kurulum betiği varsayılan olarak **Postfix + mailutils** kurar ve `sendmail` yolunu kullanır. Ayarlar veritabanında tutulur; **Admin → Giden posta** ekranından SSH’sız **SMTP’ye geçiş**, test postası ve gönderen adresi yönetilir (şifre Laravel ile şifrelenir).

2. **Müşteri alan adı posta kutuları (MX, IMAP, tam DKIM)**  
   Hâlâ **DNS kayıtları** ve (isteğe bağlı) **Dovecot / OpenDKIM** ince ayarı gerekir; **Admin → Sunucu paketleri** ile paket kurulumu panelden tetiklenir. Tam otomatik “sihirbaz” roadmap maddesidir; giden panel postasından farklıdır.

Özet: Tek sunucuda **panel e-postası** kurulum + **Giden posta** sayfasıyla uçtan uca yönetilir; **barındırma müşterisi** `.env` veya Git ile uğraşmaz.

## Dosyalar

| Dosya | Açıklama |
|--------|-----------|
| `host/install.sh` | **kodsar.com/panel’e yükleyeceğiniz tek dosya** (repo URL üstte düzenlenir) |
| `bootstrap/remote-install.sh` | Aynı mantık; GitHub raw veya başka CDN’den de verilebilir |
| `bootstrap/install-production.sh` | Ana kurulum |
| `host/hostvim-stack-install` | Admin “Sunucu paketleri” için apt demetleri (→ `/usr/local/sbin/`) |
| `scripts/deploy-panel.sh` | `git pull` (repo kökü), composer, migrate, frontend build |
| `nginx/hostvim.conf` | Site şablonu |
| `systemd/hostvim-engine.service` | Engine servisi |
| `configs/engine.production.yaml` | `/etc/hostvim/engine.yaml` şablonu |
