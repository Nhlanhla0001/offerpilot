import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

export type CartSummary = {
    items: {
        id: number;
        sku: string;
        name: string;
        image: string;
        quantity: number;
        unit_price: string;
        line_total: string;
        negotiated: boolean;
        max_quantity: number;
    }[];
    count: number;
    subtotal: string;
    shipping: string;
    total: string;
    currency: string;
    has_physical: boolean;
};

function xsrf(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function cartRequest(
    method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
    url: string,
    body?: unknown,
): Promise<CartSummary> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrf(),
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(
            (data as { message?: string }).message ??
                'Could not update your cart.',
        );
    }

    return data as CartSummary;
}

export default function CartDrawer() {
    const [cart, setCart] = useState<CartSummary | null>(null);
    const [open, setOpen] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const refresh = useCallback(async () => {
        try {
            setCart(await cartRequest('GET', '/cart'));
        } catch {
            // A missing cart is a valid state for a brand new visitor.
        }
    }, []);

    useEffect(() => {
        void refresh();
    }, [refresh]);

    useEffect(() => {
        const onCartUpdated = () => void refresh();

        window.addEventListener('cart:updated', onCartUpdated);

        return () => window.removeEventListener('cart:updated', onCartUpdated);
    }, [refresh]);

    const act = async (
        method: 'PATCH' | 'DELETE',
        url: string,
        body?: unknown,
    ) => {
        setError(null);

        try {
            setCart(await cartRequest(method, url, body));
        } catch (exception) {
            setError(
                exception instanceof Error ? exception.message : 'Failed.',
            );
        }
    };

    const count = cart?.count ?? 0;

    return (
        <>
            <button
                type="button"
                onClick={() => {
                    void refresh();
                    setOpen(true);
                }}
                className="relative flex h-11 items-center gap-2 rounded-md border border-border px-3 text-sm hover:text-foreground"
                aria-label={`Cart, ${count} item${count === 1 ? '' : 's'}`}
            >
                Cart
                <span className="grid min-w-5 place-items-center rounded-full bg-brand px-1.5 text-xs font-semibold text-brand-foreground tabular-nums">
                    {count}
                </span>
            </button>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-md">
                    <SheetHeader className="border-b border-border px-5 py-4">
                        <SheetTitle className="text-base">Your cart</SheetTitle>
                        <SheetDescription className="text-xs">
                            Prices and stock are checked again on the server.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 overflow-y-auto px-5 py-4">
                        {count === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Your cart is empty. Everything here is open to
                                an offer.
                            </p>
                        ) : (
                            <ul className="space-y-4">
                                {cart?.items.map((item) => (
                                    <li key={item.id} className="flex gap-3">
                                        <img
                                            src={item.image}
                                            alt={item.name}
                                            className="size-16 shrink-0 rounded-md border border-border object-cover"
                                        />
                                        <div className="flex-1">
                                            <p className="text-sm font-medium">
                                                {item.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                ${item.unit_price}
                                                {item.negotiated &&
                                                    ' · agreed price'}
                                            </p>

                                            <div className="mt-2 flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        void act(
                                                            'PATCH',
                                                            `/cart/items/${item.id}`,
                                                            {
                                                                quantity:
                                                                    Math.max(
                                                                        1,
                                                                        item.quantity -
                                                                            1,
                                                                    ),
                                                            },
                                                        )
                                                    }
                                                    className="size-8 rounded-md border border-border"
                                                    aria-label={`Decrease ${item.name}`}
                                                >
                                                    −
                                                </button>
                                                <span className="w-6 text-center text-sm tabular-nums">
                                                    {item.quantity}
                                                </span>
                                                <button
                                                    type="button"
                                                    disabled={
                                                        item.quantity >=
                                                        item.max_quantity
                                                    }
                                                    onClick={() =>
                                                        void act(
                                                            'PATCH',
                                                            `/cart/items/${item.id}`,
                                                            {
                                                                quantity:
                                                                    item.quantity +
                                                                    1,
                                                            },
                                                        )
                                                    }
                                                    className="size-8 rounded-md border border-border disabled:opacity-40"
                                                    aria-label={`Increase ${item.name}`}
                                                >
                                                    +
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        void act(
                                                            'DELETE',
                                                            `/cart/items/${item.id}`,
                                                        )
                                                    }
                                                    className="ml-auto text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                        <p className="text-sm font-medium tabular-nums">
                                            ${item.line_total}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {error && (
                            <p
                                role="alert"
                                className="mt-4 text-sm text-destructive"
                            >
                                {error}
                            </p>
                        )}
                    </div>

                    {count > 0 && (
                        <div className="space-y-3 border-t border-border px-5 py-4">
                            <dl className="space-y-1 text-sm">
                                <div className="flex justify-between text-muted-foreground">
                                    <dt>Subtotal</dt>
                                    <dd className="tabular-nums">
                                        ${cart?.subtotal}
                                    </dd>
                                </div>
                                <div className="flex justify-between text-muted-foreground">
                                    <dt>Delivery</dt>
                                    <dd className="tabular-nums">
                                        ${cart?.shipping}
                                    </dd>
                                </div>
                                <div className="flex justify-between font-semibold">
                                    <dt>Total</dt>
                                    <dd className="tabular-nums">
                                        ${cart?.total}
                                    </dd>
                                </div>
                            </dl>

                            <Button
                                asChild
                                className="h-11 w-full bg-brand text-brand-foreground hover:bg-brand-strong"
                            >
                                <a href="/checkout">Checkout</a>
                            </Button>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}
