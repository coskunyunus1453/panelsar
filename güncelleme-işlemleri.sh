1) Yerelde Git’e at (GitHub’a gönder)
cd /Applications/XAMPP/xamppfiles/htdocs/panelsar
git status
git add -A
git commit -m "Açıklayıcı mesaj: ne değişti"
git push origin main
Dalın main değilse: git branch ile bak, git push origin DAL_ADI kullan.

2) Sunucuda güncelle (SSH ile)
Sunucuya bağlan (IP’n: 194.163.131.213):

ssh root@194.163.131.213
Projeyi nereye koyduysan oraya gir (çoğu zaman):

cd /var/www/panelsar
git fetch origin
git pull --ff-only origin main
Sonra panel + ön yüz (repodaki betik):

PANEL_ROOT=/var/www/panelsar/panel bash deploy/scripts/deploy-panel.sh
Engine (Go) değiştiyse:

cd /var/www/panelsar/engine
go build -o /usr/local/bin/panelsar-engine ./cmd/panelsar-engine
systemctl restart panelsar-engine
Nginx/Laravel önbellek gerekiyorsa:

sudo -u www-data php /var/www/panelsar/panel/artisan config:cache
sudo -u www-data php /var/www/panelsar/panel/artisan route:cache
sudo nginx -t && sudo systemctl reload nginx
3) Tarayıcı
Panel: http://194.163.131.213/ — sertifika yoksa http kalır; güncellemeden sonra sert cache için gerekirse gizli pencerede dene.

Özet: Mac’te add → commit → push → sunucuda ssh → cd repo → git pull → deploy-panel.sh (+ gerekirse engine build + systemctl restart).