-- =============================================================================
-- Hostvim landing — nav_menu_items çok dilli sütunlar (MySQL / MariaDB, utf8mb4)
-- =============================================================================
--
-- #1054 Unknown column 'label_en' → ADIM 1’deki ALTER’ın başındaki -- kaldırıp bir kez çalıştırın, sonra ADIM 2.
-- #1060 Duplicate column 'label_en' → Sütunlar tamam; ADIM 1’i atlayın, yalnız ADIM 2 UPDATE’leri çalıştırın.
-- phpMyAdmin’de mümkünse blok blok çalıştırın.
--
-- =============================================================================
-- ADIM 1 — MEVCUT TABLO (eski şema, label_en / href_en yok) — GEREKİYORSA BUNU ÇALIŞTIRIN
-- =============================================================================
-- #1060 Duplicate column name 'label_en' → NORMAL: sütunlar zaten ekli.
--   Hiçbir şey yapmayın, ADIM 1’i ATLAYIN, doğrudan ADIM 2 UPDATE’lerini çalıştırın.
-- #1054 Unknown column → ADIM 1 ALTER’ı bir kez çalıştırın, sonra ADIM 2.

-- ALTER TABLE `nav_menu_items`
--   ADD COLUMN `label_en` varchar(255) NULL DEFAULT NULL AFTER `label`,
--   ADD COLUMN `href_en` varchar(2048) NULL DEFAULT NULL AFTER `href`;

-- =============================================================================
-- ADIM 2 — Bilinen href’ler için İngilizce etiket (yalnız label_en boşsa)
-- =============================================================================
-- ADIM 1 başarılı olduktan sonra çalıştırın.

UPDATE `nav_menu_items` SET `label_en` = 'Features' WHERE `href` = '/#features' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'Pricing' WHERE `href` = '/pricing' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'Installation' WHERE `href` = '/setup' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'Documentation' WHERE `href` = '/docs' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'Blog' WHERE `href` = '/blog' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'FAQ' WHERE `href` = '/#faq' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'Admin login' WHERE `href` = '/admin/login' AND (`label_en` IS NULL OR `label_en` = '');

UPDATE `nav_menu_items` SET `label_en` = 'Privacy notice' WHERE `href` = '/p/kvkk' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'Privacy policy' WHERE `href` = '/p/gizlilik-politikasi' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'Cookie policy' WHERE `href` = '/p/cerez-politikasi' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'Distance sales' WHERE `href` = '/p/mesafeli-satis' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'Terms of use' WHERE `href` = '/p/kullanim-kosullari' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'SLA' WHERE `href` = '/p/sla' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'Refunds' WHERE `href` = '/p/iade-ve-iptal' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'Data centre' WHERE `href` = '/p/veri-merkezi' AND (`label_en` IS NULL OR `label_en` = '');
UPDATE `nav_menu_items` SET `label_en` = 'Customer agreement' WHERE `href` = '/p/musteri-sozlesmesi' AND (`label_en` IS NULL OR `label_en` = '');

-- =============================================================================
-- ADIM 3 — Tablo hiç yoksa (yeni kurulum) — ADIM 1 ile birlikte kullanmayın
-- =============================================================================
-- Aşağıdaki CREATE yalnızca nav_menu_items tablosu veritabanında yoksa çalıştırın.
-- Tablo zaten varken CREATE çalıştırmayın (IF NOT EXISTS eski şemayı yükseltmez).

/*
CREATE TABLE `nav_menu_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `zone` varchar(32) NOT NULL,
  `label` varchar(255) NOT NULL,
  `label_en` varchar(255) DEFAULT NULL,
  `href` varchar(2048) NOT NULL,
  `href_en` varchar(2048) DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `open_in_new_tab` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nav_menu_items_zone_is_active_sort_order_index` (`zone`,`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/

-- =============================================================================
-- Geri alma — yalnızca EN sütunlarını kaldırmak (isteğe bağlı)
-- =============================================================================
-- ALTER TABLE `nav_menu_items` DROP COLUMN `label_en`, DROP COLUMN `href_en`;
