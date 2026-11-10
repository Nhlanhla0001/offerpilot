# PRODUCT.md

The product truth for OfferPilot. `README.md` explains how to run it, `AGENTS.md`
tells agents how to work on it, `DESIGN.md` defines how it looks. This file
defines what it is. When the four disagree, this one wins.

## Identity

**OfferPilot is a seller-operated commerce platform with an AI sales assistant.**

One line: _Turn buyer conversations into completed sales — without giving away
your margins._

The seller operates OfferPilot. The buyer shops with OfferPilot.

## The problem

Small merchants lose sales when a buyer hesitates, asks a question nobody
answers, or walks away over price. Hiring a salesperson for every conversation
does not scale, and giving an AI permission to discount is how a merchant loses
their margin.

## The product

A merchant gets a branded shop, inventory, a dashboard, orders, notifications, and
an assistant that answers questions and negotiates within limits the merchant
sets privately.

A buyer gets a normal shop with three equal ways to buy: at the listed price,
after asking a question, or after making an offer.

The assistant talks to the buyer and calls server-side tools. It never decides a
price or approves a discount. Laravel decides; PayPal settles; Laravel verifies.

## Roles

| Role          | Does                                                                                                                               | Never does                                                         |
| ------------- | ---------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| **Seller**    | Configures products, services, branding, listed prices, private minimums, negotiation rules; sees inventory, orders, notifications | Sees a buyer's private data beyond their order                     |
| **Buyer**     | Browses, asks questions, offers, accepts, pays                                                                                     | Configures rules, sees minimums, needs an account                  |
| **Assistant** | Understands intent, calls tools, explains outcomes, recommends alternatives                                                        | Invents prices, approves discounts, sees minimums, touches payment |

## Five principles, and how each is enforced

| Principle                 | Enforced by                                                                           | Verified by                                                                |
| ------------------------- | ------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| **Seller control**        | Merchant owns every product; policy on each seller route                              | Cross-seller access tests                                                  |
| **Margin protection**     | The private minimum is never in a prompt, tool result, Inertia prop, or log           | Tests assert the floor value never appears in agent context or tool output |
| **Deterministic pricing** | `NegotiationService` decides; the assistant has no arithmetic                         | Unit tests over every branch of the ladder                                 |
| **Natural shopping**      | Buy-now creates a list-price quote and never calls the assistant                      | Feature test: purchase completes with the AI unavailable                   |
| **Verified transactions** | Capture is re-verified server-side against the exact amount before an order is `paid` | Tampered-amount, wrong-currency, non-completed tests                       |

## The AI boundary

The assistant receives an **authorised result**, never the rules.

```
buyer offers 200
  → evaluate_offer(target_price_cents: 20000)
    → NegotiationService reads the private configuration
    → returns { status: countered, price: "240.00", can_counter: true }
  → the assistant explains the counter
```

The assistant is never told why, and never told the minimum.

### Tools

| Tool               | Responsibility                     |
| ------------------ | ---------------------------------- |
| `get_product`      | Buyer-safe product information     |
| `search_inventory` | Find available items               |
| `evaluate_offer`   | Get an authorised pricing decision |
| `get_alternatives` | Items inside the buyer's budget    |

Deliberately **not** tools: accepting an offer and creating a checkout. Those are
explicit buyer actions, validated server-side. An assistant that could accept on
a buyer's behalf, or create payment orders, is a liability.

## Money

Amounts are integer minor units (cents). Currency is USD for this build. Format
with a single formatter; never interpolate raw minor units into copy.

## What OfferPilot is not

A multi-vendor marketplace. A buyer-side shopping agent. Two AIs negotiating. A
chatbot with a pay button. A warehouse system. A payment processor.

## Non-goals for this build

Multi-currency, booking calendars, product variants, supplier management, stock
audit trails, page-view analytics, refunds workflow.

## Success

A judge can: configure an item as a seller, browse the shop as a guest, buy one
item outright, offer on another, be countered, accept, pay through PayPal
sandbox, and see the verified order in the seller's dashboard.
