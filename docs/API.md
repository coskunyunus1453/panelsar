# Panelsar API Documentation

## Base URL
```
Panel API: http://localhost:8080/api
Engine API: http://localhost:9090/api/v1
```

## Authentication

### Login
```
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@panelsar.com",
  "password": "password"
}

Response:
{
  "user": { ... },
  "token": "1|abc123...",
  "expires_at": "2026-03-30T00:00:00Z"
}
```

### Logout
```
POST /api/auth/logout
Authorization: Bearer {token}
```

### Get Current User
```
GET /api/auth/me
Authorization: Bearer {token}
```

---

## Domains

### List Domains
```
GET /api/domains?page=1
Authorization: Bearer {token}
```

### Create Domain
```
POST /api/domains
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "example.com",
  "php_version": "8.2",
  "server_type": "nginx"
}
```

### Delete Domain
```
DELETE /api/domains/{id}
Authorization: Bearer {token}
```

### Switch PHP Version
```
POST /api/domains/{id}/php
Authorization: Bearer {token}
Content-Type: application/json

{
  "php_version": "8.3"
}
```

---

## Databases

### List Databases
```
GET /api/databases?page=1
Authorization: Bearer {token}
```

### Create Database
```
POST /api/databases
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "mydb",
  "type": "mysql",
  "domain_id": 1
}
```

### Delete Database
```
DELETE /api/databases/{id}
Authorization: Bearer {token}
```

---

## System (Admin Only)

### Get System Stats
```
GET /api/system/stats
Authorization: Bearer {token}
```

### List Services
```
GET /api/system/services
Authorization: Bearer {token}
```

### Control Service
```
POST /api/system/services/{name}
Authorization: Bearer {token}
Content-Type: application/json

{
  "action": "restart"
}
```

---

## Admin

### List Users
```
GET /api/admin/users?page=1&search=&status=&role=
Authorization: Bearer {token}
```

### Create User
```
POST /api/admin/users
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "securepassword",
  "password_confirmation": "securepassword",
  "role": "user",
  "hosting_package_id": 1,
  "locale": "en"
}
```

### Hosting Packages
```
GET /api/admin/packages
POST /api/admin/packages
PUT /api/admin/packages/{id}
DELETE /api/admin/packages/{id}
```

---

## Engine API (Internal)

### Health Check
```
GET http://localhost:9090/health

Response:
{
  "status": "healthy",
  "engine": "panelsar",
  "version": "0.1.0"
}
```

### Services
```
GET /api/v1/services
GET /api/v1/services/{name}
POST /api/v1/services/{name}/start
POST /api/v1/services/{name}/stop
POST /api/v1/services/{name}/restart
```

### Sites
```
POST /api/v1/sites
DELETE /api/v1/sites/{domain}
GET /api/v1/sites
```

### SSL
```
POST /api/v1/ssl/issue
POST /api/v1/ssl/renew
```

### System
```
GET /api/v1/system/stats
GET /api/v1/system/processes
```

### Panel → Engine internal auth
Panel, `ENGINE_INTERNAL_KEY` ile `X-Panelsar-Engine-Key` başlığını gönderir (engine `security.internal_api_key` ile aynı olmalı).

### Engine ek uçlar (özet)
```
GET/POST /api/v1/files …
POST /api/v1/files/mkdir
DELETE /api/v1/files?domain=&path=
GET /api/v1/files/read?domain=&path=
POST /api/v1/files/write
GET/POST /api/v1/backups
POST /api/v1/backups/:id/restore
GET/POST /api/v1/dns/:domain
DELETE /api/v1/dns/:domain/:id
GET/POST /api/v1/ftp/:domain
GET /api/v1/mail/:domain
POST /api/v1/mail/:domain/mailbox
GET /api/v1/security/overview
POST /api/v1/security/firewall/rule
GET/POST /api/v1/cron
GET /api/v1/installer/apps
POST /api/v1/installer/install
POST /api/v1/license/validate
POST /api/v1/nginx/reload
POST /api/v1/ssl/revoke
```

### Panel REST (kimlik doğrulamalı örnekler)
```
GET  /api/domains/{domain}/files
POST /api/domains/{domain}/files/mkdir
GET  /api/backups
POST /api/billing/checkout
GET  /api/installer/apps
GET  /api/security/overview
```
