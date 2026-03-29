# Panelsar — canlı sunucu kurulumu

Bu klasör, paneli **tek sunucuda** güvenli ve tekrarlanabilir şekilde ayağa kaldırmak için dosyalar içerir.

## Sizin tarafınız (müşteriye vermeden önce)

Müşteri **sizin barındırdığınız** kaynaktan kodu çekecek. Yaygın seçenekler:

| Yöntem | Açıklama |
|--------|-----------|
| **Genel Git** | GitHub / GitLab **public** repo → müşteri `git clone` veya `remote-install.sh` ile çeker. |
| **Özel Git** | Private repo → müşteriye **deploy key** veya **sınırlı PAT** verirsiniz; `PANELSAR_REPO_URL` içinde token kullanılabilir (risk: shell geçmişi). Daha iyisi: müşteriye salt okunur deploy key. |
| **Sabit arşiv** | `panelsar-v1.tar.gz` üretip CDN/S3’e koyarsınız; müşteri `curl … \| tar` ile açar (ayrı betik gerekir). |
| **Kurulum script’i CDN** | Sadece `remote-install.sh` dosyasını kendi domain’inize koyarsınız (`https://install.sirketiniz.com/remote-install.sh`); script içinde `PANELSAR_REPO_URL` sizin repo adresinize ayarlı olur. |

Öneri: **Public veya müşteriye özel erişimli** bir Git URL + müşterinin indirdiği **tek** kurulum dosyasını kendi domain’inizde host edin.

### `kodsar.com/panel` üzerine ne yükleyeceksiniz?

**Sadece bir dosya:** repodaki `deploy/host/install.sh` dosyasını kopyalayıp sunucunuzda `https://kodsar.com/panel/install.sh` (veya aynı içerik, farklı ad) olarak yayınlayın.

- **Tüm projeyi** bu dizine atmanız gerekmez.
- Dosyanın başındaki `PANELSAR_REPO_URL` satırını **kendi Git adresinizle** düzenleyin (veya müşteriye env ile verin).
- Asıl kod **`git clone` ile Git’ten** iner.

aaPanel’in `curl -ksSO` kullanımı **sertifika doğrulamasını kapattığı** için (`-k`) orta düzeyde risklidir. Panelsar örneğinde **doğrulama açık** bırakıldı (daha güvenli).

## Müşteri tarafı (aaPanel tarzı tek satır)

Müşteri **root** (veya `sudo`) ile Debian/Ubuntu sunucuda:

```bash
URL="https://kodsar.com/panel/install.sh" && if command -v curl >/dev/null 2>&1; then curl -fsSL "$URL" | sudo bash; else wget -qO- "$URL" | sudo bash; fi
```

Önce dosyayı indirip çalıştırmak isterseniz (aaPanel’e daha çok benzer):

```bash
URL="https://kodsar.com/panel/install.sh" && if command -v curl >/dev/null 2>&1; then curl -fsSL "$URL" -o /tmp/panelsar-install.sh; else wget -q "$URL" -O /tmp/panelsar-install.sh; fi && sudo bash /tmp/panelsar-install.sh
```

Alternatif (GitHub’daki `remote-install.sh` doğrudan):

```bash
curl -fsSL https://install.SIRKETINIZ.com/remote-install.sh | sudo bash
```

`install.sh` / `remote-install.sh` sonrası sıra:

1. `git`, `curl` ve gerekirse **Go** kurulur.
2. `PANELSAR_REPO_URL` / `PANELSAR_BRANCH` ile kod `/var/www/panelsar` altına **klonlanır** (veya güncellenir).
3. `install-production.sh` nginx, PHP, MariaDB, engine, ön yüz derlemesi vb. kurar.

**Repo URL’nizi sabitlemek için** müşteriye şunu da verebilirsiniz (tek satır):

```bash
curl -fsSL https://install.SIRKETINIZ.com/remote-install.sh | sudo PANELSAR_REPO_URL=https://github.com/SIRKET/panelsar.git PANELSAR_BRANCH=main bash
```

Özel repoda token kullanmak zorundaysanız (önerilmez, geçici):

```bash
sudo PANELSAR_REPO_URL=https://TOKEN@github.com/SIRKET/panelsar.git bash -s <<< "$(curl -fsSL https://install.SIRKETINIZ.com/remote-install.sh)"
```

Daha güvenlisi: sunucuya **SSH deploy key** ekletmek ve normal `git@github.com:SIRKET/panelsar.git` URL kullanmak.

## Sıfırdan sunucu (temiz Debian/Ubuntu)

Yeni VPS veya formatlanmış makinede tipik sıra:

1. **SSH ile root veya sudo** kullanıcısı; saat dilimi / hostname isteğe bağlı.
2. Repoyu **tek kök dizinde** tutun (panel ve engine aynı repo içinde):

   ```bash
   sudo mkdir -p /var/www
   sudo chown "$USER":"$USER" /var/www
   git clone <SIZIN_REPO_URL> /var/www/panelsar
   cd /var/www/panelsar
   git checkout main   # veya kullandığınız dal
   ```

3. **Tam kurulum** (Go, PHP, Nginx, MariaDB, engine derlemesi, `panelsar-stack-install` + sudoers, frontend build dahil):

   ```bash
   sudo bash deploy/bootstrap/install-production.sh
   ```

   İlk kurulumda `SKIP_APT=0` (varsayılan) bırakın; sadece kod güncellediğinizde aşağıdaki “Güncelleme” bölümüne bakın.

4. Tarayıcıdan paneli açın. **HTTPS**:

   ```bash
   sudo certbot --nginx -d panel.ornek.com
   ```

   Sonra `panel/.env` içinde `APP_URL=https://panel.ornek.com` ve gerekirse `MAIL_*` (aşağıda “E-posta”).

5. **Admin → Giden posta** (`/admin/mail-settings`): SMTP / sendmail / log; test postası. **Admin → Sunucu paketleri** (`/admin/stack`): ek PHP-FPM, Dovecot, OpenDKIM vb. Kurulum betiği `sudoers` ve `panelsar-stack-install` dosyasını zaten yükler.

## Hızlı başlangıç (Debian 12 / Ubuntu 22.04+) — özet

1. `git clone` → `cd /var/www/panelsar`
2. `sudo bash deploy/bootstrap/install-production.sh` (Go betik içinde kurulabilir; ayrıca `apt install golang-go` da yeterli olabilir)
3. Varsayılanlar: **MariaDB**, **Node 20 kaynağı**, engine **127.0.0.1:9090**, Nginx + SPA
4. HTTPS ve `APP_URL` + `config:cache` (yukarıdaki gibi)

## Ortam değişkenleri (kurulum betiği)

| Değişken | Açıklama |
|----------|-----------|
| `PANELSAR_HOME` | Repo kökü (varsayılan: `/var/www/panelsar`) |
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

- Engine **loopback**; panel ile aynı makinede çalışır, `.env` içindeki `ENGINE_INTERNAL_KEY` `/etc/panelsar/engine.yaml` ile eşleşir.
- Nginx’te temel başlıklar (`X-Frame-Options`, `nosniff`, …) ve gzip.
- Panel şifreleri `/root/panelsar-panel-mysql.secret` (MariaDB kurulduysa).

## Güncelleme (Git’ten kod çekme)

Repoyu **kök dizinden** güncelleyin (`panel/` altında `.git` olmayabilir):

```bash
cd /var/www/panelsar
git fetch origin
git checkout main    # veya master / kullandığınız dal
git pull --ff-only origin main
```

**Panel + ön yüz + migrate** (deploy betiği artık üst dizinde `.git` varsa otomatik `git pull` da yapar; yine de kökten çekmek net kalır):

```bash
cd /var/www/panelsar
export PANEL_ROOT=/var/www/panelsar/panel
export FRONTEND_ROOT=/var/www/panelsar/frontend
sudo -E bash deploy/scripts/deploy-panel.sh
```

**Engine ikilisini** yeniden derleyip servisi yenilemek (Go kodu değiştiyse):

```bash
cd /var/www/panelsar/engine
sudo go build -buildvcs=false -o /usr/local/bin/panelsar-engine ./cmd/panelsar-engine
sudo systemctl restart panelsar-engine
```

Nginx şablonu, systemd birimi veya `panelsar-stack-install` / sudoers değiştiyse, kökten tekrar:

```bash
cd /var/www/panelsar
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
| `host/panelsar-stack-install` | Admin “Sunucu paketleri” için apt demetleri (→ `/usr/local/sbin/`) |
| `scripts/deploy-panel.sh` | `git pull` (repo kökü), composer, migrate, frontend build |
| `nginx/panelsar.conf` | Site şablonu |
| `systemd/panelsar-engine.service` | Engine servisi |
| `configs/engine.production.yaml` | `/etc/panelsar/engine.yaml` şablonu |
