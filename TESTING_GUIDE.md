# 🧪 Guida Completa al Testing della Piattaforma SaaS

## 🚀 Setup Iniziale

### 1. Avvio dell'Applicazione
```bash
# 1. Avvia il server Laravel
php artisan serve
# Server disponibile su: http://localhost:8000

# 2. Verifica connessione database
php artisan migrate:status

# 3. Popola dati di test (opzionale)
php artisan db:seed
```

### 2. Verifica API Base
```bash
# Test health check API
curl http://localhost:8000/api/v1/health

# Risposta attesa:
{
  "status": "ok",
  "timestamp": "2024-08-18T12:00:00Z",
  "version": "1.0.0",
  "environment": "local",
  "laravel_version": "11.x",
  "api_version": "1.0"
}
```

---

## 🔐 Autenticazione e Registrazione

### 1. Registrazione Nuovo Utente
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Mario Rossi",
    "email": "mario@example.com", 
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!",
    "company_name": "Test Company"
  }'
```

### 2. Login e Ottenimento Token
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "mario@example.com",
    "password": "SecurePassword123!"
  }'

# Salva il token dalla risposta per i test successivi
export API_TOKEN="your_token_here"
```

### 3. Verifica Profilo Utente
```bash
curl http://localhost:8000/api/user \
  -H "Authorization: Bearer $API_TOKEN"
```

---

## 🏢 Multi-Tenancy e Isolamento Dati

### 1. Creazione Tenant
```bash
curl -X POST http://localhost:8000/api/tenants \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $API_TOKEN" \
  -d '{
    "name": "Acme Corporation",
    "domain": "acme.example.com",
    "plan_id": 1,
    "contact_email": "admin@acme.com"
  }'

# Salva tenant_id dalla risposta
export TENANT_ID="1"
```

### 2. Test Isolamento Tenant
```bash
# Accesso con header tenant
curl http://localhost:8000/api/v1/tenant \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID"

# Test sicurezza tenant
curl http://localhost:8000/api/v1/tenant/security-status \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID"
```

---

## 💳 Sistema di Pagamenti e Sottoscrizioni

### 1. Visualizza Piani Disponibili
```bash
curl http://localhost:8000/api/plans \
  -H "Authorization: Bearer $API_TOKEN"
```

### 2. Test Sottoscrizione
```bash
# Crea sottoscrizione per piano Basic
curl -X POST http://localhost:8000/api/subscriptions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{
    "plan_id": 2,
    "payment_method": "stripe_test_card",
    "billing_cycle": "monthly"
  }'
```

### 3. Gestione Pagamenti
```bash
# Test pagamento
curl -X POST http://localhost:8000/api/payments \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{
    "amount": 2900,
    "currency": "EUR",
    "description": "Monthly subscription",
    "payment_method_id": "pm_test_card"
  }'

# Visualizza invoice
curl http://localhost:8000/api/invoices \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID"
```

---

## 📊 Usage Tracking e Limiti

### 1. Test Usage Tracking
```bash
# Traccia utilizzo API
curl -X POST http://localhost:8000/api/v1/usage/track \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{
    "feature": "api_calls",
    "amount": 1,
    "metadata": {
      "endpoint": "/api/v1/users",
      "method": "GET"
    }
  }'

# Visualizza metriche utilizzo
curl http://localhost:8000/api/v1/usage/summary \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID"

# Controlla limiti
curl http://localhost:8000/api/v1/usage/meters \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID"
```

### 2. Test Limiti e Alert
```bash
# Visualizza alert utilizzo
curl http://localhost:8000/api/v1/usage/alerts \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID"

# Test superamento limite (simulazione)
for i in {1..100}; do
  curl -X POST http://localhost:8000/api/v1/usage/track \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "X-Tenant-ID: $TENANT_ID" \
    -d '{"feature": "api_calls", "amount": 1}'
done
```

---

## 🎯 Free Tier e Feature Gates

### 1. Test Accesso Feature
```bash
# Ottieni piano corrente
curl http://localhost:8000/api/v1/free-tier/plan \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID"

# Lista feature disponibili
curl http://localhost:8000/api/v1/free-tier/features \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID"

# Test accesso feature specifica
curl http://localhost:8000/api/v1/free-tier/features/basic_reports/check \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{"quantity": 1}'
```

### 2. Test Upgrade Prompts
```bash
# Ottieni raccomandazioni upgrade
curl http://localhost:8000/api/v1/upgrade-prompts/recommendations \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID"

# Confronta piani
curl http://localhost:8000/api/v1/free-tier/comparison \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID"
```

---

## 🔧 API Framework e Versioning

### 1. Test Versioning API
```bash
# Test con Accept header (metodo preferito)
curl http://localhost:8000/api/v1/health \
  -H "Accept: application/vnd.api.v1.1+json"

# Test con header personalizzato
curl http://localhost:8000/api/v1/health \
  -H "X-API-Version: 1.0"

# Test con query parameter
curl "http://localhost:8000/api/v1/health?api_version=1.0"

# Test versione non supportata
curl http://localhost:8000/api/v1/health \
  -H "X-API-Version: 99.0"
```

### 2. Test Rate Limiting
```bash
# Test rate limit headers
curl -v http://localhost:8000/api/v1/health \
  -H "Authorization: Bearer $API_TOKEN"

# Verifica headers:
# X-RateLimit-Limit: 60
# X-RateLimit-Remaining: 59
# X-RateLimit-Reset: timestamp
# X-RateLimit-Tier: free
```

### 3. Documentazione API
```bash
# Overview API
curl http://localhost:8000/api/v1/docs

# Endpoints disponibili
curl http://localhost:8000/api/v1/docs/endpoints

# Documentazione webhook
curl http://localhost:8000/api/v1/docs/webhooks

# Rate limits configurazione
curl http://localhost:8000/api/v1/docs/rate-limits
```

---

## 🛡️ Sistema Amministrativo

### 1. Setup Admin User
```bash
# Prima crea un utente admin via database o seeder
php artisan tinker
>>> $user = User::find(1);
>>> $user->is_super_admin = true;
>>> $user->save();
>>> exit

# Login come admin
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password"
  }'

export ADMIN_TOKEN="admin_token_here"
```

### 2. Gestione Utenti Admin
```bash
# Lista utenti con filtri
curl "http://localhost:8000/api/admin/users?search=mario&status=active&tenant_id=1" \
  -H "Authorization: Bearer $ADMIN_TOKEN"

# Crea nuovo utente
curl -X POST http://localhost:8000/api/admin/users \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d '{
    "name": "Nuovo Utente",
    "email": "nuovo@example.com",
    "password": "SecurePass123!",
    "tenant_id": 1,
    "send_welcome_email": true
  }'

# Sospendi utente
curl -X POST http://localhost:8000/api/admin/users/2/suspend \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d '{"reason": "Violazione termini di servizio"}'
```

### 3. Operazioni Bulk
```bash
# Sospensione multipla
curl -X POST http://localhost:8000/api/admin/users/bulk-suspend \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d '{
    "user_ids": [2, 3, 4],
    "reason": "Test sospensione bulk"
  }'

# Riattivazione multipla
curl -X POST http://localhost:8000/api/admin/users/bulk-reactivate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d '{
    "user_ids": [2, 3, 4]
  }'
```

### 4. User Impersonation
```bash
# Avvia impersonation
curl -X POST http://localhost:8000/api/admin/users/2/impersonate \
  -H "Authorization: Bearer $ADMIN_TOKEN"

# Termina impersonation (usa session_id dalla risposta precedente)
curl -X DELETE http://localhost:8000/api/admin/impersonation/session_id \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### 5. Analytics Dashboard
```bash
# Dashboard analytics completa
curl http://localhost:8000/api/admin/analytics/dashboard \
  -H "Authorization: Bearer $ADMIN_TOKEN"

# Audit logs con filtri
curl "http://localhost:8000/api/admin/audit-logs?action=user.created&admin_id=1" \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

---

## 📱 PWA e Mobile

### 1. Test PWA
```bash
# Manifest PWA
curl http://localhost:8000/manifest.json

# Service Worker
curl http://localhost:8000/sw.js

# Performance tracking
curl -X POST http://localhost:8000/api/v1/performance/track \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $API_TOKEN" \
  -d '{
    "metrics": [{
      "name": "page_load_time",
      "value": 1200,
      "timestamp": 1692123456
    }]
  }'
```

### 2. Test Responsive
```
# Apri browser e vai su http://localhost:8000
# Apri DevTools (F12)
# Attiva Device Toolbar (Ctrl+Shift+M)
# Testa su diversi dispositivi:
# - iPhone 12 Pro
# - iPad Air
# - Samsung Galaxy S21
# - Desktop 1920x1080
```

---

## 🎯 Webhook System

### 1. Configurazione Webhook
```bash
# Crea webhook per testing
curl -X POST http://localhost:8000/api/webhooks \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{
    "name": "Test Webhook",
    "url": "https://webhook.site/your-unique-url",
    "events": ["user.created", "user.updated", "subscription.created"],
    "is_active": true
  }'
```

### 2. Test Eventi Webhook
```bash
# Gli eventi vengono triggerati automaticamente quando:
# - Crei un nuovo utente
# - Aggiorni un utente esistente  
# - Crei una sottoscrizione
# - Superi limiti di utilizzo

# Esempio: crea utente per triggerare webhook
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Webhook User",
    "email": "webhook@example.com",
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!"
  }'

# Verifica su webhook.site se hai ricevuto l'evento user.created
```

---

## 🧪 Esecuzione Test Automatici

### 1. Test Unit e Feature
```bash
# Test completi
php artisan test

# Test specifici per feature
./vendor/bin/phpunit tests/Feature/ApiFrameworkTest.php
./vendor/bin/phpunit tests/Feature/AdminUserManagementTest.php
./vendor/bin/phpunit tests/Feature/FreeTierAccessTest.php
./vendor/bin/phpunit tests/Feature/TenantIsolationTest.php

# Test unitari
./vendor/bin/phpunit tests/Unit/ApiFrameworkUnitTest.php
./vendor/bin/phpunit tests/Unit/AdminModelsUnitTest.php
```

### 2. Performance Testing
```bash
# Test performance con Apache Bench
ab -n 100 -c 10 http://localhost:8000/api/v1/health

# Test rate limiting
ab -n 200 -c 20 http://localhost:8000/api/v1/health
```

---

## 📊 Monitoraggio e Debug

### 1. Log Monitoring
```bash
# Segui i log in tempo reale
tail -f storage/logs/laravel.log

# Verifica log audit admin
tail -f storage/logs/admin-audit.log
```

### 2. Database Queries
```bash
# Verifica dati con tinker
php artisan tinker
>>> User::count()
>>> Tenant::with('users')->get()
>>> AdminAuditLog::latest()->take(5)->get()
>>> exit
```

### 3. Cache e Performance
```bash
# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Ottimizza per produzione
php artisan config:cache
php artisan route:cache
```

---

## 🎉 Scenario di Test Completo

### Test End-to-End Completo
```bash
#!/bin/bash
echo "🚀 Test End-to-End Piattaforma SaaS"

# 1. Health check
echo "1. Testing API health..."
curl -s http://localhost:8000/api/v1/health | jq .status

# 2. Registrazione utente
echo "2. Registrando nuovo utente..."
REGISTER_RESPONSE=$(curl -s -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"SecurePass123!","password_confirmation":"SecurePass123!"}')

# 3. Login
echo "3. Effettuando login..."
LOGIN_RESPONSE=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"SecurePass123!"}')

TOKEN=$(echo $LOGIN_RESPONSE | jq -r .token)

# 4. Creazione tenant
echo "4. Creando tenant..."
TENANT_RESPONSE=$(curl -s -X POST http://localhost:8000/api/tenants \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"Test Company","domain":"test.example.com","plan_id":1}')

TENANT_ID=$(echo $TENANT_RESPONSE | jq -r .data.id)

# 5. Test usage tracking
echo "5. Testing usage tracking..."
curl -s -X POST http://localhost:8000/api/v1/usage/track \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{"feature":"api_calls","amount":1}' | jq .

# 6. Test feature access
echo "6. Testing feature access..."
curl -s http://localhost:8000/api/v1/free-tier/features \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" | jq .

echo "✅ Test End-to-End completato!"
```

Salva questo script come `test_e2e.sh`, rendilo eseguibile con `chmod +x test_e2e.sh` e eseguilo con `./test_e2e.sh`.

---

## 🔍 Troubleshooting Comuni

### Problemi e Soluzioni

1. **Errore 401 Unauthorized**
   ```bash
   # Verifica token validity
   php artisan tinker
   >>> $user = User::find(1)
   >>> $token = $user->createToken('test')->plainTextToken
   >>> echo $token
   ```

2. **Errore Tenant non trovato**
   ```bash
   # Verifica X-Tenant-ID header
   curl -v -H "X-Tenant-ID: 1" your_endpoint
   ```

3. **Rate limit exceeded**
   ```bash
   # Clear rate limit cache
   php artisan cache:forget rate_limit:*
   ```

4. **Database connection error**
   ```bash
   # Verifica configurazione database
   php artisan migrate:status
   ```

Con questa guida puoi testare completamente tutte le funzionalità implementate nella piattaforma SaaS! 🎯