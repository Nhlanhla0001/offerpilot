# Judge setup and test guide

## Requirements

PHP 8.4 with Composer and SQLite support; Node.js 20+ with npm; a DeepSeek API key; a PayPal Developer Sandbox app (client ID and secret) and Sandbox personal buyer account. Keep all credentials local. The seller demo credentials below are created by the seeder and are not real account credentials.

## Install

```bash
git clone https://github.com/Nhlanhla0001/offerpilot.git
cd offerpilot
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --no-interaction
php artisan db:seed --no-interaction
npm install
npm run build
```

On PowerShell, replace `cp` with `Copy-Item .env.example .env`. SQLite is the default. If Laravel reports a missing database file, create the `database/database.sqlite` empty file and rerun migration. Do not use a production database for this demo.

## Configure `.env`

Set `APP_URL` to your local URL, `AI_PROVIDER=deepseek`, `DEEPSEEK_API_KEY`, `PAYPAL_MODE=sandbox`, `PAYPAL_SANDBOX_URL=https://api-m.sandbox.paypal.com`, `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, and `PAYPAL_MERCHANT_EMAIL` for your Sandbox business account. Use the variable names in `.env.example`. After changing settings, run `php artisan config:clear`.

## Start and test

Run `composer run dev` (or `php artisan serve` and `npm run dev` in separate terminals), then visit the local URL shown by the server. The home page is `/`; the buyer storefront is `/store`; the seeded headphone product is `/store/HEADPHONES-PRO`. Guests can browse, ask questions, negotiate and buy without a shop account.

Ask the assistant a product question. Explicitly confirm a maximum for that product before making an offer. The pricing service may accept, counter, or reject; choose an offered quote to continue. Confirm adding it to the cart, open `/cart`, review the amount, and complete the checkout with a PayPal **Sandbox personal** test account. Real PayPal credentials are not required or appropriate. A successful capture should produce a confirmation.

To inspect the seller side, sign in at `/login` with the seeded **demo-only** account `test@example.com` / `password`, then open `/dashboard`, `/offers`, `/inventory`, and `/orders`. After the test purchase, refresh `/orders` to see the verified order and PayPal capture reference. The seller's inventory edit view contains private minimums; do not expose those in a public recording.

## Limitations and access

The full AI and payment flows require the judge's own DeepSeek key and PayPal Sandbox credentials. No private credentials are distributed in this public repository. This is a locally installed sandbox demo, not a production payments deployment. The public repository plus these instructions are the judge access path. The hackathon rules require free, unrestricted testing through the judging period; the entrant should ensure the needed sandbox access can be obtained by judges without paying.
