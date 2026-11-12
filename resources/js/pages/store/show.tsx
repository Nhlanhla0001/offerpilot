import { useEffect, useRef, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Check,
    ChevronRight,
    MessageSquare,
    Minus,
    Package,
    Plus,
    ShieldCheck,
    ShoppingBag,
    Sparkles,
    Truck,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { buy, checkout } from '@/routes/store';
import { capture } from '@/routes/checkout';
import CartDrawer, { cartRequest } from '@/components/store/cart-drawer';
import FloatingAssistant from '@/components/store/floating-assistant';
import ThemeToggle from '@/components/theme-toggle';

type Product = {
    sku: string;
    name: string;
    brand: string | null;
    category: string | null;
    type: string;
    description: string | null;
    features: string[];
    specifications: Record<string, string>;
    price: string;
    priceCents: number;
    image: string;
    available: boolean;
    availability: string;
    merchant: string;
};

type Quote = { id: string; price: string };

type PayPalButtonsConfig = {
    style?: Record<string, string>;
    createOrder: () => Promise<string>;
    onApprove: (data: { orderID: string }) => Promise<void>;
    onError?: (error: unknown) => void;
};

type PayPalSdk = {
    Buttons: (config: PayPalButtonsConfig) => {
        render: (selector: string) => void;
    };
};

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function postJson<T>(url: string, body: unknown): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(
            (data as { message?: string }).message ??
                'Something went wrong. Try again.',
        );
    }

    return data as T;
}

function centsFromInput(value: string): number {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0;
}

export default function StoreShow({
    product,
    paypalClientId,
}: {
    product: Product;
    paypalClientId: string | null;
}) {
    const { auth, role } = usePage().props as {
        auth: { user: { name: string } | null };
        role?: string;
    };

    const [quantity, setQuantity] = useState(1);
    const [quote, setQuote] = useState<Quote | null>(null);
    const [pending, setPending] = useState(false);
    const [added, setAdded] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [paid, setPaid] = useState<{
        reference: string | null;
        amount: string;
    } | null>(null);
    const [assistantRequest, setAssistantRequest] = useState<{
        intent: 'question' | 'offer';
    } | null>(null);

    const buttonsRendered = useRef(false);
    const authorizedCeilingCents = useRef(0);

    const maxQuantity = Math.max(
        1,
        Math.min(10, Number(product.availability.match(/\d+/)?.[0] ?? 1)),
    );

    const startQuote = (nextQuote: Quote, ceilingCents: number) => {
        authorizedCeilingCents.current = ceilingCents;
        buttonsRendered.current = false;
        setQuantity(1);
        setQuote(nextQuote);
    };

    const buyNow = async () => {
        setError(null);
        setPending(true);

        try {
            const data = await postJson<{ quote: Quote }>(
                buy(product.sku).url,
                {},
            );
            startQuote(data.quote, product.priceCents);
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Could not start checkout.',
            );
        } finally {
            setPending(false);
        }
    };

    const addToCart = async () => {
        setError(null);
        setPending(true);

        try {
            const summary = await cartRequest('POST', '/cart/items', {
                sku: product.sku,
                quantity,
                ...(quote ? { quote: quote.id } : {}),
            });

            setAdded(true);
            window.dispatchEvent(
                new CustomEvent('cart:updated', { detail: summary }),
            );
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Could not add to cart.',
            );
        } finally {
            setPending(false);
        }
    };

    useEffect(() => {
        if (!quote || !paypalClientId || paid) {
            return;
        }

        const renderButtons = () => {
            const paypal = (window as unknown as { paypal?: PayPalSdk }).paypal;

            if (!paypal || buttonsRendered.current) {
                return;
            }

            buttonsRendered.current = true;

            const host = document.getElementById('paypal-buttons');

            if (host) {
                host.innerHTML = '';
            }

            paypal
                .Buttons({
                    style: {
                        layout: 'vertical',
                        shape: 'rect',
                        label: 'paypal',
                    },
                    createOrder: async () => {
                        if (
                            centsFromInput(quote.price) >
                            authorizedCeilingCents.current
                        ) {
                            throw new Error(
                                'This price is above the maximum you authorised.',
                            );
                        }

                        const data = await postJson<{ paypalOrderId: string }>(
                            checkout(product.sku).url,
                            { quote: quote.id },
                        );

                        return data.paypalOrderId;
                    },
                    onApprove: async (data) => {
                        try {
                            const result = await postJson<{
                                status: string;
                                captureId: string | null;
                                amount: string;
                            }>(capture().url, {
                                paypal_order_id: data.orderID,
                            });

                            setPaid({
                                reference: result.captureId,
                                amount: result.amount,
                            });
                        } catch (exception) {
                            setError(
                                exception instanceof Error
                                    ? exception.message
                                    : 'Payment could not be verified.',
                            );
                        }
                    },
                    onError: (exception) => {
                        setError(
                            exception instanceof Error
                                ? exception.message
                                : 'PayPal reported an error.',
                        );
                    },
                })
                .render('#paypal-buttons');
        };

        if (document.getElementById('paypal-sdk')) {
            renderButtons();

            return;
        }

        const script = document.createElement('script');
        script.id = 'paypal-sdk';
        script.src = `https://www.paypal.com/sdk/js?client-id=${paypalClientId}&currency=USD&intent=capture`;
        script.addEventListener('load', renderButtons);
        document.body.appendChild(script);
    }, [quote, paypalClientId, paid, product.sku]);

    return (
        <>
            <Head title={product.name} />

            <div className="min-h-screen bg-background text-foreground">
                <div className="border-b border-border bg-primary text-primary-foreground">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-6 py-2 text-xs">
                        <p className="flex items-center gap-1.5">
                            <Truck aria-hidden="true" className="size-3.5" />
                            Flat $15 delivery on physical orders
                        </p>
                        <p className="hidden items-center gap-1.5 sm:flex">
                            <ShieldCheck
                                aria-hidden="true"
                                className="size-3.5"
                            />
                            Payments verified by PayPal
                        </p>
                    </div>
                </div>

                <header className="border-b border-border">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                        <Link href="/store" className="flex items-center gap-3">
                            <span
                                className="grid size-10 place-items-center rounded-md bg-brand text-sm font-semibold text-brand-foreground"
                                aria-hidden="true"
                            >
                                NA
                            </span>
                            <span className="leading-tight">
                                <span className="block text-sm font-semibold">
                                    {product.merchant}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Shop all products
                                </span>
                            </span>
                        </Link>

                        <div className="flex items-center gap-1 sm:gap-2">
                            <span className="hidden text-xs text-muted-foreground sm:block">
                                {role === 'guest' || !auth.user
                                    ? 'Guest checkout'
                                    : `${auth.user.name} · ${role === 'seller' ? 'Seller' : 'Shopper'}`}
                            </span>
                            <ThemeToggle className="size-11 md:size-9" />
                            <CartDrawer />
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-6">
                    <nav
                        aria-label="Breadcrumb"
                        className="flex items-center gap-1.5 overflow-x-auto py-4 text-xs text-muted-foreground"
                    >
                        <Link href="/store" className="hover:text-foreground">
                            Home
                        </Link>
                        <ChevronRight aria-hidden="true" className="size-3" />
                        {product.category && (
                            <>
                                <Link
                                    href="/store"
                                    className="hover:text-foreground"
                                >
                                    {product.category}
                                </Link>
                                <ChevronRight
                                    aria-hidden="true"
                                    className="size-3"
                                />
                            </>
                        )}
                        <span className="truncate text-foreground">
                            {product.name}
                        </span>
                    </nav>

                    <div className="grid gap-8 pb-12 lg:grid-cols-[minmax(0,1.08fr)_minmax(0,0.92fr)] lg:gap-12">
                        <div>
                            <div className="overflow-hidden rounded-lg border border-border bg-white">
                                <img
                                    src={product.image}
                                    alt={product.name}
                                    className="aspect-square w-full object-contain p-6 sm:p-10"
                                />
                            </div>

                            <div className="mt-3 grid grid-cols-3 gap-2 text-xs text-muted-foreground">
                                <p className="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-2">
                                    <Truck
                                        aria-hidden="true"
                                        className="size-3.5 shrink-0"
                                    />
                                    Flat $15 delivery
                                </p>
                                <p className="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-2">
                                    <ShieldCheck
                                        aria-hidden="true"
                                        className="size-3.5 shrink-0"
                                    />
                                    Verified payment
                                </p>
                                <p className="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-2">
                                    <Package
                                        aria-hidden="true"
                                        className="size-3.5 shrink-0"
                                    />
                                    {product.availability}
                                </p>
                            </div>
                        </div>

                        <div className="lg:sticky lg:top-6 lg:self-start">
                            <p className="text-xs font-medium tracking-wide text-brand-strong uppercase">
                                {product.brand}
                            </p>
                            <h1 className="mt-2 text-2xl leading-tight font-semibold tracking-tight sm:text-3xl">
                                {product.name}
                            </h1>

                            <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                                <span className="text-3xl font-semibold tabular-nums">
                                    ${product.price}
                                </span>
                                <Badge
                                    variant={
                                        product.available
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {product.availability}
                                </Badge>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Price in USD · delivery calculated at checkout
                            </p>

                            {product.features.length > 0 && (
                                <ul className="mt-5 space-y-2 border-y border-border py-4 text-sm">
                                    {product.features
                                        .slice(0, 4)
                                        .map((feature) => (
                                            <li
                                                key={feature}
                                                className="flex gap-2"
                                            >
                                                <Check
                                                    aria-hidden="true"
                                                    className="mt-0.5 size-4 shrink-0 text-brand-strong"
                                                />
                                                <span>{feature}</span>
                                            </li>
                                        ))}
                                </ul>
                            )}

                            {!paid && (
                                <div className="mt-5 space-y-3">
                                    <div className="flex items-center gap-3">
                                        <span className="text-sm font-medium">
                                            Quantity
                                        </span>
                                        <div className="flex h-11 items-center rounded-md border border-border">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setQuantity((current) =>
                                                        Math.max(
                                                            1,
                                                            current - 1,
                                                        ),
                                                    )
                                                }
                                                disabled={
                                                    quantity <= 1 ||
                                                    quote !== null ||
                                                    !product.available
                                                }
                                                aria-label="Decrease quantity"
                                                className="grid size-11 place-items-center text-muted-foreground disabled:opacity-40"
                                            >
                                                <Minus
                                                    aria-hidden="true"
                                                    className="size-4"
                                                />
                                            </button>
                                            <span
                                                className="w-8 text-center text-sm tabular-nums"
                                                aria-live="polite"
                                            >
                                                {quantity}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setQuantity((current) =>
                                                        Math.min(
                                                            maxQuantity,
                                                            current + 1,
                                                        ),
                                                    )
                                                }
                                                disabled={
                                                    quantity >= maxQuantity ||
                                                    quote !== null ||
                                                    !product.available
                                                }
                                                aria-label="Increase quantity"
                                                className="grid size-11 place-items-center text-muted-foreground disabled:opacity-40"
                                            >
                                                <Plus
                                                    aria-hidden="true"
                                                    className="size-4"
                                                />
                                            </button>
                                        </div>
                                    </div>

                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <Button
                                            variant="outline"
                                            className="h-12 text-base"
                                            onClick={addToCart}
                                            disabled={
                                                pending || !product.available
                                            }
                                        >
                                            <ShoppingBag
                                                aria-hidden="true"
                                                className="size-4"
                                            />
                                            {added
                                                ? 'Added to cart'
                                                : 'Add to cart'}
                                        </Button>
                                        <Button
                                            onClick={buyNow}
                                            disabled={
                                                pending || !product.available
                                            }
                                            className="h-12 bg-brand text-base text-brand-foreground hover:bg-brand-strong"
                                        >
                                            Buy now
                                        </Button>
                                    </div>

                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <Button
                                            variant="ghost"
                                            className="h-11 justify-start"
                                            onClick={() =>
                                                setAssistantRequest({
                                                    intent: 'offer',
                                                })
                                            }
                                        >
                                            <Sparkles
                                                aria-hidden="true"
                                                className="size-4 text-brand-strong"
                                            />
                                            Make an offer
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            className="h-11 justify-start"
                                            onClick={() =>
                                                setAssistantRequest({
                                                    intent: 'question',
                                                })
                                            }
                                        >
                                            <MessageSquare
                                                aria-hidden="true"
                                                className="size-4 text-muted-foreground"
                                            />
                                            Ask AI
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {error && (
                                <p
                                    role="alert"
                                    className="mt-3 text-sm text-destructive"
                                >
                                    {error}
                                </p>
                            )}

                            {quote && !paid && (
                                <div className="mt-5 rounded-lg border border-brand/40 bg-brand/5 p-4">
                                    <p className="flex items-center gap-2 text-sm font-semibold text-brand-strong">
                                        <BadgeCheck
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        Price held for you: ${quote.price}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {product.name} from {product.merchant}.
                                        You will be charged ${quote.price} USD.
                                    </p>
                                    <div id="paypal-buttons" className="mt-3" />
                                </div>
                            )}

                            {paid && (
                                <div className="mt-5 rounded-lg border border-brand/40 bg-brand/5 p-4">
                                    <p className="flex items-center gap-2 text-sm font-semibold text-brand-strong">
                                        <BadgeCheck
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        Payment verified
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        ${paid.amount} charged · reference{' '}
                                        {paid.reference}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-10 border-t border-border py-10 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,0.6fr)] lg:gap-16">
                        <div>
                            <h2 className="text-xl font-semibold">
                                About this item
                            </h2>
                            <p className="mt-3 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                                {product.description}
                            </p>

                            {product.features.length > 0 && (
                                <ul className="mt-5 max-w-2xl space-y-2 text-sm">
                                    {product.features.map((feature) => (
                                        <li
                                            key={feature}
                                            className="flex gap-2"
                                        >
                                            <Check
                                                aria-hidden="true"
                                                className="mt-0.5 size-4 shrink-0 text-brand-strong"
                                            />
                                            <span>{feature}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div>
                            <h2 className="text-sm font-semibold">Details</h2>
                            <dl className="mt-3 divide-y divide-border border-y border-border text-sm">
                                {Object.entries(product.specifications).map(
                                    ([label, value]) => (
                                        <div
                                            key={label}
                                            className="grid grid-cols-[0.9fr_1.1fr] gap-3 py-2.5"
                                        >
                                            <dt className="text-muted-foreground">
                                                {label}
                                            </dt>
                                            <dd className="font-medium">
                                                {value}
                                            </dd>
                                        </div>
                                    ),
                                )}
                            </dl>
                        </div>
                    </div>

                    <section className="border-t border-border py-10">
                        <div className="grid gap-6 sm:grid-cols-3">
                            <div>
                                <ShieldCheck
                                    aria-hidden="true"
                                    className="size-5 text-brand-strong"
                                />
                                <h2 className="mt-2 text-sm font-semibold">
                                    A price the shop will honour
                                </h2>
                                <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                    Offers are checked by the seller&apos;s
                                    rules, not improvised by the assistant.
                                </p>
                            </div>
                            <div>
                                <BadgeCheck
                                    aria-hidden="true"
                                    className="size-5 text-brand-strong"
                                />
                                <h2 className="mt-2 text-sm font-semibold">
                                    Verified PayPal capture
                                </h2>
                                <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                    The order is marked paid only after PayPal
                                    confirms the exact amount in USD.
                                </p>
                            </div>
                            <div>
                                <Package
                                    aria-hidden="true"
                                    className="size-5 text-brand-strong"
                                />
                                <h2 className="mt-2 text-sm font-semibold">
                                    Shipped from stock
                                </h2>
                                <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                    Physical orders leave from the seller&apos;s
                                    inventory at a flat delivery rate.
                                </p>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-border">
                    <div className="mx-auto flex max-w-6xl flex-col gap-2 px-6 py-8 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <p>
                            {product.merchant} · Sold and fulfilled by the
                            merchant
                        </p>
                        <p>Payments secured by PayPal Sandbox</p>
                    </div>
                </footer>

                <FloatingAssistant
                    merchantName={product.merchant}
                    productContext={{
                        sku: product.sku,
                        name: product.name,
                        price: product.price,
                        image: product.image,
                    }}
                    openRequest={assistantRequest}
                    onOpenRequestHandled={() => setAssistantRequest(null)}
                />
            </div>
        </>
    );
}
