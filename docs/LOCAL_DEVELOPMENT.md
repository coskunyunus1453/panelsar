# Yerel geliştirme — doğrulama listesi

Projeyi göndermeden veya PR açmadan önce:

```bash
# Panel (Laravel)
cd panel
./vendor/bin/pint --test          # stil (pint.json: bootstrap/cache hariç)
./vendor/bin/phpunit              # testler (sqlite :memory:)

# Engine (Go)
cd ../engine
go vet ./...
go build ./...

# Ön yüz
cd ../frontend
npm run lint
npm run build
```

## Notlar

- **PHP 8.5:** Laravel `config/database.php` içinde `PDO::MYSQL_ATTR_SSL_CA` kullanımı PHPUnit’te “deprecation” uyarısı üretebilir; framework güncellemesiyle gider. Testler yine geçer.
- **`composer-setup.php`** repoda olmamalı; yanlışlıkla indirildiyse silin (`.gitignore` listelenir).
- Engine + panel birlikte denemek için `.env` içinde `ENGINE_API_URL` ve `ENGINE_INTERNAL_KEY` kullanın.
