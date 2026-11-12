import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type FormEvent,
} from 'react';
import { Link } from '@inertiajs/react';
import { BadgeCheck, Send, ShoppingBag, Sparkles, Trash2 } from 'lucide-react';
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
import {
    assistant,
    events as storeEvents,
    index as storeIndex,
    show as storeShow,
} from '@/routes/store';
import type { CartSummary } from '@/components/store/cart-drawer';
import { cartRequest } from '@/components/store/cart-drawer';
import {
    destroy as destroyCartItem,
    update as updateCartItem,
} from '@/routes/cart/items/index';
import { index as checkoutIndex } from '@/routes/checkout/index';

type AssistantProduct = {
    sku: string;
    name: string;
    price?: string;
    image?: string;
    availability?: string;
};

type AssistantProductOption = {
    sku: string;
    name: string;
};

type AssistantMessage = { role: 'buyer' | 'assistant'; text: string };

type AssistantCartActionItem = {
    sku: string;
    quantity: number;
    quote_id: string | null;
    name: string | null;
    price: string | null;
    price_cents: number;
};

type AssistantAction =
    | {
          type: 'confirm_add_to_cart';
          action_id: string;
          items: AssistantCartActionItem[];
          subtotal: string;
          sku?: string;
          quantity?: number;
          name?: string | null;
          price?: string | null;
      }
    | {
          type: 'confirm_quote';
          quote_id: string;
          product_sku: string;
          price: string;
          expires_at: string | null;
      };

type AssistantTurnResponse = {
    reply: string;
    products: AssistantProduct[];
    quote: { id: string; price: string; expiresAt?: string | null } | null;
    actions: AssistantAction[];
    conversation_id: string | null;
    cart?: CartSummary;
};

type CeilingResponse = {
    authorization: {
        status: 'confirmed';
        product_sku: string;
        ceiling_price_cents: number;
    };
};

function AssistantProductCard({ product }: { product: AssistantProduct }) {
    const [imageFailed, setImageFailed] = useState(false);
    const fallbackImage = '/demo/accessories.svg';

    return (
        <Link
            href={storeShow(product.sku).url}
            className="flex min-w-0 items-center gap-3 rounded-lg border border-border p-2 hover:border-foreground/25"
        >
            <img
                src={
                    imageFailed || !product.image
                        ? fallbackImage
                        : product.image
                }
                alt={imageFailed || !product.image ? '' : product.name}
                onError={() => setImageFailed(true)}
                className="size-16 shrink-0 rounded-md bg-white object-contain p-1"
            />
            <span className="min-w-0 flex-1">
                <span className="block text-sm leading-snug font-medium">
                    {product.name}
                </span>
                {product.price && (
                    <span className="mt-1 block text-sm font-semibold tabular-nums">
                        ${product.price}
                    </span>
                )}
                {product.availability && (
                    <span className="mt-0.5 block text-xs text-muted-foreground">
                        {product.availability}
                    </span>
                )}
            </span>
            <span className="sr-only">View product</span>
        </Link>
    );
}

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

async function trackEvent(
    name: string,
    properties: Record<string, string> = {},
): Promise<void> {
    try {
        await fetch(storeEvents.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ name, properties }),
        });
    } catch {
        // Analytics is best-effort; never block the assistant on a tracking call.
    }
}

function centsFromInput(value: string): number {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0;
}

function formatCents(cents: number): string {
    return (cents / 100).toFixed(2);
}

const QUESTION_PROMPTS = [
    'What is in the box?',
    'Is this right for my setup?',
    'How does delivery work?',
];

const OFFER_PROMPTS = [
    'Can you do a better price?',
    'What is the best you can do?',
    'I can pay less than list.',
];

export default function FloatingAssistant({
    merchantName,
    productContext = null,
    assistantProducts = [],
    openRequest = null,
    onOpenRequestHandled,
}: {
    merchantName: string;
    productContext?: AssistantProduct | null;
    assistantProducts?: AssistantProductOption[];
    openRequest?: { intent: 'question' | 'offer' } | null;
    onOpenRequestHandled?: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [isDesktop, setIsDesktop] = useState(true);
    const [intent, setIntent] = useState<'question' | 'offer'>('question');
    const [messages, setMessages] = useState<AssistantMessage[]>([]);
    const [message, setMessage] = useState('');
    const [selectedProduct, setSelectedProduct] = useState(
        productContext ? `product:${productContext.sku}` : 'general',
    );
    const [ceiling, setCeiling] = useState('');
    const [ceilingConfirmed, setCeilingConfirmed] = useState<string | null>(
        null,
    );
    const [conversationId, setConversationId] = useState<string | null>(null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [actions, setActions] = useState<AssistantAction[]>([]);
    const [surfaced, setSurfaced] = useState<AssistantProduct[]>([]);
    const [cart, setCart] = useState<CartSummary | null>(null);
    const [cartPending, setCartPending] = useState(false);
    const [cartError, setCartError] = useState<string | null>(null);

    const transcriptRef = useRef<HTMLDivElement>(null);
    const launcherRef = useRef<HTMLButtonElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const restoreFocusRef = useRef<HTMLElement | null>(null);

    useEffect(() => {
        const query = window.matchMedia('(min-width: 768px)');

        const sync = () => setIsDesktop(query.matches);

        sync();
        query.addEventListener('change', sync);

        return () => query.removeEventListener('change', sync);
    }, []);

    useEffect(() => {
        if (!openRequest) {
            return;
        }

        setIntent(openRequest.intent);
        setOpen(true);
        onOpenRequestHandled?.();
    }, [openRequest, onOpenRequestHandled]);

    useEffect(() => {
        if (!open) {
            return;
        }

        void trackEvent('assistant_open', { intent });
    }, [open, intent]);

    useEffect(() => {
        if (
            open &&
            isDesktop &&
            document.activeElement instanceof HTMLElement
        ) {
            restoreFocusRef.current = document.activeElement;
        }
    }, [open, isDesktop]);

    useEffect(() => {
        if (open) {
            return;
        }

        restoreFocusRef.current?.focus();
        restoreFocusRef.current = null;
    }, [open]);

    useEffect(() => {
        if (!open || !isDesktop) {
            return;
        }

        const id = window.setTimeout(() => inputRef.current?.focus(), 0);

        return () => window.clearTimeout(id);
    }, [open, isDesktop]);

    useEffect(() => {
        if (!open || !isDesktop) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                setOpen(false);

                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const panel = panelRef.current;

            if (!panel) {
                return;
            }

            const focusable = Array.from(
                panel.querySelectorAll<HTMLElement>(
                    'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
                ),
            );

            if (focusable.length === 0) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey) {
                if (
                    document.activeElement === first ||
                    !panel.contains(document.activeElement)
                ) {
                    event.preventDefault();
                    last.focus();
                }
            } else if (
                document.activeElement === last ||
                !panel.contains(document.activeElement)
            ) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [open, isDesktop]);

    useEffect(() => {
        transcriptRef.current?.scrollTo({
            top: transcriptRef.current.scrollHeight,
            behavior: 'smooth',
        });
    }, [messages, actions, pending, open]);

    const run = useCallback(async (task: () => Promise<void>) => {
        setPending(true);
        setError(null);

        try {
            await task();
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Something went wrong. Try again.',
            );
        } finally {
            setPending(false);
        }
    }, []);

    const confirmCeiling = () => {
        const cents = centsFromInput(ceiling);

        if (cents <= 0) {
            setError('Enter a maximum amount first.');

            return;
        }

        if (!productContext) {
            setError('Choose a product before setting a maximum.');

            return;
        }

        void run(async () => {
            const data = await postJson<CeilingResponse>(assistant.url(), {
                confirmation_action: 'confirm_ceiling',
                product_sku: productContext.sku,
                ceiling_price_cents: cents,
            });

            setCeiling('');
            setCeilingConfirmed(
                formatCents(data.authorization.ceiling_price_cents),
            );
            setMessages((current) => [
                ...current,
                {
                    role: 'assistant',
                    text: `Maximum confirmed: you will not be charged above $${formatCents(
                        data.authorization.ceiling_price_cents,
                    )}. Ask me to negotiate the price when you are ready.`,
                },
            ]);
        });
    };

    const confirmCart = (action: AssistantAction) => {
        if (action.type !== 'confirm_add_to_cart') {
            return;
        }

        void run(async () => {
            const data = await postJson<AssistantTurnResponse>(
                assistant.url(),
                { message: 'Yes, add it', action_id: action.action_id },
            );

            setActions((current) => current.filter((item) => item !== action));
            setMessages((current) => [
                ...current,
                {
                    role: 'assistant',
                    text: data.reply || 'Added to your cart.',
                },
            ]);

            if (data.cart) {
                setCart(data.cart);
                window.dispatchEvent(
                    new CustomEvent('cart:updated', { detail: data.cart }),
                );
            }
        });
    };

    const updateCart = async (
        itemId: number,
        quantity?: number,
    ): Promise<void> => {
        setCartPending(true);
        setCartError(null);

        try {
            const updatedCart = await cartRequest(
                quantity === undefined ? 'DELETE' : 'PATCH',
                quantity === undefined
                    ? destroyCartItem.url(itemId)
                    : updateCartItem.url(itemId),
                quantity === undefined ? undefined : { quantity },
            );

            setCart(updatedCart);
            window.dispatchEvent(
                new CustomEvent('cart:updated', { detail: updatedCart }),
            );
        } catch (exception) {
            setCartError(
                exception instanceof Error
                    ? exception.message
                    : 'Could not update your cart. Please try again.',
            );
        } finally {
            setCartPending(false);
        }
    };

    const keepChatting = () => {
        transcriptRef.current?.scrollTo({
            top: transcriptRef.current.scrollHeight,
            behavior: 'smooth',
        });
        inputRef.current?.focus();
    };

    const requestQuoteAdd = (action: AssistantAction) => {
        if (action.type !== 'confirm_quote') {
            return;
        }

        void run(async () => {
            const data = await postJson<AssistantTurnResponse>(
                assistant.url(),
                {
                    confirmation_action: 'confirm_quote_to_cart',
                    product_sku: action.product_sku,
                    quote_id: action.quote_id,
                },
            );

            setConversationId(data.conversation_id);
            setMessages((current) => [
                ...current,
                { role: 'assistant', text: data.reply },
            ]);
            setActions(data.actions ?? []);
            setSurfaced(data.products ?? []);
        });
    };

    const sendMessage = async (text: string, silent = false) => {
        const trimmed = text.trim();
        const productSku =
            productContext?.sku ??
            (selectedProduct.startsWith('product:')
                ? selectedProduct.slice('product:'.length)
                : '');

        if (trimmed === '') {
            return;
        }

        if (!silent) {
            setMessages((current) => [
                ...current,
                { role: 'buyer', text: trimmed },
            ]);
        }
        setMessage('');

        await run(async () => {
            const data = await postJson<AssistantTurnResponse>(
                assistant.url(),
                {
                    message: trimmed,
                    ...(productSku ? { product_sku: productSku } : {}),
                    ...(conversationId
                        ? { conversation_id: conversationId }
                        : {}),
                },
            );

            setConversationId(data.conversation_id);
            setMessages((current) => [
                ...current,
                { role: 'assistant', text: data.reply },
            ]);
            setActions(data.actions ?? []);
            setSurfaced(data.products ?? []);
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        void sendMessage(message);
    };

    const prompts = intent === 'offer' ? OFFER_PROMPTS : QUESTION_PROMPTS;

    const suggested = Array.from(
        new Map(
            surfaced
                .filter((product) => product.sku !== productContext?.sku)
                .map((product) => [product.sku, product]),
        ).values(),
    );

    const launcher = (
        <button
            ref={launcherRef}
            type="button"
            onClick={() => {
                setOpen((current) => !current);
            }}
            aria-expanded={open}
            aria-haspopup="dialog"
            aria-label="Open the AI shop assistant"
            className="fixed right-4 bottom-4 z-50 inline-flex h-13 items-center gap-2 rounded-full bg-brand px-5 text-sm font-semibold text-brand-foreground shadow-lg transition hover:bg-brand-strong focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <Sparkles aria-hidden="true" className="size-4" />
            <span className="hidden sm:inline">Ask AI</span>
            <span className="sm:hidden">AI</span>
        </button>
    );

    const body = (
        <>
            <div className="flex items-start gap-3 border-b border-border px-4 py-3">
                <span className="grid size-9 shrink-0 place-items-center rounded-full bg-brand text-brand-foreground">
                    <Sparkles aria-hidden="true" className="size-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                        AI assistant
                        <span className="rounded-full bg-surface px-2 py-0.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                            Automated
                        </span>
                    </p>
                    <p className="mt-0.5 text-xs leading-snug text-muted-foreground">
                        Answers from {merchantName}&rsquo;s product data. It
                        never sets a price itself.
                    </p>
                </div>
            </div>

            {productContext && (
                <div className="flex items-center gap-3 border-b border-border px-4 py-2.5">
                    {productContext.image && (
                        <img
                            src={productContext.image}
                            alt=""
                            className="size-10 shrink-0 rounded-md border border-border bg-white object-contain p-0.5"
                        />
                    )}
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                            {productContext.name}
                        </p>
                        {productContext.price && (
                            <p className="text-xs text-muted-foreground tabular-nums">
                                Listed at ${productContext.price}
                            </p>
                        )}
                    </div>
                </div>
            )}

            <div
                ref={transcriptRef}
                role="log"
                aria-live="polite"
                aria-label="Conversation with the shop assistant"
                className="flex-1 space-y-3 overflow-y-auto overscroll-contain px-4 py-3"
            >
                {messages.length === 0 && (
                    <div className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            {intent === 'offer'
                                ? 'Tell me what you would like to pay. I will take it to the shop and bring back the answer.'
                                : 'Ask about a product, delivery or payment. I answer from the shop’s own product data.'}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {prompts.map((prompt) => (
                                <button
                                    key={prompt}
                                    type="button"
                                    onClick={() => {
                                        setMessage(prompt);
                                        inputRef.current?.focus();
                                    }}
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
                                ? 'ml-6 rounded-lg bg-surface px-3 py-2 text-sm'
                                : 'mr-6 rounded-lg border border-border px-3 py-2 text-sm'
                        }
                    >
                        <span className="mb-1 block text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                            {entry.role === 'buyer' ? 'You' : 'Shop assistant'}
                        </span>
                        {entry.text}
                    </div>
                ))}

                {cart && (
                    <section
                        aria-label="Your cart"
                        className="space-y-3 rounded-lg border border-border p-3"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <h3 className="text-sm font-semibold">Your cart</h3>
                            <span className="text-xs text-muted-foreground">
                                {cart.count} item{cart.count === 1 ? '' : 's'}
                            </span>
                        </div>

                        {cart.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Your cart is empty. Keep chatting or choose
                                another product.
                            </p>
                        ) : (
                            <>
                                <ul className="space-y-3">
                                    {cart.items.map((item) => (
                                        <li
                                            key={item.id}
                                            className="flex gap-2 border-b border-border pb-3 last:border-0 last:pb-0"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium">
                                                    {item.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground tabular-nums">
                                                    ${item.unit_price}
                                                    {item.negotiated
                                                        ? ' · agreed price'
                                                        : ' each'}
                                                </p>
                                                <div className="mt-2 flex items-center gap-1.5">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        disabled={
                                                            cartPending ||
                                                            item.quantity <= 1
                                                        }
                                                        onClick={() =>
                                                            void updateCart(
                                                                item.id,
                                                                item.quantity -
                                                                    1,
                                                            )
                                                        }
                                                        className="size-9 p-0"
                                                        aria-label={`Decrease ${item.name}`}
                                                    >
                                                        −
                                                    </Button>
                                                    <span
                                                        aria-label={`Quantity ${item.quantity}`}
                                                        className="w-6 text-center text-sm tabular-nums"
                                                    >
                                                        {item.quantity}
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        disabled={
                                                            cartPending ||
                                                            item.quantity >=
                                                                item.max_quantity
                                                        }
                                                        onClick={() =>
                                                            void updateCart(
                                                                item.id,
                                                                item.quantity +
                                                                    1,
                                                            )
                                                        }
                                                        className="size-9 p-0"
                                                        aria-label={`Increase ${item.name}`}
                                                    >
                                                        +
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        disabled={cartPending}
                                                        onClick={() =>
                                                            void updateCart(
                                                                item.id,
                                                            )
                                                        }
                                                        className="ml-auto size-9 p-0 text-muted-foreground"
                                                        aria-label={`Remove ${item.name}`}
                                                    >
                                                        <Trash2
                                                            aria-hidden="true"
                                                            className="size-4"
                                                        />
                                                    </Button>
                                                </div>
                                            </div>
                                            <p className="shrink-0 text-sm font-medium tabular-nums">
                                                ${item.line_total}
                                            </p>
                                        </li>
                                    ))}
                                </ul>

                                <dl className="space-y-1 border-t border-border pt-2 text-sm">
                                    <div className="flex justify-between text-muted-foreground">
                                        <dt>Subtotal</dt>
                                        <dd className="tabular-nums">
                                            ${cart.subtotal}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between text-muted-foreground">
                                        <dt>Delivery</dt>
                                        <dd className="tabular-nums">
                                            ${cart.shipping}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between font-semibold">
                                        <dt>Total</dt>
                                        <dd className="tabular-nums">
                                            ${cart.total}
                                        </dd>
                                    </div>
                                </dl>

                                {cartPending && (
                                    <p
                                        role="status"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Updating your cart…
                                    </p>
                                )}
                                {cartError && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {cartError}
                                    </p>
                                )}

                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={keepChatting}
                                        className="h-11 flex-1"
                                    >
                                        Keep chatting
                                    </Button>
                                    <Button
                                        asChild
                                        className="h-11 flex-1 bg-brand text-brand-foreground hover:bg-brand-strong"
                                    >
                                        <Link href={checkoutIndex.url()}>
                                            Checkout
                                        </Link>
                                    </Button>
                                </div>
                            </>
                        )}
                        {cart.items.length === 0 && cartError && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {cartError}
                            </p>
                        )}
                    </section>
                )}

                {pending && (
                    <p className="text-sm text-muted-foreground">
                        Checking with the shop…
                    </p>
                )}

                {!pending &&
                    actions.map((action, index) => {
                        if (action.type === 'confirm_add_to_cart') {
                            const items = action.items ?? [
                                {
                                    sku: action.sku ?? '',
                                    quantity: action.quantity ?? 1,
                                    quote_id: null,
                                    name: action.name ?? null,
                                    price: action.price ?? null,
                                    price_cents: 0,
                                },
                            ];

                            return (
                                <div
                                    key={action.action_id ?? index}
                                    className="rounded-lg border border-border p-3"
                                >
                                    <p className="text-xs text-muted-foreground">
                                        {items.length > 1
                                            ? 'Add these items to your cart'
                                            : 'Add to your cart'}
                                    </p>
                                    <ul className="mt-2 space-y-1.5">
                                        {items.map((item, itemIndex) => (
                                            <li
                                                key={`${item.sku}-${itemIndex}`}
                                                className="flex items-start justify-between gap-3 text-sm"
                                            >
                                                <span className="min-w-0">
                                                    <span className="font-medium">
                                                        {item.name ??
                                                            productContext?.name ??
                                                            'Shop item'}
                                                    </span>
                                                    <span className="text-muted-foreground">
                                                        {' '}× {item.quantity}
                                                    </span>
                                                </span>
                                                {item.price && (
                                                    <span className="shrink-0 font-medium tabular-nums">
                                                        ${(
                                                            Number(item.price) *
                                                            item.quantity
                                                        ).toFixed(2)}
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Items subtotal ${action.subtotal}. Delivery, if applicable, is shown at checkout.
                                        </p>
                                    <Button
                                        type="button"
                                        onClick={() => confirmCart(action)}
                                        disabled={pending}
                                        className="mt-2 h-9 bg-brand text-brand-foreground hover:bg-brand-strong"
                                    >
                                        <ShoppingBag
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        {items.length > 1
                                            ? 'Confirm all items'
                                            : 'Confirm add to cart'}
                                    </Button>
                                </div>
                            );
                        }

                        return (
                            <div
                                key={action.quote_id ?? index}
                                className="rounded-lg border border-brand/40 bg-brand/5 p-3"
                            >
                                <p className="flex items-center gap-2 text-sm font-semibold text-brand-strong">
                                    <BadgeCheck
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                    Price held for you: ${action.price}
                                </p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {productContext?.name ?? 'This item'} from{' '}
                                    {merchantName}. Confirm before anything is
                                    added to your cart.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => requestQuoteAdd(action)}
                                    disabled={pending}
                                    className="mt-2 h-9"
                                >
                                    Add to cart at this price
                                </Button>
                            </div>
                        );
                    })}

                {suggested.length > 0 && (
                    <div className="space-y-2">
                        <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                            Products from this search
                        </p>
                        <div className="space-y-2">
                            {suggested.map((product) => (
                                <AssistantProductCard
                                    key={product.sku}
                                    product={product}
                                />
                            ))}
                        </div>
                        {suggested.length > 1 && (
                            <Link
                                href={storeIndex.url({
                                    query: {
                                        skus: suggested
                                            .map((product) => product.sku)
                                            .join(','),
                                    },
                                })}
                                className="inline-flex min-h-11 items-center text-sm font-medium text-brand-strong underline-offset-4 hover:underline"
                            >
                                Browse all matches
                            </Link>
                        )}
                    </div>
                )}
            </div>

            <form
                onSubmit={submit}
                className="space-y-2.5 border-t border-border px-4 py-3"
            >
                {!productContext && assistantProducts.length > 0 && (
                    <div className="space-y-1.5">
                        <Label htmlFor="assistant-product" className="text-xs">
                            Product for your question
                        </Label>
                        <select
                            id="assistant-product"
                            value={selectedProduct}
                            onChange={(event) =>
                                setSelectedProduct(event.target.value)
                            }
                            className="h-10 w-full rounded-md border border-input bg-background px-3 text-base text-foreground sm:text-sm"
                        >
                            <option value="" disabled>
                                Choose a product or general question
                            </option>
                            <option value="general">
                                General shop question
                            </option>
                            {assistantProducts.map((product) => (
                                <option
                                    key={product.sku}
                                    value={`product:${product.sku}`}
                                >
                                    {product.name}
                                </option>
                            ))}
                        </select>
                    </div>
                )}
                {productContext && (
                    <div className="space-y-1.5">
                        <Label htmlFor="assistant-ceiling" className="text-xs">
                            Most you will pay (optional)
                        </Label>
                        <div className="flex gap-2">
                            <Input
                                id="assistant-ceiling"
                                value={ceiling}
                                onChange={(event) =>
                                    setCeiling(event.target.value)
                                }
                                inputMode="decimal"
                                placeholder="Never go above this"
                                className="h-10 text-base"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={confirmCeiling}
                                disabled={pending}
                                className="h-10 shrink-0"
                            >
                                Confirm maximum
                            </Button>
                        </div>
                        {ceilingConfirmed && (
                            <p className="text-xs text-brand-strong tabular-nums">
                                Maximum confirmed: ${ceilingConfirmed}
                            </p>
                        )}
                    </div>
                )}

                <div className="flex gap-2">
                    <Input
                        ref={inputRef}
                        value={message}
                        onChange={(event) => setMessage(event.target.value)}
                        placeholder={
                            intent === 'offer'
                                ? 'Name a price, or ask a question'
                                : 'Ask the shop'
                        }
                        aria-label="Message the shop assistant"
                        className="h-10 text-base"
                    />
                    <Button
                        type="submit"
                        disabled={
                            pending ||
                            message.trim() === ''
                        }
                        className="h-10 shrink-0 bg-brand text-brand-foreground hover:bg-brand-strong"
                    >
                        <Send aria-hidden="true" className="size-4" />
                        Send
                    </Button>
                </div>

                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
            </form>
        </>
    );

    if (isDesktop) {
        return (
            <>
                {launcher}
                {open && (
                    <div
                        ref={panelRef}
                        role="dialog"
                        aria-modal="true"
                        aria-label="AI shop assistant"
                        className="fixed right-4 bottom-20 z-50 flex max-h-[min(72vh,40rem)] w-[24rem] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-xl border border-border bg-background shadow-xl"
                    >
                        {body}
                    </div>
                )}
            </>
        );
    }

    return (
        <>
            {launcher}
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent
                    side="bottom"
                    className="flex h-[85dvh] w-full flex-col gap-0 p-0"
                >
                    <SheetHeader className="border-b border-border px-4 py-3 text-left">
                        <SheetTitle className="text-base">
                            AI assistant
                        </SheetTitle>
                        <SheetDescription className="text-xs">
                            Automated shop assistant for {merchantName}. It
                            answers from the shop&rsquo;s product data and never
                            sets a price itself.
                        </SheetDescription>
                    </SheetHeader>
                    {body}
                </SheetContent>
            </Sheet>
        </>
    );
}
