import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { buy, checkout, negotiate } from '@/routes/store';
import { capture } from '@/routes/checkout';

type Product = {
    sku: string;
    name: string;
    brand: string | null;
    category: string | null;
    type: string;
    description: string | null;
    price: string;
    priceCents: number;
    image: string;
    available: boolean;
    availability: string;
    merchant: string;
};

type Quote = { id: string; price: string };

type ChatMessage = { role: 'buyer' | 'assistant'; text: string };

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

const PROMPTS = [
    'Can you do a better price?',
    'What is included in the box?',
    'Is this right for my setup?',
];

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

    const [open, setOpen] = useState(false);
    const [message, setMessage] = useState('');
    const [ceiling, setCeiling] = useState('');
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [quote, setQuote] = useState<Quote | null>(null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [paid, setPaid] = useState<{
        reference: string | null;
        amount: string;
    } | null>(null);

    const buttonsRendered = useRef(false);
    const authorizedCeilingCents = useRef(0);
    const transcriptRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        transcriptRef.current?.scrollTo({
            top: transcriptRef.current.scrollHeight,
            behavior: 'smooth',
        });
    }, [messages, pending, open]);

    const startQuote = (nextQuote: Quote, ceilingCents: number) => {
        authorizedCeilingCents.current = ceilingCents;
        buttonsRendered.current = false;
        setQuote(nextQuote);
        setOpen(false);
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

    const send = async (event: FormEvent) => {
        event.preventDefault();

        if (message.trim() === '') {
            return;
        }

        setError(null);
        setPending(true);
        setQuote(null);
        buttonsRendered.current = false;

        const ceilingCents = centsFromInput(ceiling);
        const text = message;
        setMessages((current) => [...current, { role: 'buyer', text }]);
        setMessage('');

        try {
            const data = await postJson<{ reply: string; quote: Quote | null }>(
                negotiate(product.sku).url,
                {
                    message: text,
                    ...(ceilingCents > 0
                        ? { ceiling_price_cents: ceilingCents }
                        : {}),
                },
            );

            setMessages((current) => [
                ...current,
                { role: 'assistant', text: data.reply },
            ]);

            if (data.quote) {
                startQuote(
                    data.quote,
                    ceilingCents > 0 ? ceilingCents : product.priceCents,
                );
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
                <header className="border-b border-border">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                        <Link
                            href="/store"
                            className="flex h-11 items-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            ← {product.merchant}
                        </Link>
                        <span className="text-sm text-muted-foreground">
                            {auth.user ? auth.user.name : 'Browsing as a guest'}
                        </span>
                    </div>
                </header>

                <main className="mx-auto grid max-w-6xl gap-10 px-6 py-10 lg:grid-cols-[1.1fr_1fr] lg:gap-16">
                    <div className="bg-surface overflow-hidden rounded-lg border border-border">
                        <img
                            src={product.image}
                            alt={product.name}
                            className="aspect-square w-full object-cover"
                        />
                    </div>

                    <div className="lg:sticky lg:top-10 lg:self-start">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            {product.category}
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                            {product.name}
                        </h1>
                        {product.brand && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {product.brand}
                            </p>
                        )}

                        <div className="mt-5 flex items-baseline gap-3">
                            <span className="text-3xl font-semibold tabular-nums">
                                ${product.price}
                            </span>
                            <Badge
                                variant={
                                    product.available ? 'secondary' : 'outline'
                                }
                            >
                                {product.availability}
                            </Badge>
                        </div>

                        <p className="mt-4 text-sm leading-relaxed text-muted-foreground">
                            {product.description}
                        </p>

                        {!paid && (
                            <div className="mt-6 grid gap-2 sm:grid-cols-2">
                                <Button
                                    onClick={buyNow}
                                    disabled={pending || !product.available}
                                    className="h-11 bg-brand text-brand-foreground hover:bg-brand-strong"
                                >
                                    Buy now — ${product.price}
                                </Button>
                                <Button
                                    variant="outline"
                                    className="h-11"
                                    onClick={() => setOpen(true)}
                                >
                                    Ask or make an offer
                                </Button>
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
                            <div className="mt-6 rounded-lg border border-border p-4">
                                <p className="text-sm font-medium">
                                    Agreed price{' '}
                                    <span className="tabular-nums">
                                        ${quote.price}
                                    </span>
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {product.name} from {product.merchant}. You
                                    will be charged ${quote.price} USD.
                                </p>
                                <div id="paypal-buttons" className="mt-3" />
                            </div>
                        )}

                        {paid && (
                            <div className="mt-6 rounded-lg border border-brand/40 bg-brand/5 p-4">
                                <p className="text-sm font-semibold text-brand-strong">
                                    Payment verified
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    ${paid.amount} charged · reference{' '}
                                    {paid.reference}
                                </p>
                            </div>
                        )}
                    </div>
                </main>
            </div>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-md">
                    <SheetHeader className="border-b border-border px-5 py-4">
                        <SheetTitle className="text-base">
                            {product.merchant}&apos;s assistant
                        </SheetTitle>
                        <SheetDescription className="text-xs">
                            Ask about {product.name}, or say what you would pay.
                            This is an automated assistant acting for the
                            seller.
                        </SheetDescription>
                    </SheetHeader>

                    <div
                        ref={transcriptRef}
                        role="log"
                        aria-live="polite"
                        aria-label="Conversation with the seller's assistant"
                        className="flex-1 space-y-3 overflow-y-auto px-5 py-4"
                    >
                        {messages.length === 0 && (
                            <div className="space-y-3">
                                <p className="text-sm text-muted-foreground">
                                    Ask a question, or tell me what you would
                                    like to pay.
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {PROMPTS.map((prompt) => (
                                        <button
                                            key={prompt}
                                            type="button"
                                            onClick={() => setMessage(prompt)}
                                            className="rounded-full border border-border px-3 py-1.5 text-xs text-muted-foreground hover:text-foreground"
                                        >
                                            {prompt}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {messages.map((entry, index) => (
                            <div
                                key={index}
                                className={
                                    entry.role === 'buyer'
                                        ? 'bg-surface ml-6 rounded-lg px-3 py-2 text-sm'
                                        : 'mr-6 rounded-lg border border-border px-3 py-2 text-sm'
                                }
                            >
                                <span className="mb-1 block text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                    {entry.role === 'buyer'
                                        ? 'You'
                                        : 'Assistant'}
                                </span>
                                {entry.text}
                            </div>
                        ))}

                        {pending && (
                            <p className="text-sm text-muted-foreground">
                                Thinking…
                            </p>
                        )}
                    </div>

                    <form
                        onSubmit={send}
                        className="space-y-3 border-t border-border px-5 py-4"
                    >
                        <div>
                            <Label htmlFor="ceiling" className="text-xs">
                                Most you will pay (optional)
                            </Label>
                            <Input
                                id="ceiling"
                                value={ceiling}
                                onChange={(event) =>
                                    setCeiling(event.target.value)
                                }
                                inputMode="decimal"
                                placeholder="We will never go above this"
                                className="mt-1 h-11 text-base"
                            />
                        </div>

                        <div className="flex gap-2">
                            <Input
                                value={message}
                                onChange={(event) =>
                                    setMessage(event.target.value)
                                }
                                placeholder="Ask a question, or name a price"
                                aria-label="Message the seller's assistant"
                                className="h-11 text-base"
                            />
                            <Button
                                type="submit"
                                disabled={pending}
                                className="h-11 shrink-0 bg-brand text-brand-foreground hover:bg-brand-strong"
                            >
                                Send
                            </Button>
                        </div>

                        {error && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {error}
                            </p>
                        )}
                    </form>
                </SheetContent>
            </Sheet>
        </>
    );
}
