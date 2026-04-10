-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: localhost    Database: hostvim_landing_full_dump
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `blog_categories`
--

DROP TABLE IF EXISTS `blog_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `locale` varchar(10) NOT NULL DEFAULT 'tr',
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_categories_locale_slug_unique` (`locale`,`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_categories`
--

LOCK TABLES `blog_categories` WRITE;
/*!40000 ALTER TABLE `blog_categories` DISABLE KEYS */;
INSERT INTO `blog_categories` VALUES (1,'tr','hosting-migration','Hosting ve geçiş','Hosting ve geçiş — Hostvim blog','Paylaşımlı hostingden çıkış, sunucu taşıma ve panel geçişi üzerine yazılar.',10,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(2,'en','hosting-migration','Hosting & migration','Hosting & migration — Hostvim blog','Moving off shared hosting, server migrations, and panel transitions.',10,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(3,'tr','security','Güvenlik','Güvenlik — Hostvim blog','Panel ve sunucu güvenliği, erişim ve sertifika konuları.',20,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(4,'en','security','Security','Security — Hostvim blog','Panel and server security, access control, and certificates.',20,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(5,'tr','scaling','Ölçeklendirme','Ölçeklendirme ve mimari — Hostvim blog','Tek sunucudan çoklu düzene geçiş ve mimari notları.',30,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(6,'en','scaling','Scaling','Scaling & architecture — Hostvim blog','Growing from one server to multi-node setups.',30,'2026-04-10 09:47:46','2026-04-10 09:47:46');
/*!40000 ALTER TABLE `blog_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_posts`
--

DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `locale` varchar(10) NOT NULL DEFAULT 'tr',
  `blog_category_id` bigint(20) unsigned DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `canonical_url` varchar(2048) DEFAULT NULL,
  `og_image` varchar(2048) DEFAULT NULL,
  `robots` varchar(64) DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_posts_locale_slug_unique` (`locale`,`slug`),
  KEY `blog_posts_blog_category_id_foreign` (`blog_category_id`),
  KEY `blog_posts_locale_pub_date` (`locale`,`is_published`,`published_at`),
  CONSTRAINT `blog_posts_blog_category_id_foreign` FOREIGN KEY (`blog_category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_posts`
--

LOCK TABLES `blog_posts` WRITE;
/*!40000 ALTER TABLE `blog_posts` DISABLE KEYS */;
INSERT INTO `blog_posts` VALUES (1,'tr',1,'from-shared-hosting','Shared hosting’den kendi panelime',NULL,NULL,NULL,NULL,NULL,'Klasik paylaşımlı hostingden çıkıp kendi sunucunuzda Hostvim ile nasıl ilerlersiniz?','Paylaşımlı hosting uzun yıllar işinizi görür; ta ki tek panelden onlarca siteyi yönetme ihtiyacı doğana kadar.\n\n## Geçiş stratejisi\n\n1. **DNS TTL** düşürün; taşıma günü kesintiyi azaltır.\n2. Veritabanını **mysqldump** veya panel araçlarıyla alın.\n3. Dosyaları **rsync** ile senkronize edin.\n4. Hostvim’de site sihirbazını çalıştırıp SSL’i doğrulayın.\n\nKüçük projelerde önce staging subdomain ile test etmek riski ciddi şekilde azaltır.','2026-04-05 09:47:46',1,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(2,'en',2,'from-shared-hosting','From shared hosting to your own panel',NULL,NULL,NULL,NULL,NULL,'How to move from classic shared hosting to Hostvim on your own server.','Shared hosting works for years — until you need to run many sites from one panel.\n\n## Migration strategy\n\n1. Lower **DNS TTL** to reduce cutover pain.\n2. Export the database with **mysqldump** or your tools.\n3. Sync files with **rsync**.\n4. Run the Hostvim site wizard and verify TLS.\n\nFor smaller projects, test on a staging subdomain first.','2026-04-05 09:47:46',1,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(3,'tr',3,'panel-security-basics','Panel güvenliğinde temel hatalar',NULL,NULL,NULL,NULL,NULL,'Yönetim arayüzünü internete açarken sık yapılan hatalar ve pratik önlemler.','Panel URL’sini herkese açık bırakmak yerine:\n\n- **İki faktörlü doğrulama** kullanın\n- Yönetim yolunu **rate limit** ile koruyun\n- Varsayılan portları değiştirin veya **VPN** arkasına alın\n\nHostvim yönetim hesapları için güçlü şifre politikası ve oturum süresi sınırları önerilir.','2026-04-07 09:47:46',1,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(4,'en',4,'panel-security-basics','Common panel security mistakes',NULL,NULL,NULL,NULL,NULL,'Typical pitfalls when exposing an admin UI to the internet — and practical fixes.','Before leaving the panel URL wide open:\n\n- Enable **two-factor authentication**\n- Protect admin routes with **rate limiting**\n- Change default ports or place the panel behind a **VPN**\n\nStrong password policy and session limits are recommended for Hostvim admin accounts.','2026-04-07 09:47:46',1,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(5,'tr',5,'single-server-to-cluster','Tek sunucudan çoklu cluster’a',NULL,NULL,NULL,NULL,NULL,'Büyüdükçe mimariyi nasıl parçalayabilirsiniz?','İlk aşamada tek sunucu yeterlidir. Trafik ve ekip büyüdükçe:\n\n- Veritabanını ayrı bir **DB host**’a taşıyın\n- Statik ve medya için **CDN** ekleyin\n- Engine örneklerini **load balancer** arkasında çoğaltın\n\nHostvim bu aşamalarda aynı panel üzerinden çoklu sunucu yönetimini hedefler; roadmap’i ürün duyurularından takip edin.','2026-04-09 09:47:46',1,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(6,'en',6,'single-server-to-cluster','From one server to a multi-node setup',NULL,NULL,NULL,NULL,NULL,'How to split the architecture as you grow.','A single server is enough at first. As traffic and teams grow:\n\n- Move the database to a dedicated **DB host**\n- Add a **CDN** for static assets and media\n- Run multiple Engine instances behind a **load balancer**\n\nHostvim aims to manage multiple servers from the same panel over time — follow product announcements for the roadmap.','2026-04-09 09:47:46',1,'2026-04-10 09:47:46','2026-04-10 09:47:46');
/*!40000 ALTER TABLE `blog_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `community_categories`
--

DROP TABLE IF EXISTS `community_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `community_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `robots_override` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `community_categories_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `community_categories`
--

LOCK TABLES `community_categories` WRITE;
/*!40000 ALTER TABLE `community_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `community_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `community_posts`
--

DROP TABLE IF EXISTS `community_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `community_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `community_topic_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `body` longtext NOT NULL,
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `moderation_status` varchar(24) NOT NULL DEFAULT 'approved',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `community_posts_user_id_foreign` (`user_id`),
  KEY `community_posts_community_topic_id_is_hidden_index` (`community_topic_id`,`is_hidden`),
  KEY `community_posts_topic_vis_mod` (`community_topic_id`,`is_hidden`,`moderation_status`),
  CONSTRAINT `community_posts_community_topic_id_foreign` FOREIGN KEY (`community_topic_id`) REFERENCES `community_topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `community_posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `community_posts`
--

LOCK TABLES `community_posts` WRITE;
/*!40000 ALTER TABLE `community_posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `community_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `community_site_meta`
--

DROP TABLE IF EXISTS `community_site_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `community_site_meta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `site_title` varchar(255) NOT NULL DEFAULT 'Community',
  `default_meta_title` varchar(255) DEFAULT NULL,
  `default_meta_description` text DEFAULT NULL,
  `og_image_url` varchar(255) DEFAULT NULL,
  `twitter_site` varchar(255) DEFAULT NULL,
  `enable_indexing` tinyint(1) NOT NULL DEFAULT 1,
  `moderation_new_topics` tinyint(1) NOT NULL DEFAULT 0,
  `moderation_new_posts` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `community_site_meta`
--

LOCK TABLES `community_site_meta` WRITE;
/*!40000 ALTER TABLE `community_site_meta` DISABLE KEYS */;
/*!40000 ALTER TABLE `community_site_meta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `community_tag_topic`
--

DROP TABLE IF EXISTS `community_tag_topic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `community_tag_topic` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `community_topic_id` bigint(20) unsigned NOT NULL,
  `community_tag_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_tag_unique` (`community_topic_id`,`community_tag_id`),
  KEY `community_tag_topic_community_tag_id_foreign` (`community_tag_id`),
  CONSTRAINT `community_tag_topic_community_tag_id_foreign` FOREIGN KEY (`community_tag_id`) REFERENCES `community_tags` (`id`) ON DELETE CASCADE,
  CONSTRAINT `community_tag_topic_community_topic_id_foreign` FOREIGN KEY (`community_topic_id`) REFERENCES `community_topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `community_tag_topic`
--

LOCK TABLES `community_tag_topic` WRITE;
/*!40000 ALTER TABLE `community_tag_topic` DISABLE KEYS */;
/*!40000 ALTER TABLE `community_tag_topic` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `community_tags`
--

DROP TABLE IF EXISTS `community_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `community_tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `community_tags_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `community_tags`
--

LOCK TABLES `community_tags` WRITE;
/*!40000 ALTER TABLE `community_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `community_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `community_topics`
--

DROP TABLE IF EXISTS `community_topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `community_topics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `community_category_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `excerpt` varchar(600) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'published',
  `moderation_status` varchar(24) NOT NULL DEFAULT 'approved',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_solved` tinyint(1) NOT NULL DEFAULT 0,
  `best_answer_post_id` bigint(20) unsigned DEFAULT NULL,
  `view_count` int(10) unsigned NOT NULL DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `robots_override` varchar(255) DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `community_topics_slug_unique` (`slug`),
  KEY `community_topics_user_id_foreign` (`user_id`),
  KEY `community_topics_community_category_id_status_index` (`community_category_id`,`status`),
  KEY `community_topics_last_activity_at_index` (`last_activity_at`),
  KEY `community_topics_best_answer_post_id_foreign` (`best_answer_post_id`),
  KEY `community_topics_pub_mod_activity` (`status`,`moderation_status`,`last_activity_at`),
  CONSTRAINT `community_topics_best_answer_post_id_foreign` FOREIGN KEY (`best_answer_post_id`) REFERENCES `community_posts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `community_topics_community_category_id_foreign` FOREIGN KEY (`community_category_id`) REFERENCES `community_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `community_topics_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `community_topics`
--

LOCK TABLES `community_topics` WRITE;
/*!40000 ALTER TABLE `community_topics` DISABLE KEYS */;
/*!40000 ALTER TABLE `community_topics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doc_pages`
--

DROP TABLE IF EXISTS `doc_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `doc_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `locale` varchar(10) NOT NULL DEFAULT 'tr',
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `doc_pages_locale_slug_unique` (`locale`,`slug`),
  KEY `doc_pages_parent_id_foreign` (`parent_id`),
  KEY `doc_pages_locale_pub_sort` (`locale`,`is_published`,`sort_order`),
  CONSTRAINT `doc_pages_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `doc_pages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doc_pages`
--

LOCK TABLES `doc_pages` WRITE;
/*!40000 ALTER TABLE `doc_pages` DISABLE KEYS */;
INSERT INTO `doc_pages` VALUES (1,'tr',NULL,'getting-started','Başlangıç',NULL,NULL,'# Hostvim dokümantasyonu\n\nBu bölümde kurulum, mimari ve tipik kullanım senaryolarına dair rehberler bulunur.\n\nSol menüden alt başlıklara geçebilir veya doğrudan ilgili sayfaların bağlantılarını kullanabilirsiniz.',0,1,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(2,'en',NULL,'getting-started','Getting started',NULL,NULL,'# Hostvim documentation\n\nHere you will find guides for installation, architecture, and common workflows.\n\nUse the sidebar for nested pages or follow direct links from the docs home.',0,1,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(3,'tr',1,'server-setup','Sunucu kurulumu',NULL,NULL,'## Adımlar\n\n1. Sunucuda güncellemeleri alın: `apt update && apt upgrade -y`\n2. Hostvim bootstrap betiğini çalıştırın (resmi komut dokümantasyonda).\n3. Firewall’da **80**, **443** ve panel için kullanılan portu açın.\n4. İlk girişte yönetici e-postası ve güçlü bir şifre belirleyin.\n\n## Engine ve panel\n\nEngine sistem servislerini yönetir; panel Laravel tabanlı arayüzdür. İkisi arasında TLS ve API anahtarları ile güvenli iletişim kurulur.',10,1,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(4,'en',2,'server-setup','Server setup',NULL,NULL,'## Steps\n\n1. Update the server: `apt update && apt upgrade -y`\n2. Run the Hostvim bootstrap script (official command is in the docs).\n3. Open **80**, **443**, and the panel port in your firewall.\n4. On first login, set admin email and a strong password.\n\n## Engine and panel\n\nThe Engine manages system services; the panel is a Laravel-based UI. They communicate over TLS using API keys.',10,1,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(5,'tr',NULL,'architecture','Mimari genel bakış',NULL,NULL,'## Bileşenler\n\n- **Engine (Go)**: konteyner veya sistem servisi olarak çalışır; Nginx sanal host, PHP-FPM havuzu ve sertifika işlemlerini uygular.\n- **Panel (Laravel)**: kullanıcı, site ve lisans yönetimi; Engine ile REST/WebSocket üzerinden konuşur.\n- **Veritabanları**: MySQL/MariaDB veya PostgreSQL; panel üzerinden kullanıcı bazlı yetkilendirme.\n\nBu yapı sayesinde paneli güncellerken engine sürümünü bağımsız tutabilirsiniz.',5,1,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(6,'en',NULL,'architecture','Architecture overview',NULL,NULL,'## Components\n\n- **Engine (Go)**: runs as a container or system service; applies Nginx vhosts, PHP-FPM pools, and certificates.\n- **Panel (Laravel)**: users, sites, and licensing; talks to the Engine over REST/WebSocket.\n- **Databases**: MySQL/MariaDB or PostgreSQL with per-user authorization from the panel.\n\nYou can upgrade the panel and Engine on independent cadences.',5,1,'2026-04-10 09:47:46','2026-04-10 09:47:46');
/*!40000 ALTER TABLE `doc_pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `landing_site_settings`
--

DROP TABLE IF EXISTS `landing_site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `landing_site_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `landing_site_settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `landing_site_settings`
--

LOCK TABLES `landing_site_settings` WRITE;
/*!40000 ALTER TABLE `landing_site_settings` DISABLE KEYS */;
INSERT INTO `landing_site_settings` VALUES (1,'landing.default_locale','tr','2026-04-10 09:47:46','2026-04-10 09:47:46'),(2,'landing.enabled_locales','[\"tr\",\"en\"]','2026-04-10 09:47:46','2026-04-10 09:47:46'),(3,'landing.site_name','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(4,'landing.site_tagline','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(5,'landing.site_logo_path','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(6,'landing.site_logo_max_height_px','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(7,'landing.site_logo_max_width_px','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(8,'landing.site_logo_footer_max_height_px','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(9,'landing.site_logo_footer_max_width_px','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(10,'landing.favicon_path','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(11,'landing.contact_email','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(12,'landing.social_twitter_url','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(13,'landing.social_github_url','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(14,'landing.social_linkedin_url','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(15,'landing.analytics_ga4_id','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(16,'landing.footer_extra_note','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(17,'landing.active_theme','orange','2026-04-10 09:47:46','2026-04-10 09:47:46'),(18,'landing.graphic_motif','grid','2026-04-10 09:47:46','2026-04-10 09:47:46'),(19,'landing.theme_primary_hex','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(20,'landing.hero_image_path','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(21,'landing.hero_image_alt','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(22,'landing.hero_image_caption','','2026-04-10 09:47:46','2026-04-10 09:47:46'),(23,'landing.page_overrides','{}','2026-04-10 09:47:46','2026-04-10 09:47:46'),(24,'landing.home_feature_cards','[]','2026-04-10 09:47:46','2026-04-10 09:47:46');
/*!40000 ALTER TABLE `landing_site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `landing_translations`
--

DROP TABLE IF EXISTS `landing_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `landing_translations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `locale` varchar(16) NOT NULL,
  `key` varchar(191) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `landing_translations_locale_key_unique` (`locale`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `landing_translations`
--

LOCK TABLES `landing_translations` WRITE;
/*!40000 ALTER TABLE `landing_translations` DISABLE KEYS */;
/*!40000 ALTER TABLE `landing_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_04_03_110437_add_is_admin_to_users_table',1),(5,'2026_04_03_112231_create_blog_posts_table',1),(6,'2026_04_03_112231_create_doc_pages_table',1),(7,'2026_04_03_112231_create_plans_table',1),(8,'2026_04_03_112231_create_site_pages_table',1),(9,'2026_04_03_200000_create_landing_site_settings_table',1),(10,'2026_04_03_200001_create_landing_translations_table',1),(11,'2026_04_04_120000_create_nav_menu_items_table',1),(12,'2026_04_04_140000_create_blog_categories_and_seo_fields',1),(13,'2026_04_05_120000_add_site_page_full_seo_fields',1),(14,'2026_04_06_100000_add_locale_and_english_slugs_to_content',1),(15,'2026_04_07_120000_create_hostvim_saas_tables',1),(16,'2026_04_08_100000_saas_checkout_and_product_prices',1),(17,'2026_04_08_160000_create_community_tables',1),(18,'2026_04_08_180000_add_community_moderation_to_users',1),(19,'2026_04_08_200000_community_moderation_tags_avatar',1),(20,'2026_04_10_120000_add_listing_performance_indexes',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `nav_menu_items`
--

DROP TABLE IF EXISTS `nav_menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nav_menu_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `zone` varchar(32) NOT NULL,
  `label` varchar(255) NOT NULL,
  `href` varchar(2048) NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `open_in_new_tab` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nav_menu_items_zone_is_active_sort_order_index` (`zone`,`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nav_menu_items`
--

LOCK TABLES `nav_menu_items` WRITE;
/*!40000 ALTER TABLE `nav_menu_items` DISABLE KEYS */;
INSERT INTO `nav_menu_items` VALUES (1,'header','Özellikler','/#features',0,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(2,'header','Fiyatlandırma','/pricing',1,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(3,'header','Kurulum','/setup',2,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(4,'header','Dokümantasyon','/docs',3,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(5,'header','Blog','/blog',4,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(6,'header','SSS','/#faq',5,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(7,'footer','Dokümantasyon','/docs',0,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(8,'footer','Blog','/blog',1,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(9,'footer','SSS','/#faq',2,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(10,'footer','Yönetim girişi','/admin/login',3,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(11,'footer','KVKK','/p/kvkk',100,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(12,'footer','Gizlilik','/p/gizlilik-politikasi',101,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(13,'footer','Çerezler','/p/cerez-politikasi',102,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(14,'footer','Mesafeli satış','/p/mesafeli-satis',103,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(15,'footer','Kullanım koşulları','/p/kullanim-kosullari',104,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(16,'footer','SLA','/p/sla',105,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(17,'footer','İade ve iptal','/p/iade-ve-iptal',106,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(18,'footer','Veri merkezi','/p/veri-merkezi',107,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(19,'footer','Müşteri sözleşmesi','/p/musteri-sozlesmesi',108,1,0,'2026-04-10 09:47:46','2026-04-10 09:47:46');
/*!40000 ALTER TABLE `nav_menu_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `price_label` varchar(255) NOT NULL,
  `price_note` varchar(255) DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plans_slug_unique` (`slug`),
  KEY `plans_active_sort` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plans`
--

LOCK TABLES `plans` WRITE;
/*!40000 ALTER TABLE `plans` DISABLE KEYS */;
INSERT INTO `plans` VALUES (1,'Freemium','freemium','Tek sunucu için sınırlı ama yeterli özellikler','₺0','/ay','[\"1 sunucu\",\"Temel site ve domain y\\u00f6netimi\",\"Otomatik SSL (Let\'s Encrypt)\",\"S\\u0131n\\u0131rl\\u0131 log ve terminal eri\\u015fimi\"]',10,0,1,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(2,'Pro Lisans','pro-lisans','Ajanslar ve yoğun trafik için','₺?','/ay · sunucu başına','[\"S\\u0131n\\u0131rs\\u0131z site ve domain\",\"Geli\\u015fmi\\u015f g\\u00fcvenlik profilleri\",\"Detayl\\u0131 metrikler ve health checks\",\"\\u00d6ncelikli destek\"]',20,1,1,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(3,'Vendor / White-label','vendor','Kendi markanızla sunmak isteyen paneller için','Özel','teklif','[\"\\u00d6zel fiyatland\\u0131rma ve SLA\",\"Marka \\u00f6zelle\\u015ftirme\",\"Roadmap i\\u015f birli\\u011fi\"]',30,0,1,'2026-04-10 09:47:46','2026-04-10 09:47:46');
/*!40000 ALTER TABLE `plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saas_checkout_orders`
--

DROP TABLE IF EXISTS `saas_checkout_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saas_checkout_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_ref` varchar(64) NOT NULL,
  `provider` varchar(16) NOT NULL,
  `locale` varchar(10) NOT NULL DEFAULT 'en',
  `email` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `saas_license_product_id` bigint(20) unsigned NOT NULL,
  `amount_minor` int(10) unsigned NOT NULL,
  `currency` varchar(8) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `stripe_checkout_session_id` varchar(255) DEFAULT NULL,
  `saas_license_id` bigint(20) unsigned DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `failure_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saas_checkout_orders_order_ref_unique` (`order_ref`),
  UNIQUE KEY `saas_checkout_orders_stripe_checkout_session_id_unique` (`stripe_checkout_session_id`),
  KEY `saas_checkout_orders_saas_license_product_id_foreign` (`saas_license_product_id`),
  KEY `saas_checkout_orders_saas_license_id_foreign` (`saas_license_id`),
  CONSTRAINT `saas_checkout_orders_saas_license_id_foreign` FOREIGN KEY (`saas_license_id`) REFERENCES `saas_licenses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `saas_checkout_orders_saas_license_product_id_foreign` FOREIGN KEY (`saas_license_product_id`) REFERENCES `saas_license_products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saas_checkout_orders`
--

LOCK TABLES `saas_checkout_orders` WRITE;
/*!40000 ALTER TABLE `saas_checkout_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `saas_checkout_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saas_customers`
--

DROP TABLE IF EXISTS `saas_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saas_customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `saas_customers_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saas_customers`
--

LOCK TABLES `saas_customers` WRITE;
/*!40000 ALTER TABLE `saas_customers` DISABLE KEYS */;
/*!40000 ALTER TABLE `saas_customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saas_license_products`
--

DROP TABLE IF EXISTS `saas_license_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saas_license_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `default_limits` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`default_limits`)),
  `default_modules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`default_modules`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `price_try_minor` int(10) unsigned DEFAULT NULL,
  `price_usd_minor` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saas_license_products_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saas_license_products`
--

LOCK TABLES `saas_license_products` WRITE;
/*!40000 ALTER TABLE `saas_license_products` DISABLE KEYS */;
INSERT INTO `saas_license_products` VALUES (1,'community','Hostvim Community','Freemium barındırma paneli','{\"max_sites\":5}','{\"vendor_panel\":false,\"backups_pro\":false,\"monitoring_advanced\":false,\"ai_advisor\":false,\"stripe_billing\":false}',1,0,NULL,NULL,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(2,'pro','Hostvim Pro','Tam özellik + vendor','{\"max_sites\":500}','{\"vendor_panel\":true,\"backups_pro\":true,\"monitoring_advanced\":true,\"ai_advisor\":true,\"stripe_billing\":true}',1,10,199900,19900,'2026-04-10 09:47:46','2026-04-10 09:47:46');
/*!40000 ALTER TABLE `saas_license_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saas_licenses`
--

DROP TABLE IF EXISTS `saas_licenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saas_licenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `license_key` varchar(80) NOT NULL,
  `saas_customer_id` bigint(20) unsigned NOT NULL,
  `saas_license_product_id` bigint(20) unsigned NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `starts_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `limits_override` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`limits_override`)),
  `modules_override` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`modules_override`)),
  `subscription_status` varchar(32) DEFAULT NULL,
  `subscription_renews_at` timestamp NULL DEFAULT NULL,
  `billing_provider` varchar(255) DEFAULT NULL,
  `billing_reference` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saas_licenses_license_key_unique` (`license_key`),
  KEY `saas_licenses_saas_customer_id_foreign` (`saas_customer_id`),
  KEY `saas_licenses_saas_license_product_id_foreign` (`saas_license_product_id`),
  KEY `saas_licenses_status_expires` (`status`,`expires_at`),
  CONSTRAINT `saas_licenses_saas_customer_id_foreign` FOREIGN KEY (`saas_customer_id`) REFERENCES `saas_customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saas_licenses_saas_license_product_id_foreign` FOREIGN KEY (`saas_license_product_id`) REFERENCES `saas_license_products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saas_licenses`
--

LOCK TABLES `saas_licenses` WRITE;
/*!40000 ALTER TABLE `saas_licenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `saas_licenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saas_product_modules`
--

DROP TABLE IF EXISTS `saas_product_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saas_product_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(64) NOT NULL,
  `label` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saas_product_modules_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saas_product_modules`
--

LOCK TABLES `saas_product_modules` WRITE;
/*!40000 ALTER TABLE `saas_product_modules` DISABLE KEYS */;
INSERT INTO `saas_product_modules` VALUES (1,'vendor_panel','Vendor kontrol düzlemi',NULL,1,1,10,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(2,'backups_pro','Gelişmiş yedekleme',NULL,1,1,20,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(3,'monitoring_advanced','Gelişmiş izleme',NULL,1,1,30,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(4,'ai_advisor','AI danışman',NULL,1,1,40,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(5,'stripe_billing','Stripe faturalama entegrasyonu',NULL,1,1,50,'2026-04-10 09:47:46','2026-04-10 09:47:46');
/*!40000 ALTER TABLE `saas_product_modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_pages`
--

DROP TABLE IF EXISTS `site_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `locale` varchar(10) NOT NULL DEFAULT 'tr',
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `canonical_url` varchar(2048) DEFAULT NULL,
  `og_image` varchar(2048) DEFAULT NULL,
  `robots` varchar(64) DEFAULT NULL,
  `content` longtext NOT NULL,
  `meta_description` text DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_pages_locale_slug_unique` (`locale`,`slug`),
  KEY `site_pages_locale_pub_sort` (`locale`,`is_published`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_pages`
--

LOCK TABLES `site_pages` WRITE;
/*!40000 ALTER TABLE `site_pages` DISABLE KEYS */;
INSERT INTO `site_pages` VALUES (1,'tr','setup','Kurulum rehberi',NULL,NULL,NULL,NULL,'## Genel bakış\n\nHostvim, Linux sunucunuzda **Nginx**, **PHP-FPM** ve veritabanlarını tek panelden yönetmenizi sağlar. Kurulum iki ana bileşenden oluşur:\n\n1. **Hostvim Engine** — sunucu tarafı servisler ve yapılandırma\n2. **Panel** — web arayüzü ve API\n\n## Ön koşullar\n\n- Temiz bir **Ubuntu 22.04 LTS** veya üretim ekibinizin desteklediği bir dağıtım\n- **root** veya `sudo` yetkisi\n- Alan adınızın DNS kayıtlarının sunucuya işaret etmesi (SSL için)\n\n## Tek satır kurulum (örnek)\n\nSunucuda aşağıdaki komut, bootstrap betiğini indirip çalıştırır:\n\n```bash\ncurl -fsSL https://get.hostvim.sh | bash\n```\n\n> Üretim ortamında betiği çalıştırmadan önce resmi dokümantasyondaki checksum ve imza doğrulamasını uygulayın.\n\n## Kurulum sonrası\n\n- Panel URL’nizi tarayıcıda açın ve ilk yönetici hesabını oluşturun.\n- Engine ile panel arasındaki API anahtarlarını `.env` üzerinden eşleştirin.\n- İlk site oluşturma sihirbazı ile bir **test domain** üzerinde doğrulama yapın.\n\nSorularınız için [dokümantasyon](/docs) ve [blog](/blog) sayfalarına göz atın.','Hostvim panelini sunucunuza kurmak için adım adım rehber.',1,10,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(2,'en','setup','Installation guide',NULL,NULL,NULL,NULL,'## Overview\n\nHostvim lets you manage **Nginx**, **PHP-FPM**, and databases from one panel on your Linux server. Installation has two main parts:\n\n1. **Hostvim Engine** — server-side services and configuration\n2. **Panel** — web UI and API\n\n## Prerequisites\n\n- A clean **Ubuntu 22.04 LTS** or a distribution your operations team supports\n- **root** or `sudo`\n- DNS pointing your domain at the server (for SSL)\n\n## One-line install (example)\n\nOn the server, the following downloads and runs the bootstrap script:\n\n```bash\ncurl -fsSL https://get.hostvim.sh | bash\n```\n\n> In production, verify checksums and signatures from the official documentation before running the script.\n\n## After install\n\n- Open the panel URL and create the first admin account.\n- Match API keys between Engine and panel in `.env`.\n- Use the site wizard on a **test domain** to validate the stack.\n\nSee [documentation](/docs) and the [blog](/blog) for more.','Step-by-step guide to installing the Hostvim panel on your server.',1,10,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(3,'tr','pricing','Fiyatlandırma özeti',NULL,NULL,NULL,NULL,'Bu sayfa, **fiyatlandırma** ekranındaki giriş metnini besler. Plan kartları veritabanındaki kayıtlardan otomatik oluşturulur.\n\n- **Freemium**: tek sunucu ve temel özelliklerle başlayın.\n- **Pro**: ajans ve yüksek trafik senaryoları için genişletilmiş limitler.\n- **Vendor**: white-label ve kurumsal SLA için bizimle iletişime geçin.\n\nDetaylı limit tabloları panel içi lisans ekranında güncellenir.','Hostvim freemium ve lisanslı planlar hakkında kısa özet.',1,20,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(4,'en','pricing','Pricing overview',NULL,NULL,NULL,NULL,'This page feeds the intro text on the **pricing** screen. Plan cards are built from database records.\n\n- **Freemium**: start with one server and core features.\n- **Pro**: higher limits for agencies and busy workloads.\n- **Vendor**: white-label and enterprise SLA — contact us.\n\nDetailed limits are maintained in the in-panel licensing screen.','Short overview of Hostvim freemium and licensed plans.',1,20,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(5,'tr','kvkk','KVKK Aydınlatma Metni',NULL,NULL,NULL,NULL,'> **Önemli:** Bu metin bilgilendirme amaçlı şablondur. Şirket unvanı, adres, iletişim, ürün ve ödeme modelinize göre **mutlaka bir hukuk danışmanı** tarafından gözden geçirilmelidir.\n\n## Veri sorumlusu\n\n**[TİCARİ ÜNVAN]** (bundan böyle “Şirket”), 6698 sayılı Kişisel Verilerin Korunması Kanunu (“KVKK”) kapsamında veri sorumlusudur.\n\n- **Adres:** [ADRES]\n- **E-posta:** [E-POSTA]\n- **MERSİS:** [MERSİS NO] · **Vergi no:** [VERGİ KİMLİK / NO]\n\n## İşlenen kişisel veriler\n\nÖrnek kategoriler: kimlik / iletişim (ad, soyad, e-posta, telefon), müşteri işlem (sipariş, fatura, ödeme kaydı özetleri), teknik loglar (IP, tarayıcı, cihaz bilgisi, tarih-saat), destek talebi içerikleri, pazarlama izinleri (varsa).\n\n## İşleme amaçları\n\nHizmetin sunulması ve sözleşmenin ifası; müşteri desteği; faturalandırma ve muhasebe; güvenlik ve kötüye kullanımın önlenmesi; yasal yükümlülüklerin yerine getirilmesi; (açık rızanız varsa) pazarlama ve iletişim.\n\n## Hukuki sebepler\n\nKVKK m.5/2 (c) sözleşmenin kurulması veya ifası; (ç) veri sorumlusunun hukuki yükümlülüğü; (f) meşru menfaat; (a) açık rıza (pazarlama çerezleri / bülten vb. için).\n\n## Aktarım\n\nHizmetin gerektirdiği ölçüde; barındırma / ödeme / e-posta sağlayıcıları gibi **hizmet sağlayıcılarına** (yurt içi/yurt dışı, KVKK ve sözleşmelere uygun) aktarım yapılabilir. Yurt dışına aktarımda KVKK’da öngörülen şartlar uygulanır.\n\n## Saklama süresi\n\nİlgili mevzuatta öngörülen süreler ve meşru menfaat / sözleşme gereği gerekli süre boyunca; süre sonunda silme, yok etme veya anonimleştirme.\n\n## Haklarınız\n\nKVKK m.11 kapsamında; verilerinizin işlenip işlenmediğini öğrenme, bilgi talep etme, düzeltme/silme, itiraz, zararın giderilmesi talebi vb. **[E-POSTA]** üzerinden başvurabilirsiniz. Şikâyet için Kişisel Verileri Koruma Kurulu’na başvuru hakkınız saklıdır.\n\n**Son güncelleme:** 2026-04-10','6698 sayılı KVKK kapsamında kişisel verilerin işlenmesine ilişkin aydınlatma.',1,31,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(6,'tr','gizlilik-politikasi','Gizlilik Politikası',NULL,NULL,NULL,NULL,'> **Önemli:** Bu metin bilgilendirme amaçlı şablondur. Şirket unvanı, adres, iletişim, ürün ve ödeme modelinize göre **mutlaka bir hukuk danışmanı** tarafından gözden geçirilmelidir.\n\nBu politika, **Hostvim** markası altında sunulan web sitesi, demo, iletişim formları ve bağlantılı dijital hizmetler için geçerlidir.\n\n## Toplanan bilgiler\n\nFormlar, hesap oluşturma, destek talepleri, çerezler ve sunucu logları aracılığıyla toplanan veriler (kimlik/iletişim, teknik veriler, kullanım istatistikleri).\n\n## Kullanım amaçları\n\nHizmet sunumu, güvenlik, analitik (anonim/aggregate), iletişim, yasal uyum.\n\n## Üçüncü taraflar\n\nBarındırma, CDN, analitik, ödeme ve e-posta sağlayıcıları. Listeler sözleşme ekinde veya talep üzerine güncellenir.\n\n## Güvenlik\n\nŞifreleme (TLS), erişim kontrolleri ve sınırlı yetkilendirme prensipleri uygulanır; mutlak güvenlik taahhüdü verilmez.\n\n## Haklar ve iletişim\n\nKVKK başvuruları **[E-POSTA]** üzerinden. Politika güncellenebilir; önemli değişiklikler sitede duyurulur.\n\n**Son güncelleme:** 2026-04-10','Web sitesi ve hizmet kullanımında kişisel verilerin korunması.',1,32,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(7,'tr','cerez-politikasi','Çerez Politikası',NULL,NULL,NULL,NULL,'> **Önemli:** Bu metin bilgilendirme amaçlı şablondur. Şirket unvanı, adres, iletişim, ürün ve ödeme modelinize göre **mutlaka bir hukuk danışmanı** tarafından gözden geçirilmelidir.\n\n## Çerez nedir?\n\nÇerezler, cihazınıza kaydedilen küçük metin dosyalarıdır.\n\n## Kullandığımız çerez türleri\n\n- **Zorunlu:** Oturum, güvenlik, dil tercihi.\n- **İşlevsel:** Form ve tercih hatırlama.\n- **Analitik:** Ziyaret istatistikleri (anonimleştirilmiş olabilir).\n- **Pazarlama:** (Yalnızca açık rıza ile) yeniden pazarlama.\n\n## Yönetim\n\nTarayıcı ayarlarından çerezleri silebilir veya engelleyebilirsiniz. Zorunlu çerezleri kapatmak bazı özellikleri etkileyebilir.\n\n**Son güncelleme:** 2026-04-10','Çerez türleri, amaçları ve tercih yönetimi.',1,33,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(8,'tr','mesafeli-satis','Mesafeli Satış Sözleşmesi',NULL,NULL,NULL,NULL,'> **Önemli:** Bu metin bilgilendirme amaçlı şablondur. Şirket unvanı, adres, iletişim, ürün ve ödeme modelinize göre **mutlaka bir hukuk danışmanı** tarafından gözden geçirilmelidir.\n\n## Taraflar\n\n**SATICI:** [TİCARİ ÜNVAN], [ADRES], [E-POSTA]\n\n**ALICI:** Sipariş sırasında bildirdiği bilgilerle tanımlanan gerçek/tüzel kişi.\n\n## Konu\n\nDijital ürün / lisans / abonelik (hosting paneli yazılımı ve ilişkili hizmetler) satışına ilişkin mesafeli sözleşme hükümleri.\n\n## Cayma hakkı\n\nMesafeli Sözleşmeler Yönetmeliği kapsamında, **elektronik ortamda anında ifa edilen** veya dijital içerikte tüketicinin onayı ile ifaya başlanan hizmetlerde cayma hakkı istisnaları bulunabilir. Gerçek uygulama ürün tipinize (lisans, kurulum, SaaS) göre hukukçunuzca netleştirilmelidir.\n\n## Ödeme ve fiyat\n\nFiyatlar sitede veya teklifte belirtilir; KDV ve yasal kesintiler ayrıca gösterilir.\n\n## Uyuşmuzluk\n\nTüketici işlemlerinde Tüketici Hakem Heyeti / Tüketici Mahkemeleri yetkilidir (mevzuata göre).\n\n**Son güncelleme:** 2026-04-10','6502 sayılı Kanun ve Mesafeli Sözleşmeler Yönetmeliği kapsamı.',1,34,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(9,'tr','kullanim-kosullari','Kullanım Koşulları',NULL,NULL,NULL,NULL,'> **Önemli:** Bu metin bilgilendirme amaçlı şablondur. Şirket unvanı, adres, iletişim, ürün ve ödeme modelinize göre **mutlaka bir hukuk danışmanı** tarafından gözden geçirilmelidir.\n\n## Kapsam\n\nWeb sitesi, dokümantasyon ve **Hostvim** hosting kontrol paneli yazılımının kullanımına ilişkin şartlar.\n\n## Lisans\n\nYazılım, satın alınan lisans tipine (ör. tek sunucu, vendor) göre kullanılır. Kaynak kodu, tersine mühendislik, lisans dışı çoğaltma yasaktır (sözleşme ve lisans metnine tabi).\n\n## Kabul edilebilir kullanım\n\nYasadışı içerik barındırma, spam, güvenlik açığı taraması (izinsiz), başkalarının sistemlerine zarar verme yasaktır. İhlal halinde hizmet askıya alınabilir veya feshedilebilir.\n\n## Sorumluluk reddi\n\nYazılım “olduğu gibi” sunulur; iş sürekliliği ve üçüncü taraf hizmetlerinden doğan dolaylı zararlar için sorumluluk, mevzuatın izin verdiği azami ölçüde sınırlıdır.\n\n## Değişiklik\n\nŞartlar güncellenebilir; yayın tarihi sitede belirtilir.\n\n**Son güncelleme:** 2026-04-10','Yazılım, web sitesi ve hizmetlerin kullanım şartları.',1,35,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(10,'tr','sla','Hizmet Seviyesi (SLA)',NULL,NULL,NULL,NULL,'> **Önemli:** Bu metin bilgilendirme amaçlı şablondur. Şirket unvanı, adres, iletişim, ürün ve ödeme modelinize göre **mutlaka bir hukuk danışmanı** tarafından gözden geçirilmelidir.\n\n## Hedefler (örnek — gerçek rakamları sözleşmede netleştirin)\n\n- **Aylık erişilebilirlik hedefi:** %99,5 (planlı bakım hariç, aşağıda).\n- **Planlı bakım:** Hafta içi [SAAT ARALIĞI], önceden [X] saat/gün bildirim (mümkün olduğunca).\n- **Destek ilk yanıt hedefi:** İş günü içinde [X] saat (e-posta / ticket kanalı).\n\n## Kapsam dışı\n\nMüşteri kodu, üçüncü taraf eklentileri, DNS/ISP kesintileri, DDoS ve müşteri kaynaklı yapılandırma hataları.\n\n## Kredi / tazminat\n\nSLA ihlali halinde tazminat veya hizmet kredisi yalnızca **yazılı sözleşmede** açıkça düzenlenmişse geçerlidir.\n\n**Son güncelleme:** 2026-04-10','Erişilebilirlik hedefleri, bakım ve destek çerçevesi.',1,36,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(11,'tr','iade-ve-iptal','Ücret İadesi ve İptal Koşulları',NULL,NULL,NULL,NULL,'> **Önemli:** Bu metin bilgilendirme amaçlı şablondur. Şirket unvanı, adres, iletişim, ürün ve ödeme modelinize göre **mutlaka bir hukuk danışmanı** tarafından gözden geçirilmelidir.\n\n## Genel\n\nÖdeme tipi (kart, havale, fatura) ve ürün (lisans, kurulum, aylık SaaS) modelinize göre iade kuralları değişir; aşağıdaki çerçeve şablondur.\n\n## Cayma ve iptal\n\nTüketici işlemlerinde mevzuattaki cayma süreleri uygulanır; dijital içerik / anında ifa istisnaları için Mesafeli Sözleşmeler Yönetmeliği’ne uyulur.\n\n## Kurumsal / B2B\n\nCayma hakkı olmayan sözleşmelerde iptal, sözleşme feshi hükümlerine tabidir.\n\n## İade süreci\n\nTalepler **[E-POSTA]** ile yapılır; uygun görülen ödemeler [X] iş günü içinde aynı kanala iade edilir (banka süreleri hariç).\n\n**Son güncelleme:** 2026-04-10','Cayma, iptal ve iade süreçleri.',1,37,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(12,'tr','veri-merkezi','Veri Merkezi ve Altyapı',NULL,NULL,NULL,NULL,'> **Önemli:** Bu metin bilgilendirme amaçlı şablondur. Şirket unvanı, adres, iletişim, ürün ve ödeme modelinize göre **mutlaka bir hukuk danışmanı** tarafından gözden geçirilmelidir.\n\n## Lokasyon\n\nMüşteri verileri ve yedeklerin tutulduğu birincil bölge: **[ÜLKE / ŞEHİR veya bulut bölgesi]** (örn. Avrupa Birliği içi veri merkezi).\n\n## Alt işlemciler\n\nBarındırma, yedekleme, izleme ve e-posta için sınırlı erişimli alt işlemciler kullanılabilir. Güncel liste talep üzerine veya müşteri sözleşmesi ekinde paylaşılır.\n\n## Güvenlik önlemleri\n\nErişim kontrolü, şifreleme, günlükleme ve yedekleme politikaları uygulanır.\n\n**Son güncelleme:** 2026-04-10','Barındırma lokasyonu ve alt işlemci bilgisi.',1,38,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(13,'tr','musteri-sozlesmesi','Müşteri Hizmet Sözleşmesi',NULL,NULL,NULL,NULL,'> **Önemli:** Bu metin bilgilendirme amaçlı şablondur. Şirket unvanı, adres, iletişim, ürün ve ödeme modelinize göre **mutlaka bir hukuk danışmanı** tarafından gözden geçirilmelidir.\n\n## Taraflar ve tanımlar\n\n**Sağlayıcı:** [TİCARİ ÜNVAN]  \n**Müşteri:** Lisans veya hizmet sözleşmesini onaylayan taraf.\n\n## Hizmetin kapsamı\n\nHostvim hosting kontrol paneli yazılımının sağlanması, güncellemeler (lisansa bağlı) ve belirlenen destek kanalları.\n\n## Ücretlendirme ve ödeme\n\nPlan, lisans veya teklif ekindeki fiyatlandırma geçerlidir; gecikmede fesih ve faiz hakları sözleşmede düzenlenir.\n\n## Hizmetin askıya alınması\n\nÖdeme gecikmesi, yasadışı kullanım veya güvenlik riski halinde geçici askıya alma.\n\n## Gizlilik ve veri işleme\n\nKişisel veriler KVKK Aydınlatma Metni ve Gizlilik Politikası’na uygun işlenir.\n\n## Süre ve fesih\n\nSözleşme süresi ve yenileme koşulları sipariş formunda; fesih bildirim süreleri sözleşmede belirtilir.\n\n## Uygulanacak hukuk ve yetki\n\n**[TÜRKİYE / İSTANBUL]** (örnek) — hukukçunuzca güncellenmelidir.\n\n**Son güncelleme:** 2026-04-10','Lisans / SaaS hosting paneli hizmet sözleşmesi çerçevesi.',1,39,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(14,'en','kvkk','Privacy & data protection notice',NULL,NULL,NULL,NULL,'> **Important:** This is a template for information only. Have it reviewed by qualified legal counsel for your entity, product, and jurisdiction.\n\n## Controller\n\n**[LEGAL ENTITY NAME]** (“we”, “us”) is the controller of personal data for this website and related services.\n\n- **Address:** [ADDRESS]\n- **Contact:** [EMAIL]\n\n## Data we process\n\nExamples: identity/contact details, account and billing metadata, technical logs (IP, user agent), support messages, and—if you consent—marketing preferences.\n\n## Purposes and legal bases\n\nService delivery (contract), legal obligations, legitimate interests (security, analytics in aggregated form), and consent where required (e.g. non-essential cookies / newsletters).\n\n## Recipients\n\nHosting, payment, email, and analytics providers acting as processors/sub-processors, including transfers outside your country where legally permitted and safeguarded.\n\n## Retention\n\nAs required by law and as long as necessary for the purposes described, then deleted or anonymised.\n\n## Your rights\n\nDepending on applicable law, you may request access, rectification, erasure, restriction, portability, or object to processing. Contact **[EMAIL]**. You may lodge a complaint with your supervisory authority.\n\n**Last updated:** 2026-04-10','How we process personal data in line with applicable law.',1,31,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(15,'en','gizlilik-politikasi','Privacy policy',NULL,NULL,NULL,NULL,'> **Important:** This is a template for information only. Have it reviewed by qualified legal counsel for your entity, product, and jurisdiction.\n\nThis policy describes how **Hostvim** collects and uses personal data when you use our website, demos, and related digital services.\n\n## What we collect\n\nInformation you submit in forms, account creation, support tickets, cookies, and server logs.\n\n## How we use it\n\nTo provide the service, secure our systems, analyse aggregated usage, communicate with you, and comply with law.\n\n## Sharing\n\nWith infrastructure, payment, email, and analytics vendors under appropriate agreements.\n\n## Security\n\nWe apply technical and organisational measures (e.g. TLS, access control). No method is 100% secure.\n\n## Contact\n\n**[EMAIL]** · [ADDRESS]\n\n**Last updated:** 2026-04-10','How we collect, use, and protect personal data.',1,32,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(16,'en','cerez-politikasi','Cookie policy',NULL,NULL,NULL,NULL,'> **Important:** This is a template for information only. Have it reviewed by qualified legal counsel for your entity, product, and jurisdiction.\n\n## What are cookies?\n\nSmall text files stored on your device.\n\n## Types\n\nStrictly necessary, functional, analytics, and—only with consent—marketing.\n\n## Managing cookies\n\nYou can block or delete cookies in your browser. Disabling strictly necessary cookies may break parts of the site.\n\n**Last updated:** 2026-04-10','Cookies we use and how to manage preferences.',1,33,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(17,'en','mesafeli-satis','Distance / online sales terms',NULL,NULL,NULL,NULL,'> **Important:** This is a template for information only. Have it reviewed by qualified legal counsel for your entity, product, and jurisdiction.\n\n## Parties\n\n**Seller:** [LEGAL ENTITY NAME], [ADDRESS], [EMAIL]  \n**Buyer:** The person or entity identified in the order.\n\n## Subject\n\nOnline purchase of digital services or software licenses related to the Hostvim hosting control panel.\n\n## Withdrawal / cooling-off\n\nRules depend on your jurisdiction and whether delivery is instant digital content. Many laws exclude or limit withdrawal once performance has started with the buyer’s consent—confirm with counsel.\n\n## Price and taxes\n\nAs shown at checkout or in the written quote, including applicable taxes.\n\n## Disputes\n\nAs specified under applicable consumer or commercial law.\n\n**Last updated:** 2026-04-10','Terms for online purchase of digital services or licenses.',1,34,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(18,'en','kullanim-kosullari','Terms of service',NULL,NULL,NULL,NULL,'> **Important:** This is a template for information only. Have it reviewed by qualified legal counsel for your entity, product, and jurisdiction.\n\n## Scope\n\nUse of the website, documentation, and Hostvim software under the purchased license.\n\n## License\n\nUse is limited to the purchased tier (e.g. per server, vendor). No reverse engineering, circumvention, or redistribution beyond the license.\n\n## Acceptable use\n\nNo illegal content, spam, unauthorised intrusion attempts, or activities harming third parties. We may suspend or terminate for breach.\n\n## Disclaimer\n\nSoftware is provided as available; liability is limited to the extent permitted by law.\n\n## Changes\n\nWe may update these terms; the publication date will be indicated.\n\n**Last updated:** 2026-04-10','Rules for using our website, software, and services.',1,35,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(19,'en','sla','Service level agreement (SLA)',NULL,NULL,NULL,NULL,'> **Important:** This is a template for information only. Have it reviewed by qualified legal counsel for your entity, product, and jurisdiction.\n\n## Targets (examples — fix in your contract)\n\n- **Monthly availability target:** 99.5% excluding scheduled maintenance.\n- **Scheduled maintenance:** Preferably off-peak with prior notice where practical.\n- **First response target (business hours):** [X] hours via email/ticket.\n\n## Exclusions\n\nCustomer code, third-party plugins, DNS/ISP issues, DDoS, and misconfiguration by the customer.\n\n## Remedies\n\nService credits or penalties apply only if explicitly stated in a signed agreement.\n\n**Last updated:** 2026-04-10','Availability targets, maintenance, and support response goals.',1,36,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(20,'en','iade-ve-iptal','Refunds & cancellation',NULL,NULL,NULL,NULL,'> **Important:** This is a template for information only. Have it reviewed by qualified legal counsel for your entity, product, and jurisdiction.\n\n## General\n\nRefund rules depend on payment method and product type (perpetual license, setup fee, monthly SaaS).\n\n## Consumer rights\n\nLocal consumer laws may grant cooling-off rights with exceptions for digital content delivered immediately with consent.\n\n## Business customers\n\nOften governed by contract rather than consumer withdrawal rules.\n\n## Process\n\nContact **[EMAIL]** with order details. Approved refunds are returned to the original payment method within [X] business days (bank timelines may apply).\n\n**Last updated:** 2026-04-10','Cooling-off, cancellation, and refund rules.',1,37,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(21,'en','veri-merkezi','Data centre & infrastructure',NULL,NULL,NULL,NULL,'> **Important:** This is a template for information only. Have it reviewed by qualified legal counsel for your entity, product, and jurisdiction.\n\n## Location\n\nPrimary region for production data and backups: **[REGION / CLOUD AREA]** (e.g. EU).\n\n## Subprocessors\n\nHosting, backups, monitoring, and email providers with limited access. An up-to-date list is available on request or in the data processing agreement.\n\n## Security\n\nAccess controls, encryption in transit, logging, and backup policies.\n\n**Last updated:** 2026-04-10','Hosting location and subprocessors (summary).',1,38,'2026-04-10 09:47:46','2026-04-10 09:47:46'),(22,'en','musteri-sozlesmesi','Customer agreement',NULL,NULL,NULL,NULL,'> **Important:** This is a template for information only. Have it reviewed by qualified legal counsel for your entity, product, and jurisdiction.\n\n## Parties\n\n**Provider:** [LEGAL ENTITY NAME]  \n**Customer:** The entity accepting the order or master agreement.\n\n## Service\n\nProvision of the Hostvim hosting control panel software, updates as covered by the license, and agreed support channels.\n\n## Fees\n\nPer order, quote, or subscription plan; late payment may trigger suspension as described in the agreement.\n\n## Suspension\n\nFor non-payment, illegal use, or material security risk.\n\n## Data protection\n\nProcessing of personal data follows our privacy notice and, where required, a data processing agreement.\n\n## Term and termination\n\nAs set out in the order form or master agreement.\n\n## Governing law\n\n**[JURISDICTION]** — replace with counsel-approved wording.\n\n**Last updated:** 2026-04-10','Framework agreement for licensing / SaaS of the hosting control panel.',1,39,'2026-04-10 09:47:46','2026-04-10 09:47:46');
/*!40000 ALTER TABLE `site_pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `community_banned_at` timestamp NULL DEFAULT NULL,
  `community_ban_reason` varchar(500) DEFAULT NULL,
  `community_admin_notes` text DEFAULT NULL,
  `community_shadowbanned_at` timestamp NULL DEFAULT NULL,
  `avatar_url` varchar(512) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','admin@hostvim.local','2026-04-10 09:47:46','$2y$12$why08phhdJEEHvQ/Gex/4O9NVuv.iYdswyYAgj14vD.IsPpm0Gsfu',1,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 09:47:46','2026-04-10 09:47:46');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-10 15:48:01
