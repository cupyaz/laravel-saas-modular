# US-006: Secure Payment Processing - Piano di Test

## Setup Iniziale

### 1. Configurazione Stripe Test Environment

Nel tuo file `.env`, aggiungi:

```bash
# Stripe Test Keys (da https://dashboard.stripe.com/test/apikeys)
STRIPE_KEY=pk_test_51...  # Publishable key
STRIPE_SECRET=sk_test_51...  # Secret key
CASHIER_WEBHOOK_SECRET=whsec_...  # Webhook signing secret

# Currency
CASHIER_CURRENCY=usd
CASHIER_CURRENCY_LOCALE=en

# Database per testing
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### 2. Setup Database

```bash
# Crea il database SQLite
touch database/database.sqlite

# Esegui migrations (se disponibili)
php artisan migrate

# Seed dati di test
php artisan db:seed --class=PlansSeeder
```

### 3. Configura Webhook Stripe (Opzionale per test locali)

```bash
# Installa Stripe CLI per testing locale
# https://stripe.com/docs/stripe-cli

# Avvia webhook listener
stripe listen --forward-to localhost:8000/api/stripe/webhook
```

## Test Cases da Eseguire

### A. Test Frontend Payment Flow

#### Test 1: Checkout con Carta di Credito
1. **Setup**: Accedi come utente registrato
2. **URL**: `/payment/checkout/1` (sostituisci 1 con un plan_id valido)
3. **Dati Carta Test Stripe**:
   - Numero: `4242424242424242`
   - Exp: `12/34`
   - CVC: `123`
   - Nome: `Test User`
4. **Dati Billing**:
   - Address: `123 Test St`
   - City: `Test City`
   - Country: `US`
   - State: `CA`
   - ZIP: `90210`
5. **Verifica**: Redirect a `/payment/success`

#### Test 2: Checkout con Bank Transfer
1. **Setup**: Accedi come utente registrato
2. **URL**: `/payment/checkout/1`
3. **Seleziona**: Tab "Bank Transfer"
4. **Compila**: Dati di billing richiesti
5. **Verifica**: Redirect a `/payment/bank-transfer-instructions`

#### Test 3: Tax Calculation
1. **Apri**: Developer tools → Network tab
2. **Cambia**: Paese nel form checkout
3. **Verifica**: Chiamata AJAX a `/api/calculate-tax`
4. **Controlla**: Aggiornamento tax amount nel UI

### B. Test API Endpoints

#### Test 4: Setup Intent API
```bash
curl -X POST http://localhost:8000/api/payment/setup-intent \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -d '{}'
```

**Expected Response**:
```json
{
  "client_secret": "seti_...",
  "setup_intent_id": "seti_..."
}
```

#### Test 5: Tax Calculation API
```bash
curl -X POST http://localhost:8000/api/calculate-tax \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -d '{
    "plan_id": 1,
    "country_code": "US",
    "state_code": "CA",
    "postal_code": "90210"
  }'
```

**Expected Response**:
```json
{
  "subtotal": 29.99,
  "tax": {
    "amount": 2.17,
    "formatted_amount": "$2.17",
    "rate": 0.0725,
    "jurisdiction": "California"
  },
  "total": 32.16
}
```

#### Test 6: Process Payment API
```bash
curl -X POST http://localhost:8000/api/payment/process \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -d '{
    "plan_id": 1,
    "payment_method_id": "pm_test_...",
    "country_code": "US",
    "state_code": "CA",
    "postal_code": "90210",
    "city": "Los Angeles",
    "address_line1": "123 Test Street"
  }'
```

### C. Test Casi Edge

#### Test 7: Carte Declined/Error
Usa queste carte test Stripe:

- **Card Declined**: `4000000000000002`
- **Insufficient Funds**: `4000000000009995`
- **Expired Card**: `4000000000000069`
- **Incorrect CVC**: `4000000000000127`

**Verifica**: Messaggi di errore appropriati

#### Test 8: 3D Secure Card
- **Numero**: `4000002500003155`
- **Verifica**: Modal 3D Secure appare e pagamento completa dopo autenticazione

#### Test 9: Test Paesi Diversi per Tax
- **EU Country**: `country_code: "DE"` → dovrebbe calcolare IVA
- **No Tax Country**: `country_code: "AE"` → tax dovrebbe essere 0
- **US State**: `state_code: "NY"` → dovrebbe calcolare sales tax

### D. Test Webhook (Se configurati)

#### Test 10: Webhook Events
1. **Trigger**: Completa un pagamento
2. **Verifica Logs**: 
   ```bash
   tail -f storage/logs/laravel.log
   ```
3. **Controlla**: 
   - `customer.subscription.created`
   - `invoice.payment_succeeded`
   - `payment_intent.succeeded`

### E. Test UI/UX

#### Test 11: Mobile Responsiveness
1. **Apri**: DevTools → Mobile view
2. **Verifica**: Payment form funziona su mobile
3. **Test**: Stripe Elements responsive

#### Test 12: Error Handling UI
1. **Trigger**: Errore di pagamento
2. **Verifica**: Alert di errore visibile e user-friendly

#### Test 13: Loading States
1. **Osserva**: Durante pagamento
2. **Verifica**: 
   - Button disabled con spinner
   - Modal "Processing Payment"

### F. Test Billing Dashboard

#### Test 14: Billing Dashboard Access
1. **URL**: `/billing`
2. **Verifica**: 
   - Subscription correnti visualizzate
   - Invoice recenti mostrate
   - Payment methods listati

### G. Test Email System (Se configurato)

#### Test 15: Invoice Email
1. **Configura**: Mail driver in `.env`
2. **Trigger**: Pagamento completato
3. **Verifica**: Email con PDF invoice inviata

## Checklist Finale

- [ ] ✅ Payment form carica senza errori
- [ ] ✅ Stripe Elements inizializza correttamente
- [ ] ✅ Tax calculation funziona per diversi paesi
- [ ] ✅ Payment processing completa con carta test
- [ ] ✅ Redirect corretto dopo pagamento
- [ ] ✅ Bank transfer flow funziona
- [ ] ✅ Error handling mostra messaggi appropriati
- [ ] ✅ Mobile responsive
- [ ] ✅ API endpoints rispondono correttamente
- [ ] ✅ Webhook processing (se configurato)
- [ ] ✅ Billing dashboard accessibile
- [ ] ✅ Invoice generation funziona

## Debugging Tips

### Logs da Controllare
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Stripe webhook logs (se usando Stripe CLI)
stripe logs tail
```

### Common Issues

1. **"api_key cannot be empty"**: Verifica STRIPE_SECRET in `.env`
2. **"No such plan"**: Crea piani test in Stripe dashboard
3. **"No active tenant"**: Implementa tenant selection per user
4. **CORS errors**: Verifica CSRF token nelle chiamate AJAX

### Stripe Dashboard
- **Payments**: https://dashboard.stripe.com/test/payments
- **Customers**: https://dashboard.stripe.com/test/customers  
- **Webhooks**: https://dashboard.stripe.com/test/webhooks

## Automated Testing (Opzionale)

Puoi anche creare test PHPUnit per automatizzare alcuni di questi test:

```bash
# Esegui test esistenti
php artisan test

# Test specifici per payment
php artisan test --filter PaymentTest
```

Questo piano ti guiderà attraverso tutti gli aspetti critici del sistema di pagamento implementato in US-006.