# Linera — Gateway IA sécurisée (V1)

- Backend: Laravel (API)
- Frontend: Vue 3 + TypeScript + Vuetify + Vite
- DB: PostgreSQL
- Paiements: Stripe (webhooks)
- Sécurité: JWT éphémère (<60s), nonce, body_sha256
- Facturation: Hold→Capture (micro-crédits)
- Traçabilité: hash on-chain Algorand (back-office)

## Démarrage rapide
- Backend: `cd backend && php artisan serve`
- Frontend: `cd frontend && npm run dev`
# linera_dev
