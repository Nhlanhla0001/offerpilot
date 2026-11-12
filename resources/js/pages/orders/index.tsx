import { Link } from '@inertiajs/react';
import { BadgeCheck, ReceiptText, SearchX } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import SearchField from '@/components/search-field';
import Pagination, { type Paginated } from '@/components/pagination';
import SellerLayout from '@/layouts/seller-layout';

type Order = {
    order_number: string;
    customer: string | null;
    email: string | null;
    items: number;
    total: string;
    status: string;
    paid: boolean;
    capture_id: string | null;
    placed_at: string | null;
};

export default function OrdersIndex({
    orders,
    summary,
    filters,
}: {
    merchantName: string;
    orders: Paginated<Order>;
    summary: {
        paid_count: number;
        revenue: string;
        inventory_count: number;
        low_stock: number;
    };
    filters: { q: string };
}) {
    const searching = filters.q !== '';
    const orderItems = orders.data;

    const description =
        summary.paid_count === 0
            ? 'Orders appear here when buyers start checkout. Payment status updates after PayPal confirms payment.'
            : `${summary.paid_count} paid ${summary.paid_count === 1 ? 'order' : 'orders'} worth $${summary.revenue}. ${summary.inventory_count} items in your shop${summary.low_stock > 0 ? `, ${summary.low_stock} running low` : ''}.`;

    return (
        <SellerLayout title="Orders" description={description}>
            {orders.total === 0 && !searching ? (
                <div className="rounded-lg border border-dashed border-border px-6 py-12 text-center">
                    <ReceiptText
                        aria-hidden="true"
                        className="mx-auto size-8 text-muted-foreground"
                    />
                    <p className="mt-3 text-sm font-medium">No orders yet</p>
                    <p className="mx-auto mt-1 max-w-md text-sm text-muted-foreground">
                        Orders appear here when buyers start checkout. Paid
                        orders include verified items, agreed prices and a
                        payment reference.
                    </p>
                </div>
            ) : (
                <section className="overflow-hidden rounded-lg border border-border bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                        <div className="flex items-baseline gap-2">
                            <h2 className="text-sm font-semibold">
                                All orders
                            </h2>
                            <p className="text-xs text-muted-foreground tabular-nums">
                                {orders.total}{' '}
                                {orders.total === 1 ? 'order' : 'orders'}
                                {searching ? ' matching' : ''}
                            </p>
                        </div>

                        <SearchField
                            url="/orders"
                            value={filters.q}
                            placeholder="Order, customer, capture"
                            label="Search orders"
                        />
                    </div>

                    {orderItems.length === 0 ? (
                        <div className="px-6 py-12 text-center">
                            <SearchX
                                aria-hidden="true"
                                className="mx-auto size-8 text-muted-foreground"
                            />
                            <p className="mt-3 text-sm font-medium">
                                No orders match “{filters.q}”
                            </p>
                            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                                Search by order number, customer name, email or
                                capture reference.
                            </p>
                            <Button
                                asChild
                                variant="outline"
                                className="mt-5 h-11"
                            >
                                <Link href="/orders">Clear search</Link>
                            </Button>
                        </div>
                    ) : (
                        <div className="w-full overflow-x-auto contain-paint">
                            <table className="w-full text-sm">
                                <thead className="bg-surface text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Order
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Customer
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Items
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Total
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Payment
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Capture
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Placed
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {orderItems.map((order) => (
                                        <tr
                                            key={order.order_number}
                                            className="transition-colors hover:bg-surface"
                                        >
                                            <td className="px-5 py-3 font-medium tabular-nums">
                                                {order.order_number}
                                            </td>
                                            <td className="px-4 py-3">
                                                {order.customer ?? '—'}
                                                {order.email && (
                                                    <span className="mt-0.5 block text-xs text-muted-foreground">
                                                        {order.email}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {order.items > 0
                                                    ? order.items
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                ${order.total}
                                            </td>
                                            <td className="px-4 py-3">
                                                {order.paid ? (
                                                    <Badge className="gap-1 border-brand/25 bg-brand/10 text-brand-strong">
                                                        <BadgeCheck
                                                            aria-hidden="true"
                                                            className="size-3"
                                                        />
                                                        Paid
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="outline"
                                                        className="capitalize"
                                                    >
                                                        {order.status}
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-muted-foreground tabular-nums">
                                                {order.capture_id ?? '—'}
                                            </td>
                                            <td className="px-5 py-3 text-xs text-muted-foreground">
                                                {order.placed_at ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            )}
            <Pagination pages={orders} label="Orders" />
        </SellerLayout>
    );
}
