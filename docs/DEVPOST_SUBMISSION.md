# OfferPilot — Name your budget. The merchant meets you where it can.

**GitHub:** https://github.com/Nhlanhla0001/offerpilot
**YouTube demo:** [ADD PUBLIC YOUTUBE URL BEFORE SUBMITTING]

## Inspiration and problem

Small merchants often have room to negotiate, but cannot answer every buyer question or evaluate every offer in real time. Buyers want useful product advice and a clear path from an agreed price to a trusted payment. A generic chatbot can invent discounts; a checkout alone cannot explain a product or negotiate.

## What OfferPilot does

OfferPilot is a seller-operated demo storefront for product discovery, AI-assisted conversation, controlled negotiation, a cart, and PayPal Sandbox checkout. A buyer can browse the catalogue, ask product questions, explicitly set a maximum price for a product, make an offer, accept a server-issued quote, and complete a sandbox purchase. Sellers can review offers, inventory, orders, and notifications in their workspace.

## How it works

The storefront assistant uses the Laravel AI SDK with DeepSeek. It can use server-side product lookup, search, negotiation, and cart-proposal tools. It receives published catalogue facts and a buyer-authorized ceiling. It does not receive the merchant's private minimum price. The assistant handles conversation; `NegotiationService` calculates the actual price in integer minor units. The server applies the merchant floor, counteroffer ladder, round limit, and buyer ceiling before issuing a quote. The buyer must confirm before a proposed cart action is applied.

PayPal is central to the purchase flow. The server creates an Orders v2 order for the quoted amount; the buyer approves through PayPal Sandbox; the server captures the order and checks completed status, currency, and exact amount before marking it paid. Seller Orders then displays the verified payment information.

## Who it serves

OfferPilot is designed for merchants who want to set clear negotiation boundaries and shoppers who want answers, a fair offer process, and a familiar payment flow.

## Built with

Laravel and PHP, Inertia with React and TypeScript, SQLite for local setup, Laravel AI SDK with DeepSeek, and PayPal Orders v2 with the PayPal Sandbox.

## Development and challenges

The work focused on connecting natural conversation to deterministic commerce controls. The main challenge was keeping private seller floors and buyer ceilings out of AI-generated pricing decisions while still making negotiation feel conversational. A separate payment boundary verifies PayPal's capture response before an order becomes paid. The repository includes tests for negotiation boundaries, quote ownership, storefront and cart behavior, and capture verification. The repository history and current source show the implementation; the entrant should confirm which changes were made during the hackathon period before submission.

## How judges can try it

Clone the public repository and follow `docs/JUDGE_SETUP.md` or the README. Seed the demo catalogue, configure your own DeepSeek key and PayPal Sandbox credentials, then open `/store`. Browse the Northsound products, ask the assistant a question, set a maximum and make an offer, accept the quote, and pay using a PayPal Sandbox personal test account. Sign in to the seller workspace and check `/orders` for the verified capture. The app is intended for local installation; it is not presented as a publicly hosted production service.

## Disclosures and limitations

This is a sandbox demonstration: no real money is charged. A DeepSeek API key and PayPal Sandbox developer credentials are required for full end-to-end testing; these are not published in the repository. Demo seller credentials are seeded locally and are for testing only. The entrant must confirm original ownership, any third-party asset permissions, eligibility, and the extent of work completed during the submission period. No public YouTube link has been inserted yet.
