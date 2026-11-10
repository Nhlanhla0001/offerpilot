# OfferPilot

**Name your budget. The merchant meets you where it can.**

OfferPilot is a negotiating storefront built for the 2026 PayPal AI Hackathon. A buyer sets a target price and a hard maximum, talks to an AI assistant, and the merchant answers with a real, server-issued quote that can be paid immediately through the PayPal Sandbox.

The design goal is trust in both directions:

- **Merchants keep their floors.** A product's minimum price lives in a private table and never reaches the browser, the logs, or the language model.
- **Buyers keep their ceilings.** Every price the app offers is checked against the buyer's maximum before it is shown.
- **The AI never does arithmetic.** It negotiates the conversation; a deterministic service decides every number.

## How it works

```
Browser (Inertia + React)
   │  target + ceiling + message
   ▼
OfferPilotAgent  ──tool──►  NegotiatePrice  ──►  NegotiationService
   │                          │                    (deterministic pricing)
   │  reply                   │ quote_id
   ▼                          ▼
 Chat panel               quotes table
   │
   │  Pay with PayPal
   ▼
PayPalService  ──►  Orders v2: create → buyer approves → capture
                        └─ capture verified server-side (status + exact amount)
```

### How AI is used

- `App\Ai\Agents\OfferPilotAgent` is a Laravel AI SDK agent (`laravel/ai`) running on DeepSeek.
- Its only route to a price is the `negotiate_price` tool. The tool calls `NegotiationService`, so the model cannot invent, round, or negotiate past the rules.
- The agent is given the buyer's target and ceiling only. It is never given the merchant floor, so there is nothing for it to leak — by construction, not by instruction.
- Conversation history is persisted by the AI SDK's `RemembersConversations` trait.

### How PayPal is used

- `App\Services\PayPalService` calls **Orders v2** over HTTP with a cached OAuth token.
    - `POST /v2/checkout/orders` creates the order for the quoted amount.
    - The buyer approves in the PayPal Sandbox.
    - `POST /v2/checkout/orders/{id}/capture` captures payment.
- The browser is never trusted. `captureIsVerified()` re-checks the PayPal response for `COMPLETED` status, matching currency, and the **exact** quoted amount before an order is marked paid.

### Pricing rules

`NegotiationService` is deterministic:

- An offer at or above the merchant floor is accepted immediately (never above list price).
- An offer below the floor is countered from a declining ladder: the merchant moves a fraction toward the floor on each round, rounded to whole units.
- Every counter is clamped to the buyer's ceiling, or rejected with in-budget alternatives.
- Once the round limit is reached, the merchant makes a best-and-final offer at the floor.
- Money is stored and compared as integer minor units.

## Requirements

- PHP 8.4 and Composer
- Node 20+ and npm
- SQLite (the default), or edit `DB_CONNECTION` in `.env`
- A PayPal Developer sandbox app
- A DeepSeek API key

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
```

Add your credentials to `.env`:

```ini
PAYPAL_MODE=sandbox
PAYPAL_SANDBOX_URL=https://api-m.sandbox.paypal.com
PAYPAL_CLIENT_ID=your-sandbox-client-id
PAYPAL_CLIENT_SECRET=your-sandbox-client-secret
PAYPAL_MERCHANT_EMAIL=your-sandbox-business-account@business.example.com

AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=your-deepseek-key
```

Seed the demo catalogue:

```bash
php artisan db:seed --class=DemoStoreSeeder
```

Run it:

```bash
composer run dev      # Laravel + Vite together
# or: php artisan serve and npm run dev in separate terminals
```

Open `/store`.

## Trying it

1. Register an account (or `php artisan db:seed` for `test@example.com`).
2. Open `/store/HEADPHONES-PRO` — list price **$250.00**.
3. Enter a target of **200** and a maximum of **225**.
4. Ask: _"Can you do 200 for me?"_
5. The merchant counters at **$225.00** and a quote is held for 15 minutes.
6. Click the PayPal button and sign in with a **Sandbox personal** account.
7. After approval, the server captures the payment and the panel shows the verified capture.

### Sandbox accounts used during development

These are PayPal **sandbox** accounts only — they hold no real money:

- Business (receives funds): `sb-eely453766164@business.example.com`
- Personal (pays): `sb-p3k1f53759937@personal.example.com`

Create your own equivalents under **Developer Dashboard → Testing Tools → Sandbox Accounts**. Never reuse live credentials.

## Testing

```bash
php artisan test
vendor/bin/pint --test
```

The suite covers the negotiation ladder, ceiling clamping, alternative matching, the guarantee that the floor never appears in agent context or tool output, and PayPal capture verification including amount tampering and wrong-currency responses.

## Project layout

```
app/Ai/Agents/OfferPilotAgent.php     the negotiating assistant
app/Ai/Tools/NegotiatePrice.php       the agent's only route to a price
app/Services/NegotiationService.php   deterministic pricing rules
app/Services/PayPalService.php        Orders v2 create + verified capture
app/Http/Controllers/Storefront/      storefront, negotiation, checkout
resources/js/pages/store/             product list and negotiation panel
```

## Design system

`DESIGN.md` is the source of truth for colour, typography, spacing and component
rules, in Google's open [DESIGN.md](https://github.com/google-labs-code/design.md)
format. It lints clean:

```bash
npx -p @google/design.md designmd lint DESIGN.md
```

The brand accent is a single deep teal, contrast-verified in both themes
(5.11:1 on white, 8.36:1 on near-black).

## Security notes

- `.env` is ignored; only `.env.example` with empty placeholders is committed.
- Merchant floors live in `merchant_rules` and are never serialised to Inertia props, agent prompts, tool output, or logs.
- Every captured payment is verified against the quoted amount server-side.

## License

MIT — see [LICENSE](LICENSE).
