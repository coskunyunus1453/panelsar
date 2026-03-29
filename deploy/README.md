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

## Hızlı başlangıç (Debian 12 / Ubuntu 22.04+)

1. Sunucuya repoyu klonlayın (örnek):

   ```bash
   sudo mkdir -p /var/www && sudo chown "$USER":"$USER" /var/www
   git clone <repo-url> /var/www/panelsar
   cd /var/www/panelsar
   ```

2. **Go** kurulu olmalı (engine derlemek için): `apt install golang-go` veya resmi Go paketi.

3. Kurulum betiğini çalıştırın:

   ```bash
   sudo bash deploy/bootstrap/install-production.sh
   ```

   Varsayılanlar:

   - **MariaDB** kurulur ve panel veritabanı oluşturulur (`WITH_MARIADB=1`).
   - **Node.js 20** için NodeSource eklenir (`WITH_NODE_REPO=1`).
   - **Engine** yalnızca **127.0.0.1:9090** üzerinde dinler; dış ağa açmayın.
   - **Nginx** SPA + `/api` için yapılandırılır; React derlemesi `panel/public/` içine alınır.

4. Tarayıcıdan sunucu IP veya alan adı ile açın. **HTTPS** için:

   ```bash
   sudo certbot --nginx -d panel.ornek.com
   ```

   Ardından `panel/.env` içinde `APP_URL` değerini güncelleyin ve:

   ```bash
   cd /var/www/panelsar/panel && sudo -u www-data php artisan config:cache
   ```

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

## Güvenlik özeti

- Engine **loopback**; panel ile aynı makinede çalışır, `.env` içindeki `ENGINE_INTERNAL_KEY` `/etc/panelsar/engine.yaml` ile eşleşir.
- Nginx’te temel başlıklar (`X-Frame-Options`, `nosniff`, …) ve gzip.
- Panel şifreleri `/root/panelsar-panel-mysql.secret` (MariaDB kurulduysa).

## Güncelleme

```bash
export PANEL_ROOT=/var/www/panelsar/panel
export FRONTEND_ROOT=/var/www/panelsar/frontend
sudo -E bash deploy/scripts/deploy-panel.sh
sudo systemctl restart panelsar-engine
```

## Dosyalar

| Dosya | Açıklama |
|--------|-----------|
| `host/install.sh` | **kodsar.com/panel’e yükleyeceğiniz tek dosya** (repo URL üstte düzenlenir) |
| `bootstrap/remote-install.sh` | Aynı mantık; GitHub raw veya başka CDN’den de verilebilir |
| `bootstrap/install-production.sh` | Ana kurulum |
| `nginx/panelsar.conf` | Site şablonu |
| `systemd/panelsar-engine.service` | Engine servisi |
| `configs/engine.production.yaml` | `/etc/panelsar/engine.yaml` şablonu |
