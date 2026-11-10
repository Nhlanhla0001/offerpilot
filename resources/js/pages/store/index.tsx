import { useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { show as storeShow } from '@/routes/store';

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
}: {
    merchant: Merchant | null;
    products: Product[];
    categories: string[];
}) {
    const [category, setCategory] = useState<string>('All');
    const [query, setQuery] = useState('');

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return products.filter((product) => {
            const matchesCategory =
                category === 'All' || product.category === category;
            const matchesQuery =
                needle === '' ||
                [
                    product.name,
                    product.brand,
                    product.category,
                    product.description,
                ]
                    .filter(Boolean)
                    .some((value) => value!.toLowerCase().includes(needle));

            return matchesCategory && matchesQuery;
        });
    }, [products, category, query]);

    return (
        <>
            <Head title={merchant?.name ?? 'Shop'} />

            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b border-border">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                        <div className="flex items-center gap-3">
                            <span
                                className="grid size-10 place-items-center rounded-md bg-brand text-sm font-semibold text-brand-foreground"
                                aria-hidden="true"
                            >
                                {merchant?.initials ?? 'OP'}
                            </span>
                            <div className="leading-tight">
                                <p className="text-sm font-semibold">
                                    {merchant?.name ?? 'OfferPilot'}
                                </p>
                                {merchant?.tagline && (
                                    <p className="hidden text-xs text-muted-foreground sm:block">
                                        {merchant.tagline}
                                    </p>
                                )}
                            </div>
                        </div>

                        <nav className="flex items-center gap-4 text-sm">
                            <Link
                                href="/login"
                                className="flex h-11 items-center text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                            >
                                Sign in
                            </Link>
                        </nav>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-6">
                    <section className="py-12 sm:py-16">
                        <h1 className="max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                            Buy at the listed price, or make an offer.
                        </h1>
                        <p className="mt-3 max-w-xl text-muted-foreground">
                            Every item here is open to a conversation. Ask a
                            question, or tell us what you would pay — the seller
                            answers within seconds.
                        </p>

                        <div className="mt-6 max-w-md">
                            <label htmlFor="shop-search" className="sr-only">
                                Search the shop
                            </label>
                            <Input
                                id="shop-search"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Search by name, brand or category"
                                className="h-11 text-base"
                            />
                        </div>
                    </section>

                    <div className="flex flex-wrap items-center gap-2 pb-6">
                        {['All', ...categories].map((item) => (
                            <button
                                key={item}
                                type="button"
                                onClick={() => setCategory(item)}
                                aria-pressed={category === item}
                                className={
                                    category === item
                                        ? 'h-9 rounded-full bg-primary px-4 text-sm font-medium text-primary-foreground'
                                        : 'h-9 rounded-full border border-border px-4 text-sm text-muted-foreground hover:text-foreground'
                                }
                            >
                                {item}
                            </button>
                        ))}
                    </div>

                    {visible.length === 0 ? (
                        <p className="pb-16 text-sm text-muted-foreground">
                            Nothing matches “{query}”. Try a different search or
                            category.
                        </p>
                    ) : (
                        <ul className="grid gap-x-6 gap-y-10 pb-20 sm:grid-cols-2 lg:grid-cols-3">
                            {visible.map((product) => (
                                <li key={product.sku}>
                                    <Link
                                        href={storeShow(product.sku).url}
                                        className="group block"
                                    >
                                        <div className="bg-surface overflow-hidden rounded-lg border border-border">
                                            <img
                                                src={product.image}
                                                alt={product.name}
                                                className="aspect-square w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                                                loading="lazy"
                                            />
                                        </div>

                                        <div className="mt-3 flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                    {product.category}
                                                </p>
                                                <h2 className="mt-1 text-base font-medium">
                                                    {product.name}
                                                </h2>
                                            </div>
                                            {!product.available && (
                                                <Badge variant="secondary">
                                                    Out of stock
                                                </Badge>
                                            )}
                                        </div>

                                        <p className="mt-2 text-lg font-semibold tabular-nums">
                                            ${product.price}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {product.availability} · Open to
                                            offers
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </main>

                <footer className="border-t border-border">
                    <div className="mx-auto flex max-w-6xl flex-col gap-2 px-6 py-8 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <p>{merchant?.name ?? 'OfferPilot'} · Prices in USD</p>
                        <p>Payments secured by PayPal</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
