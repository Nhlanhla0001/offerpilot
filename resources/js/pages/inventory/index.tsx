import { Head, Link, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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

export default function InventoryIndex({
    merchant,
    products,
}: {
    merchant: { name: string };
    products: Item[];
}) {
    const { flash } = usePage().props as { flash?: { success?: string } };

    return (
        <>
            <Head title="Inventory" />

            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b border-border">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                        <div>
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {merchant.name} · Seller
                            </p>
                            <h1 className="text-lg font-semibold">Inventory</h1>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button asChild variant="outline" className="h-11">
                                <a href="/orders">Orders</a>
                            </Button>
                            <Button asChild variant="outline" className="h-11">
                                <a href="/store">View shop</a>
                            </Button>
                            <Button
                                asChild
                                className="h-11 bg-brand text-brand-foreground hover:bg-brand-strong"
                            >
                                <a href="/inventory/create">Add product</a>
                            </Button>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-6 py-8">
                    {flash?.success && (
                        <p
                            role="status"
                            className="mb-6 rounded-md border border-brand/40 bg-brand/5 px-4 py-3 text-sm text-brand-strong"
                        >
                            {flash.success}
                        </p>
                    )}

                    {products.length === 0 ? (
                        <div className="rounded-lg border border-border p-8 text-center">
                            <p className="text-sm font-medium">
                                Nothing in your shop yet
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add your first product or service to start
                                selling.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-sm">
                                <thead className="bg-surface text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Item
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Type
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Listed
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Minimum
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Availability
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Offers
                                        </th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {products.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <img
                                                        src={item.image}
                                                        alt=""
                                                        className="size-10 rounded border border-border object-cover"
                                                    />
                                                    <div>
                                                        <p className="font-medium">
                                                            {item.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {item.sku}
                                                            {item.category
                                                                ? ` · ${item.category}`
                                                                : ''}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 capitalize">
                                                {item.type}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                ${item.list_price}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                ${item.floor_price}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.availability}
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
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/inventory/${item.id}/edit`}
                                                    className="text-sm underline-offset-4 hover:underline"
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
                </main>
            </div>
        </>
    );
}
