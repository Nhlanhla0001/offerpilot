import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { checkout, negotiate } from '@/routes/store';
import { capture } from '@/routes/checkout';

type Product = {
    sku: string;
    name: string;
    description: string | null;
    price: string;
    priceCents: number;
    stock: number;
    merchant: string;
};

type Quote = {
    id: string;
    price: string;
    expiresAt: string | null;
};

type ChatMessage = {
    role: 'buyer' | 'offerpilot';
    text: string;
};

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
    const { auth } = usePage().props as {
        auth: { user: { name: string } | null };
    };

    const [target, setTarget] = useState('');
    const [ceiling, setCeiling] = useState('');
    const [message, setMessage] = useState('');
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [quote, setQuote] = useState<Quote | null>(null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [paid, setPaid] = useState<{
        captureId: string | null;
        amount: string;
    } | null>(null);

    const buttonsRendered = useRef(false);
    const authorizedCeilingCents = useRef(0);

    const send = async (event: FormEvent) => {
        event.preventDefault();
        setError(null);

        const targetCents = centsFromInput(target);
        const ceilingCents = centsFromInput(ceiling);

        if (targetCents <= 0 || ceilingCents < targetCents) {
            setError('Set a target, and a maximum at or above it.');

            return;
        }

        setPending(true);
        setQuote(null);
        buttonsRendered.current = false;
        authorizedCeilingCents.current = ceilingCents;
        setMessages((current) => [
            ...current,
            { role: 'buyer', text: message },
        ]);
        setMessage('');

        try {
            const data = await postJson<{ reply: string; quote: Quote | null }>(
                negotiate(product.sku).url,
                {
                    message,
                    target_price_cents: targetCents,
                    ceiling_price_cents: ceilingCents,
                },
            );

            setMessages((current) => [
                ...current,
                { role: 'offerpilot', text: data.reply },
            ]);

            if (data.quote) {
                setQuote(data.quote);
                buttonsRendered.current = false;
            }
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Something went wrong.',
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
                                'This offer is above the maximum you authorised.',
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
                                captureId: result.captureId,
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

            <div className="min-h-screen bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <header className="border-b border-[#19140014] dark:border-[#3E3E3A]">
                    <div className="mx-auto flex max-w-3xl items-center justify-between px-6 py-5">
                        <Link
                            href="/store"
                            className="text-sm text-[#706f6c] underline-offset-4 hover:underline dark:text-[#A1A09A]"
                        >
                            ← All products
                        </Link>
                        <span className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                            {auth.user
                                ? auth.user.name
                                : 'Sign in to negotiate'}
                        </span>
                    </div>
                </header>

                <main className="mx-auto grid max-w-3xl gap-8 px-6 py-10">
                    <section>
                        <p className="text-xs tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">
                            {product.merchant}
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            {product.name}
                        </h1>
                        <p className="mt-2 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                            {product.description}
                        </p>
                        <p className="mt-4 text-xl font-semibold">
                            ${product.price}
                        </p>
                        <p className="text-xs text-[#706f6c] dark:text-[#A1A09A]">
                            {product.stock} in stock
                        </p>
                    </section>

                    {!auth.user ? (
                        <section className="rounded-lg border border-[#19140014] p-5 dark:border-[#3E3E3A]">
                            <p className="text-sm">
                                Sign in to negotiate a price.
                            </p>
                            <Link
                                href="/login"
                                className="mt-3 inline-block rounded-sm border border-[#19140035] px-4 py-1.5 text-sm dark:border-[#3E3E3A]"
                            >
                                Log in
                            </Link>
                        </section>
                    ) : (
                        <section className="rounded-lg border border-[#19140014] p-5 dark:border-[#3E3E3A]">
                            <h2 className="text-sm font-medium">
                                Your mandate
                            </h2>
                            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                <label className="text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                    Target price
                                    <input
                                        value={target}
                                        onChange={(event) =>
                                            setTarget(event.target.value)
                                        }
                                        inputMode="decimal"
                                        placeholder="200"
                                        className="mt-1 w-full rounded-sm border border-[#19140035] px-3 py-2 text-sm text-[#1b1b18] dark:border-[#3E3E3A] dark:bg-transparent dark:text-[#EDEDEC]"
                                    />
                                </label>
                                <label className="text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                    Maximum you will pay
                                    <input
                                        value={ceiling}
                                        onChange={(event) =>
                                            setCeiling(event.target.value)
                                        }
                                        inputMode="decimal"
                                        placeholder="225"
                                        className="mt-1 w-full rounded-sm border border-[#19140035] px-3 py-2 text-sm text-[#1b1b18] dark:border-[#3E3E3A] dark:bg-transparent dark:text-[#EDEDEC]"
                                    />
                                </label>
                            </div>

                            <div className="mt-5 space-y-3">
                                {messages.map((entry, index) => (
                                    <p
                                        key={index}
                                        className={
                                            entry.role === 'buyer'
                                                ? 'ml-8 rounded-lg bg-[#1914000f] px-3 py-2 text-sm dark:bg-[#ffffff14]'
                                                : 'mr-8 rounded-lg border border-[#19140014] px-3 py-2 text-sm dark:border-[#3E3E3A]'
                                        }
                                    >
                                        {entry.text}
                                    </p>
                                ))}
                            </div>

                            <form onSubmit={send} className="mt-4 flex gap-2">
                                <input
                                    value={message}
                                    onChange={(event) =>
                                        setMessage(event.target.value)
                                    }
                                    placeholder="Can you do 200?"
                                    className="flex-1 rounded-sm border border-[#19140035] px-3 py-2 text-sm dark:border-[#3E3E3A] dark:bg-transparent"
                                />
                                <button
                                    type="submit"
                                    disabled={pending || message.trim() === ''}
                                    className="rounded-sm border border-[#19140035] px-4 py-2 text-sm disabled:opacity-50 dark:border-[#3E3E3A]"
                                >
                                    {pending ? '…' : 'Send'}
                                </button>
                            </form>

                            {error && (
                                <p className="mt-3 text-sm text-red-600">
                                    {error}
                                </p>
                            )}

                            {quote && (
                                <div className="mt-5 rounded-md border border-[#19140014] p-4 dark:border-[#3E3E3A]">
                                    <p className="text-sm font-medium">
                                        Quote held at ${quote.price}
                                    </p>
                                    <p className="mt-1 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                        Valid for 15 minutes. Pay with PayPal
                                        Sandbox to settle it.
                                    </p>
                                    <div
                                        id="paypal-buttons"
                                        className={paid ? 'hidden' : 'mt-3'}
                                    />
                                </div>
                            )}

                            {paid && (
                                <div className="mt-5 rounded-md border border-emerald-600/40 p-4">
                                    <p className="text-sm font-medium">
                                        Payment verified
                                    </p>
                                    <p className="mt-1 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                        ${paid.amount} captured ·{' '}
                                        {paid.captureId}
                                    </p>
                                </div>
                            )}
                        </section>
                    )}
                </main>
            </div>
        </>
    );
}
