/**
 * XAMPP .env ile yapılmış build'ler chunk URL'lerine /hostvim/landing/public gömer;
 * canlı kök alan adında 404 verir. .env düzeltmek yetmez — public/build yeniden üretilmeli.
 */
const fs = require('fs');
const path = require('path');

const assetsDir = path.join(__dirname, '..', 'public', 'build', 'assets');
const forbidden = [
    'hostvim/landing/public',
    '/htdocs/hostvim',
    'localhost/hostvim',
];

if (!fs.existsSync(assetsDir)) {
    process.exit(0);
}

let failed = false;
for (const name of fs.readdirSync(assetsDir)) {
    if (!name.endsWith('.js')) {
        continue;
    }
    const full = path.join(assetsDir, name);
    const content = fs.readFileSync(full, 'utf8');
    for (const needle of forbidden) {
        if (content.includes(needle)) {
            console.error(
                `[vite] ${name} içinde yerel taban "${needle}" gömülü.\n` +
                    'Çözüm: .env → ASSET_URL boş, APP_URL=https://hostvim.com; npm run build; sonra sunucudaki public/build klasörünü tamamen değiştir.'
            );
            failed = true;
        }
    }
}

process.exit(failed ? 1 : 0);
