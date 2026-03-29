# Panelsar - Development Roadmap

## Vision
Panelsar is a modern, full-featured hosting control panel designed to compete with Plesk and cPanel. Built with Go (engine), Laravel (panel), and React (frontend), it offers a multi-language, globally-ready hosting management solution.

---

## Phase 1 - MVP (Month 1-3)
**Goal:** Core functionality to manage basic hosting operations.

### Core Engine (Go) ✅ Structure Created
- [x] Daemon architecture with graceful shutdown
- [x] Configuration management (YAML + env)
- [x] Service manager (Nginx, PHP-FPM, MySQL, Redis)
- [x] Container management (Docker-based isolation)
- [x] Resource quota system (CPU, RAM, Disk, Bandwidth)
- [x] Cron job scheduler
- [x] REST API with JWT authentication
- [x] CORS and rate limiting middleware
- [ ] Nginx vhost template generation
- [ ] PHP-FPM pool management
- [ ] Let's Encrypt SSL automation (certbot integration)
- [ ] File system sandboxing per user

### Panel Backend (Laravel) ✅ Structure Created
- [x] User authentication (Sanctum tokens)
- [x] Role-based access (Admin, Reseller, User)
- [x] Domain CRUD with engine communication
- [x] Database management service
- [x] Hosting package management
- [x] Subscription/billing model
- [x] Engine API service layer
- [x] Database migrations (all tables)
- [x] Seeder with default data
- [ ] Queue workers for async operations
- [ ] Email notification system
- [ ] Activity logging

### Frontend (React) ✅ Structure Created
- [x] Login page with modern UI
- [x] Dashboard with stats and quick actions
- [x] Domain management page
- [x] Database management page
- [x] Settings page (profile, theme, language, security)
- [x] Sidebar navigation with collapsible design
- [x] Dark mode support
- [x] Multi-language (EN, TR, DE, FR, ES)
- [x] Zustand state management
- [x] React Query for API calls
- [ ] Domain creation modal
- [ ] Database creation modal
- [ ] Real-time notifications

### Infrastructure ✅ Structure Created
- [x] Docker Compose (PostgreSQL, Redis, MySQL, Nginx, phpMyAdmin, Mailhog)
- [x] Nginx reverse proxy configuration
- [x] Makefile for development commands
- [ ] CI/CD pipeline (GitHub Actions)
- [ ] Automated testing setup

---

## Phase 2 - Essential Features (Month 4-6)

### Backup & Restore
- [ ] Full site backup (files + database)
- [ ] Incremental backups
- [ ] Scheduled automatic backups
- [ ] One-click restore
- [ ] Remote backup storage (S3, FTP)

### File Manager
- [ ] Browser-based file manager
- [ ] Drag-and-drop upload
- [ ] Code editor with syntax highlighting
- [ ] File permissions management
- [ ] Archive/extract support

### FTP Management
- [ ] FTP/SFTP account creation
- [ ] Directory restriction per account
- [ ] Quota management
- [ ] ProFTPD/vsftpd integration

### Monitoring Dashboard
- [ ] Real-time CPU, RAM, Disk graphs (Recharts)
- [ ] Per-site resource usage
- [ ] Access log viewer
- [ ] Error log viewer
- [ ] Uptime monitoring
- [ ] Alert system (email/webhook)

### One-Click App Installer
- [ ] WordPress
- [ ] Laravel
- [ ] Joomla
- [ ] Drupal
- [ ] PrestaShop
- [ ] Custom app templates

---

## Phase 3 - Advanced Features (Month 7-10)

### Email Server
- [ ] Postfix + Dovecot integration
- [ ] Mailbox creation/management
- [ ] Email forwarders and aliases
- [ ] Autoresponders
- [ ] DKIM/SPF/DMARC configuration
- [ ] SpamAssassin integration
- [ ] Webmail (Roundcube) integration

### Security Module
- [ ] Fail2ban integration
- [ ] iptables/ufw management
- [ ] Brute-force protection
- [ ] Malware scanning (ClamAV)
- [ ] SSL certificate monitoring
- [ ] Security audit reports
- [ ] IP whitelist/blacklist

### Reseller System
- [ ] Sub-user management
- [ ] Custom hosting packages per reseller
- [ ] White-label branding
- [ ] Billing automation
- [ ] Resource allocation and limits
- [ ] Reseller dashboard

### API & Integrations
- [ ] Complete RESTful API documentation (Swagger)
- [ ] WHMCS integration
- [ ] Domain registrar APIs (Namecheap, GoDaddy, Cloudflare)
- [ ] Payment gateway integration (Stripe, PayPal)
- [ ] Webhook system

### License System
- [ ] IP/domain-based licensing
- [ ] License verification server
- [ ] Auto-update system
- [ ] Rollback mechanism
- [ ] Usage analytics

---

## Phase 4 - Global Scale (Month 11-16)

### AI-Powered Features
- [ ] Performance optimization suggestions
- [ ] Anomaly detection in resource usage
- [ ] Automated scaling recommendations
- [ ] Smart log analysis
- [ ] Predictive maintenance alerts

### Enterprise Features
- [ ] Multi-server cluster management
- [ ] High availability setup
- [ ] Staging/development environments
- [ ] CI/CD pipeline integration
- [ ] Git deployment
- [ ] Container orchestration (Kubernetes)

### Full Multi-Language Support
- [ ] Complete translations: EN, TR, DE, FR, ES, PT, ZH, JA, AR, RU
- [ ] RTL support for Arabic
- [ ] Locale-specific formatting (dates, numbers, currency)
- [ ] Translation management interface

### Additional Languages
- [ ] Portuguese (PT)
- [ ] Chinese (ZH)
- [ ] Japanese (JA)
- [ ] Arabic (AR)
- [ ] Russian (RU)

---

## Tech Stack Summary

| Component | Technology |
|-----------|-----------|
| Backend Engine | Go 1.22 + Gin |
| Panel Backend | Laravel 11 + PHP 8.2 |
| Frontend | React 18 + TypeScript + Vite |
| Styling | TailwindCSS 3.4 |
| State Management | Zustand + React Query |
| Database (Core) | PostgreSQL 16 |
| Database (Sites) | MySQL 8.0 |
| Cache/Queue | Redis 7 |
| Web Server | Nginx |
| Containers | Docker |
| Email | Postfix + Dovecot |
| SSL | Let's Encrypt |
| i18n | react-i18next + Laravel Localization |

---

## Architecture

```
┌─────────────────────────────────────────────┐
│                  Frontend                     │
│          React + TypeScript + Vite            │
│              TailwindCSS + i18n               │
└──────────────────┬──────────────────────────┘
                   │ HTTP/REST
┌──────────────────▼──────────────────────────┐
│              Panel Backend                    │
│         Laravel 11 + Sanctum                  │
│    Redis Queue │ PostgreSQL │ Stripe          │
└──────────────────┬──────────────────────────┘
                   │ Internal REST API
┌──────────────────▼──────────────────────────┐
│              Backend Engine                   │
│           Go Daemon + Gin API                 │
│  Docker │ Nginx │ PHP-FPM │ MySQL │ SSL      │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│              Linux Server                     │
│    Nginx │ Apache │ PHP │ MySQL │ Postfix     │
│    Docker Containers │ Firewall │ Cron        │
└─────────────────────────────────────────────┘
```
