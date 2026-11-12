import { useState, type FormEvent } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search, ShieldCheck, Sparkles, Truck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import CartDrawer from '@/components/store/cart-drawer';
import FloatingAssistant from '@/components/store/floating-assistant';
import Pagination, { type Paginated } from '@/components/pagination';
import ThemeToggle from '@/components/theme-toggle';
import { index as storeIndex, show as storeShow } from '@/routes/store';

type Product = {
    sku: string;
    name: string;
    brand: string | null;
    category: string | null;
    type: string;
    description: string | null;
    price: string;
    image: string;
    available: boolean;
    availability: string;
    merchant: string;
};

type Merchant = {
    name: string;
    tagline: string | null;
    initials: string;
};

export default function StoreIndex({
    merchant,
    products,
    categories,
    filters,
    assistant,
}: {
    merchant: Merchant | null;
    products: Paginated<Product>;
    categories: string[];
    filters: {
        q: string;
        category: string;
        isAssistantResultFilter: boolean;
    };
    assistant: {
        merchantName: string;
        productSku: string | null;
        products: { sku: string; name: string }[];
    };
}) {
    const { auth, role } = usePage().props as {
        auth?: { user?: { name: string | null } | null };
        role?: string;
    };
    const [category, setCategory] = useState<string>(filters.category || 'All');
    const [query, setQuery] = useState(filters.q);

    const visible = products.data;
    const applyFilters = (nextQuery: string, nextCategory: string) => {
        router.get(
            '/store',
            {
                ...(nextQuery.trim() ? { q: nextQuery.trim() } : {}),
                ...(nextCategory !== 'All' ? { category: nextCategory } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };
    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        applyFilters(query, category);
    };

    const featured =
        visible.find((product) => product.sku === 'HEADPHONES-PRO') ??
        visible[0];

    return (
        <>
            <Head title={merchant?.name ?? 'Shop'} />

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
                    <div className="mx-auto grid max-w-6xl grid-cols-[1fr_auto] items-center gap-x-6 gap-y-3 px-6 py-4 sm:grid-cols-[auto_1fr_auto]">
                        <Link
                            href="/store"
                            className="flex min-w-0 items-center gap-3"
                            aria-label={`${merchant?.name ?? 'Shop'} home`}
                        >
                            <span
                                className="grid size-10 place-items-center rounded-md bg-brand text-sm font-semibold text-brand-foreground"
                                aria-hidden="true"
                            >
                                {merchant?.initials ?? 'OP'}
                            </span>
                            <span className="min-w-0 leading-tight">
                                <span className="block truncate text-base font-semibold">
                                    {merchant?.name ?? 'OfferPilot'}
                                </span>
                                {merchant?.tagline && (
                                    <span className="hidden text-xs text-muted-foreground sm:block">
                                        {merchant.tagline}
                                    </span>
                                )}
                            </span>
                        </Link>

                        <form
                            onSubmit={submitSearch}
                            className="order-last col-span-2 w-full min-w-0 sm:order-none sm:col-span-1"
                        >
                            <label htmlFor="shop-search" className="sr-only">
                                Search the shop
                            </label>
                            <div className="relative">
                                <Search
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="shop-search"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Search products, brands or categories"
                                    className="h-11 bg-surface pr-12 pl-9 text-base sm:text-sm"
                                />
                                <button
                                    type="submit"
                                    aria-label="Search products"
                                    className="absolute top-1/2 right-1 flex size-9 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground hover:bg-background hover:text-foreground"
                                >
                                    <span className="sr-only">Search</span>↵
                                </button>
                            </div>
                        </form>

                        <nav className="flex items-center justify-end gap-1 sm:gap-2">
                            <ThemeToggle className="hidden size-11 sm:inline-flex md:size-9" />
                            <CartDrawer />
                            {role === 'seller' ? (
                                <Link
                                    href="/dashboard"
                                    className="hidden h-11 items-center rounded-md px-2 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline sm:flex"
                                >
                                    <span className="hidden sm:inline">
                                        Seller workspace
                                    </span>
                                    <span className="sm:hidden">Seller</span>
                                </Link>
                            ) : (
                                <Link
                                    href="/login"
                                    className="hidden h-11 items-center rounded-md px-2 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline sm:flex"
                                >
                                    {auth?.user ? 'My account' : 'Sign in'}
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-6">
                    <section className="grid items-center gap-8 border-b border-border py-9 sm:py-12 lg:grid-cols-[1.05fr_0.95fr] lg:gap-14">
                        <div>
                            <p className="text-xs font-medium tracking-wide text-brand-strong uppercase">
                                {merchant?.name ?? 'OfferPilot'} · Audio and
                                desk equipment
                            </p>
                            <h1 className="mt-3 max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                                Good gear, and room to talk about the price.
                            </h1>
                            <p className="mt-3 max-w-xl text-sm leading-relaxed text-muted-foreground sm:text-base">
                                Buy at the listed price, or make an offer and
                                let the shop answer. Every item ships from stock
                                held by the seller.
                            </p>
                            <p className="mt-4 text-xs text-muted-foreground tabular-nums">
                                {products.total} products · prices in USD
                            </p>
                        </div>

                        {featured && (
                            <Link
                                href={storeShow(featured.sku).url}
                                className="group grid grid-cols-[7rem_1fr] items-center gap-4 rounded-lg border border-border p-3 transition-colors hover:border-foreground/25 sm:grid-cols-[10rem_1fr] sm:p-4"
                            >
                                <div className="overflow-hidden rounded-md bg-white">
                                    <img
                                        src={featured.image}
                                        alt={featured.name}
                                        className="aspect-square w-full object-contain p-2"
                                    />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-[11px] font-medium tracking-wide text-brand-strong uppercase">
                                        Shop favourite
                                    </p>
                                    <p className="mt-1 text-sm font-medium group-hover:underline group-hover:underline-offset-4 sm:text-base">
                                        {featured.name}
                                    </p>
                                    <p className="mt-1 text-lg font-semibold tabular-nums">
                                        ${featured.price}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Open to offers
                                    </p>
                                </div>
                            </Link>
                        )}
                    </section>

                    <div className="sticky top-0 z-20 -mx-6 border-b border-border bg-background/95 px-6 backdrop-blur">
                        <div className="flex gap-2 overflow-x-auto py-3">
                            {['All', ...categories].map((item) => (
                                <button
                                    key={item}
                                    type="button"
                                    onClick={() => {
                                        setCategory(item);
                                        applyFilters(query, item);
                                    }}
                                    aria-pressed={category === item}
                                    className={
                                        category === item
                                            ? 'h-11 shrink-0 rounded-full bg-primary px-4 text-sm font-medium text-primary-foreground'
                                            : 'h-11 shrink-0 rounded-full border border-border px-4 text-sm text-muted-foreground hover:border-foreground/30 hover:text-foreground'
                                    }
                                >
                                    {item}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-baseline justify-between gap-4 pt-8">
                        <h2 className="text-lg font-semibold">
                            {category === 'All' ? 'All products' : category}
                        </h2>
                        <p className="text-xs text-muted-foreground tabular-nums">
                            {products.total}{' '}
                            {products.total === 1 ? 'item' : 'items'}
                        </p>
                    </div>

                    {visible.length === 0 ? (
                        <div className="my-10 rounded-lg border border-dashed border-border px-6 py-14 text-center">
                            {filters.isAssistantResultFilter ? (
                                <>
                                    <p className="text-sm font-medium">
                                        We couldn’t show those matches.
                                    </p>
                                    <Link
                                        href={storeIndex.url()}
                                        className="mt-3 inline-flex h-11 items-center rounded-md border border-border px-4 text-sm font-medium hover:bg-surface"
                                    >
                                        Browse all products
                                    </Link>
                                </>
                            ) : (
                                <>
                                    <p className="text-sm font-medium">
                                        Nothing matches{' '}
                                        {query ? `“${query}”` : 'this category'}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Try a different search, or clear the
                                        category filter.
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setQuery('');
                                            setCategory('All');
                                            applyFilters('', 'All');
                                        }}
                                        className="mt-4 h-11 rounded-md border border-border px-4 text-sm font-medium hover:bg-surface"
                                    >
                                        Clear filters
                                    </button>
                                </>
                            )}
                        </div>
                    ) : (
                        <ul className="grid grid-cols-2 gap-x-4 gap-y-8 py-6 pb-16 sm:gap-x-6 lg:grid-cols-3">
                            {visible.map((product) => (
                                <li key={product.sku}>
                                    <Link
                                        href={storeShow(product.sku).url}
                                        className="group block h-full"
                                    >
                                        <div className="relative overflow-hidden rounded-lg border border-border bg-white">
                                            <img
                                                src={product.image}
                                                alt={product.name}
                                                className="aspect-[4/3] w-full object-contain p-4 transition-transform duration-300 group-hover:scale-[1.03] sm:p-6"
                                                loading="lazy"
                                            />
                                            {!product.available && (
                                                <Badge
                                                    variant="secondary"
                                                    className="absolute top-3 left-3 bg-background/95"
                                                >
                                                    Out of stock
                                                </Badge>
                                            )}
                                        </div>

                                        <div className="mt-3">
                                            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                                {product.brand ??
                                                    product.category}
                                            </p>
                                            <h3 className="mt-1 text-sm leading-snug font-medium group-hover:underline group-hover:underline-offset-4 sm:text-base">
                                                {product.name}
                                            </h3>
                                            <p className="mt-1.5 text-base font-semibold tabular-nums sm:text-lg">
                                                ${product.price}
                                            </p>
                                            <p className="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                                {product.available ? (
                                                    <>
                                                        <Sparkles
                                                            aria-hidden="true"
                                                            className="size-3 text-brand-strong"
                                                        />
                                                        Open to offers
                                                    </>
                                                ) : (
                                                    product.availability
                                                )}
                                            </p>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Pagination pages={products} label="Products" />
                </main>

                <footer className="border-t border-border">
                    <div className="mx-auto flex max-w-6xl flex-col gap-2 px-6 py-8 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <p>{merchant?.name ?? 'OfferPilot'} · Prices in USD</p>
                        <p>Payments secured by PayPal Sandbox</p>
                    </div>
                </footer>

                <FloatingAssistant
                    merchantName={merchant?.name ?? 'OfferPilot'}
                    assistantProducts={assistant.products}
                />
            </div>
        </>
    );
}
