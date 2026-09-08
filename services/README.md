# WapApp Microservices

This directory contains the standalone microservices extracted from the monolith. Each service runs independently as a lightweight Laravel service with its own database, routing, and testing suite.

---

## 📁 Services Directory Structure

```
services/
├── inbox-service/       # Port 8001: Manages messages, conversations, and outbound dispatching
├── campaign-service/    # Port 8002: Manages campaigns, scheduling, and bulk messaging
└── template-service/    # Port 8003: Manages WhatsApp templates, approvals, and variables
```

---

## 🚀 Running Microservices Locally

### 1. Inbox Service
```bash
cd services/inbox-service
php artisan serve --port=8001
```

### 2. Campaign Service
```bash
cd services/campaign-service
php artisan serve --port=8002
```

### 3. Template Service
```bash
cd services/template-service
php artisan serve --port=8003
```

---

## 🧪 Running Microservice Tests

You can run test suites for all microservices individually or collectively:

```bash
# Run all microservice tests
(cd services/inbox-service && ./vendor/bin/phpunit) && \
(cd services/campaign-service && ./vendor/bin/phpunit) && \
(cd services/template-service && ./vendor/bin/phpunit)
```

---

## ⚙️ Monolith Configuration & Integration

The monolith communicates with each microservice using resilient adapter & client layers with automatic fallback:

| Service | Config File | Default Base URL | Environment Variable |
| :--- | :--- | :--- | :--- |
| **Inbox Service** | `config/inbox-service.php` | `http://127.0.0.1:8001/api/v1` | `INBOX_SERVICE_URL`, `INBOX_SERVICE_ENABLED` |
| **Campaign Service** | `config/campaign-service.php` | `http://127.0.0.1:8002/api/v1` | `CAMPAIGN_SERVICE_URL`, `CAMPAIGN_SERVICE_ENABLED` |
| **Template Service** | `config/template-service.php` | `http://127.0.0.1:8003/api/v1` | `TEMPLATE_SERVICE_URL`, `TEMPLATE_SERVICE_ENABLED` |
