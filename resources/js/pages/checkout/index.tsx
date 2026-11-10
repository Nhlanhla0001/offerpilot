import {
    useEffect,
    useRef,
    useState,
    type ChangeEvent,
    type FormEvent,
} from 'react';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ThemeToggle from '@/components/theme-toggle';

type Cart = {
    items: {
        id: number;
        name: string;
        quantity: number;
        unit_price: string;
        line_total: string;
        image: string;
    }[];
    count: number;
    subtotal: string;
    shipping: string;
    total: string;
    currency: string;
    has_physical: boolean;
};

type PayPalSdk = {
    Buttons: (config: {
        style?: Record<string, string>;
        createOrder: () => Promise<string>;
        onApprove: (data: { orderID: string }) => Promise<void>;
        onError?: (error: unknown) => void;
    }) => { render: (selector: string) => void };
};

function xsrf(): string {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

async function postJson<T>(url: string, body: unknown): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrf(),
        },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(
            (data as { message?: string }).message ?? 'Something went wrong.',
        );
    }
    return data as T;
}

export default function Checkout({
    cart,
    paypalClientId,
}: {
    cart: Cart;
    paypalClientId: string | null;
}) {
    const [form, setForm] = useState({
        name: '',
        email: '',
        address: '',
        city: '',
        postal_code: '',
        country: 'South Africa',
    });
    const [paypalOrderId, setPaypalOrderId] = useState<string | null>(null);
    const [orderNumber, setOrderNumber] = useState<string | null>(null);
    const [paid, setPaid] = useState<{
        orderNumber: string;
        amount: string;
    } | null>(null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const rendered = useRef(false);

    const set =
        (key: keyof typeof form) => (e: ChangeEvent<HTMLInputElement>) =>
            setForm((current) => ({ ...current, [key]: e.target.value }));

    const startPayment = async (event: FormEvent) => {
        event.preventDefault();
        setError(null);
        setPending(true);

        try {
            const data = await postJson<{
                paypalOrderId: string;
                orderNumber: string;
            }>('/checkout', form);
            setPaypalOrderId(data.paypalOrderId);
            setOrderNumber(data.orderNumber);
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Could not start payment.',
            );
        } finally {
            setPending(false);
        }
    };

    useEffect(() => {
        if (!paypalOrderId || !paypalClientId || paid || rendered.current) {
            return;
        }

        const render = () => {
            const paypal = (window as unknown as { paypal?: PayPalSdk }).paypal;
            const host = document.getElementById('paypal-buttons');

            if (!paypal || !host || rendered.current) {
                return;
            }

            rendered.current = true;
            host.innerHTML = '';

            paypal
                .Buttons({
                    style: {
                        layout: 'vertical',
                        shape: 'rect',
                        label: 'paypal',
                    },
                    createOrder: async () => paypalOrderId,
                    onApprove: async (data) => {
                        try {
                            const result = await postJson<{
                                orderNumber: string;
                                amount: string;
                            }>('/checkout/complete', {
                                paypal_order_id: data.orderID,
                            });
                            setPaid(result);
                        } catch (exception) {
                            setError(
                                exception instanceof Error
                                    ? exception.message
                                    : 'Payment could not be verified.',
                            );
                        }
                    },
                    onError: (exception) =>
                        setError(
                            exception instanceof Error
                                ? exception.message
                                : 'PayPal reported an error.',
                        ),
                })
                .render('#paypal-buttons');
        };

        if (document.getElementById('paypal-sdk')) {
            render();
            return;
        }

        const script = document.createElement('script');
        script.id = 'paypal-sdk';
        script.src = `https://www.paypal.com/sdk/js?client-id=${paypalClientId}&currency=USD&intent=capture`;
        script.addEventListener('load', render);
        document.body.appendChild(script);
    }, [paypalOrderId, paypalClientId, paid]);

    if (paid) {
        return (
            <>
                <Head title="Order confirmed" />
                <div className="mx-auto max-w-xl px-6 py-16 text-center">
                    <p className="text-xs font-medium tracking-wide text-brand-strong uppercase">
                        Payment verified
                    </p>
                    <h1 className="mt-2 text-2xl font-semibold">
                        Thank you — your order is confirmed
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Order {paid.orderNumber} · ${paid.amount} charged
                    </p>
                    <Button
                        asChild
                        className="mt-6 h-11 bg-brand text-brand-foreground hover:bg-brand-strong"
                    >
                        <Link href="/store">Continue shopping</Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Checkout" />
            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b border-border">
                    <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                        <Link
                            href="/store"
                            className="flex h-11 items-center text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            ← Back to shop
                        </Link>
                        <span className="flex items-center gap-2">
                            <span className="text-sm font-semibold">
                                Checkout
                            </span>
                            <ThemeToggle className="size-11 md:size-9" />
                        </span>
                    </div>
                </header>

                <main className="mx-auto grid max-w-5xl gap-10 px-6 py-10 lg:grid-cols-[1.2fr_1fr]">
                    <form onSubmit={startPayment} className="space-y-4">
                        <h1 className="text-xl font-semibold">Your details</h1>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={form.name}
                                    onChange={set('name')}
                                    className="mt-1 h-11"
                                />
                            </div>
                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.email}
                                    onChange={set('email')}
                                    className="mt-1 h-11"
                                />
                            </div>
                        </div>

                        {cart.has_physical && (
                            <>
                                <div>
                                    <Label htmlFor="address">
                                        Delivery address
                                    </Label>
                                    <Input
                                        id="address"
                                        value={form.address}
                                        onChange={set('address')}
                                        className="mt-1 h-11"
                                    />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <Label htmlFor="city">City</Label>
                                        <Input
                                            id="city"
                                            value={form.city}
                                            onChange={set('city')}
                                            className="mt-1 h-11"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="postal_code">
                                            Postal code
                                        </Label>
                                        <Input
                                            id="postal_code"
                                            value={form.postal_code}
                                            onChange={set('postal_code')}
                                            className="mt-1 h-11"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="country">Country</Label>
                                        <Input
                                            id="country"
                                            value={form.country}
                                            onChange={set('country')}
                                            className="mt-1 h-11"
                                        />
                                    </div>
                                </div>
                            </>
                        )}

                        {error && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {error}
                            </p>
                        )}

                        {!paypalOrderId ? (
                            <Button
                                type="submit"
                                disabled={pending}
                                className="h-11 bg-brand text-brand-foreground hover:bg-brand-strong"
                            >
                                {pending
                                    ? 'Preparing…'
                                    : `Continue to payment — $${cart.total}`}
                            </Button>
                        ) : (
                            <div className="rounded-lg border border-border p-4">
                                <p className="text-sm font-medium">
                                    Order {orderNumber}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    You will be charged ${cart.total} USD. Pay
                                    below to confirm.
                                </p>
                                <div id="paypal-buttons" className="mt-3" />
                            </div>
                        )}
                    </form>

                    <aside className="rounded-lg border border-border p-5 lg:sticky lg:top-10 lg:self-start">
                        <h2 className="text-sm font-medium">Order summary</h2>
                        <ul className="mt-4 space-y-3">
                            {cart.items.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex items-center gap-3"
                                >
                                    <img
                                        src={item.image}
                                        alt=""
                                        className="size-12 rounded border border-border object-cover"
                                    />
                                    <div className="flex-1">
                                        <p className="text-sm">{item.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            Qty {item.quantity} · $
                                            {item.unit_price}
                                        </p>
                                    </div>
                                    <p className="text-sm tabular-nums">
                                        ${item.line_total}
                                    </p>
                                </li>
                            ))}
                        </ul>

                        <dl className="mt-5 space-y-1 border-t border-border pt-4 text-sm">
                            <div className="flex justify-between text-muted-foreground">
                                <dt>Subtotal</dt>
                                <dd className="tabular-nums">
                                    ${cart.subtotal}
                                </dd>
                            </div>
                            <div className="flex justify-between text-muted-foreground">
                                <dt>
                                    {cart.has_physical
                                        ? 'Delivery'
                                        : 'Collection'}
                                </dt>
                                <dd className="tabular-nums">
                                    ${cart.shipping}
                                </dd>
                            </div>
                            <div className="flex justify-between font-semibold">
                                <dt>Total</dt>
                                <dd className="tabular-nums">${cart.total}</dd>
                            </div>
                        </dl>
                    </aside>
                </main>
            </div>
        </>
    );
}
