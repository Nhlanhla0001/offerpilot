import { Link, usePage } from '@inertiajs/react';
import { Lock, PackagePlus, SearchX } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import SearchField from '@/components/search-field';
import Pagination, { type Paginated } from '@/components/pagination';
import SellerLayout from '@/layouts/seller-layout';

type Item = {
    id: number;
    sku: string;
    name: string;
    type: string;
    brand: string | null;
    category: string | null;
    image: string;
    list_price: string;
    floor_price: string;
    stock: number;
    availability: string;
    negotiation: boolean;
};

function availabilityStyle(label: string): string {
    if (label === 'Out of stock' || label === 'Unavailable') {
        return 'bg-destructive/10 text-destructive';
    }

    if (label === '1 in stock') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
    }

    return 'bg-surface text-muted-foreground';
}

export default function InventoryIndex({
    merchant,
    products,
    filters,
}: {
    merchant: { name: string };
    products: Paginated<Item>;
    filters: { q: string };
}) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const searching = filters.q !== '';
    const items = products.data;

    return (
        <SellerLayout
            title="Inventory"
            description={`Everything ${merchant.name} sells. The private minimum only ever appears here.`}
            actions={
                <Button
                    asChild
                    className="h-11 bg-brand text-brand-foreground hover:bg-brand-strong"
                >
                    <Link href="/inventory/create">
                        <PackagePlus
                            aria-hidden="true"
                            className="mr-1.5 size-4"
                        />
                        Add product
                    </Link>
                </Button>
            }
        >
            {flash?.success && (
                <p
                    role="status"
                    className="rounded-lg border border-brand/40 bg-brand/5 px-4 py-3 text-sm text-brand-strong"
                >
                    {flash.success}
                </p>
            )}

            {products.total === 0 && !searching ? (
                <div className="rounded-lg border border-dashed border-border px-6 py-12 text-center">
                    <PackagePlus
                        aria-hidden="true"
                        className="mx-auto size-8 text-muted-foreground"
                    />
                    <p className="mt-3 text-sm font-medium">
                        Nothing in your shop yet
                    </p>
                    <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                        Add your first product or service to start selling.
                    </p>
                    <Button
                        asChild
                        className="mt-5 h-11 bg-brand text-brand-foreground hover:bg-brand-strong"
                    >
                        <Link href="/inventory/create">Add product</Link>
                    </Button>
                </div>
            ) : (
                <section className="overflow-hidden rounded-lg border border-border bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                        <div className="flex items-baseline gap-2">
                            <h2 className="text-sm font-semibold">Catalogue</h2>
                            <p className="text-xs text-muted-foreground tabular-nums">
                                {products.total}{' '}
                                {products.total === 1 ? 'item' : 'items'}
                                {searching ? ' matching' : ''}
                            </p>
                        </div>

                        <SearchField
                            url="/inventory"
                            value={filters.q}
                            placeholder="Search name, SKU, brand"
                            label="Search inventory"
                        />
                    </div>

                    {items.length === 0 ? (
                        <div className="px-6 py-12 text-center">
                            <SearchX
                                aria-hidden="true"
                                className="mx-auto size-8 text-muted-foreground"
                            />
                            <p className="mt-3 text-sm font-medium">
                                No items match “{filters.q}”
                            </p>
                            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                                Try a product name, SKU, brand or category.
                            </p>
                            <Button
                                asChild
                                variant="outline"
                                className="mt-5 h-11"
                            >
                                <Link href="/inventory">Clear search</Link>
                            </Button>
                        </div>
                    ) : (
                        <div className="w-full overflow-x-auto contain-paint">
                            <table className="w-full text-sm">
                                <thead className="bg-surface text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Item
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Listed
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            <span className="inline-flex items-center gap-1">
                                                <Lock
                                                    aria-hidden="true"
                                                    className="size-3"
                                                />
                                                <span title="Private. Buyers and the assistant never see this.">
                                                    Minimum
                                                </span>
                                            </span>
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Availability
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Offers
                                        </th>
                                        <th className="px-5 py-3 text-right font-medium">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {items.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="transition-colors hover:bg-surface"
                                        >
                                            <td className="px-5 py-3">
                                                <div className="flex items-center gap-3">
                                                    <img
                                                        src={item.image}
                                                        alt=""
                                                        className="size-11 shrink-0 rounded-md border border-border object-cover"
                                                    />
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">
                                                            {item.name}
                                                        </p>
                                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                                            {item.sku}
                                                            {item.category
                                                                ? ` · ${item.category}`
                                                                : ''}
                                                            {` · ${item.type === 'service' ? 'Service' : 'Physical'}`}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                ${item.list_price}
                                            </td>
                                            <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                                ${item.floor_price}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${availabilityStyle(item.availability)}`}
                                                >
                                                    {item.availability}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        item.negotiation
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {item.negotiation
                                                        ? 'Open'
                                                        : 'Fixed'}
                                                </Badge>
                                            </td>
                                            <td className="px-5 py-3 text-right">
                                                <Link
                                                    href={`/inventory/${item.id}/edit`}
                                                    className="inline-flex h-11 items-center px-1 text-sm font-medium underline-offset-4 hover:underline"
                                                >
                                                    Edit
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            )}
            <Pagination pages={products} label="Inventory" />
        </SellerLayout>
    );
}
