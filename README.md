# OfferPilot

**Name your budget. The merchant meets you where it can.**

OfferPilot is a seller-operated commerce platform with an AI sales assistant. Buyers can shop at the listed price, ask questions, or make an offer; the assistant can only negotiate up to a maximum the buyer explicitly confirms, and accepted prices become server-issued quotes.

The design goal is trust in both directions:

- **Merchants keep their floors.** A product's minimum price lives in a private table and never reaches the browser, the logs, or the language model.
- **Buyers keep their ceilings.** Every price the app offers is checked against the buyer's maximum before it is shown.
- **The AI never does arithmetic.** It negotiates the conversation; a deterministic service decides every number.

## How it works

```
Browser (Inertia + React)
   │  buyer confirms a maximum, then sends a message
   ▼
StorefrontAssistant ──tool──► NegotiateStorePrice ──► NegotiationService
   │                            │                        (deterministic pricing)
   │  reply + buyer actions     │ quote_id
   ▼                            ▼
 Chat panel                 quotes table
   │ buyer confirms adding a quote to cart
   ▼
 Cart ──► Checkout ──► PayPal approval
   ▼
PayPalService  ──►  Orders v2: create → buyer approves → capture
                        └─ capture verified server-side (status + exact amount)
```

### How AI is used

- `App\Ai\Agents\StorefrontAssistant` is the storefront's Laravel AI SDK agent (`laravel/ai`) running on DeepSeek.
- Its only route to a negotiated price is `NegotiateStorePrice`, which calls `NegotiationService`. The maximum is read from the buyer's explicit, product-specific authorization in their session; it is never accepted from the model's tool arguments.
- The assistant receives the buyer-authorized maximum and public catalogue facts, never the merchant floor or cost. Search, product lookup, and negotiation results are server-generated and buyer-safe.
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
php artisan db:seed --no-interaction
```

Run it:

```bash
composer run dev      # Laravel + Vite together
# or: php artisan serve and npm run dev in separate terminals
```

Open `/` for the product overview, then `/store` for the demo shop.

## Trying it

**No account is needed to buy.** Open `/store/HEADPHONES-PRO` — list price **$250.00**.

1. Ask the assistant a product question; it answers from published shop information.
2. Explicitly confirm the maximum you authorize for that product, then make an offer. The deterministic pricing service decides the result.
3. Accept a held quote and confirm adding it to your cart. Nothing is added without your confirmation.
4. Review your cart and check out with a PayPal **Sandbox personal** account. After approval, the server verifies the capture before confirming the order.

The seller side starts at `/dashboard`:

1. Sign in as `test@example.com` / `password`.
2. **Offers** lists every negotiation: what the buyer asked for, what the shop held, and how many rounds it took.
3. **Inventory** is the only screen that shows the private minimum. **Orders** lists verified captures. **Notifications** reports them.

### Demo accounts

| Role                | Email                   | Password   | Lands on     |
| ------------------- | ----------------------- | ---------- | ------------ |
| Seller (Northsound) | `test@example.com`      | `password` | `/dashboard` |
| Shopper (no shop)   | `buyer@offerpilot.test` | `password` | `/store`     |

A visitor who never signs in is a **guest**: they can ask questions, make an offer and pay. Quotes are bound to the browser session, so one guest can never reach another's quote. A signed-in user without a shop is a **shopper** and is kept out of the seller workspace.

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

The suite covers the negotiation ladder, ceiling clamping, alternative matching, the guarantee that the floor never appears in agent context or tool output, and PayPal capture verification including amount tampering and wrong-currency responses. It also covers guest checkout and quote ownership, seller search, the offers list, and role routing.

## Project layout

```
app/Ai/Agents/OfferPilotAgent.php     the negotiating assistant
app/Ai/Tools/NegotiatePrice.php       the agent's only route to a price
app/Services/NegotiationService.php   deterministic pricing rules
app/Services/PayPalService.php        Orders v2 create + verified capture
app/Services/BuyerSession.php         the guest's identity, bound to the session
app/Http/Controllers/Storefront/      storefront, negotiation, checkout
app/Http/Controllers/Seller/          dashboard, offers, inventory, orders, notifications
resources/js/pages/welcome.tsx        the product overview at /
resources/js/pages/store/             the shop and product pages
resources/js/pages/offers/            the seller's negotiation list
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
- Merchant floors live in `merchant_rules` and are never serialised to buyer pages, agent prompts, tool output, or buyer-visible logs. The seller's own inventory screen is the only place they appear.
- Every captured payment is verified against the quoted amount server-side.

## Demo imagery

The shop's products and brands are fictional. Product photographs are licensed
stock images from [Pexels](https://www.pexels.com/license/), used as stand-ins
rather than as photographs of the listed products.

| Product             | Pexels photo                                       |
| ------------------- | -------------------------------------------------- |
| Studio headphones   | [3394650](https://www.pexels.com/photo/3394650/)   |
| Wireless earbuds    | [16703782](https://www.pexels.com/photo/16703782/) |
| Desk microphone     | [13044705](https://www.pexels.com/photo/13044705/) |
| Ultrabook           | [10655906](https://www.pexels.com/photo/10655906/) |
| 4K monitor          | [21325134](https://www.pexels.com/photo/21325134/) |
| Mechanical keyboard | [13728045](https://www.pexels.com/photo/13728045/) |
| Studio consultation | [164755](https://www.pexels.com/photo/164755/)     |

## License

MIT — see [LICENSE](LICENSE).
