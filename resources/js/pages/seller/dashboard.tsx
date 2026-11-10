import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import SellerLayout from '@/layouts/seller-layout';

type Recent = {
    order_number: string;
    customer: string | null;
    items: number;
    total: string;
    paid: boolean;
    placed_at: string | null;
};

export default function SellerDashboard({
    stats,
    recent,
}: {
    merchantName: string;
    stats: {
        paid_orders: number;
        revenue: string;
        offers: number;
        accepted: number;
        items: number;
        low_stock: number;
        pending: number;
    };
    recent: Recent[];
}) {
    return (
        <SellerLayout title="Dashboard">
            <div className="-mt-3 space-y-8">
                <p className="text-sm text-muted-foreground">
                    {stats.paid_orders === 0
                        ? 'No verified sales yet. Revenue appears here only after PayPal confirms a capture.'
                        : `${stats.paid_orders} verified ${stats.paid_orders === 1 ? 'sale' : 'sales'} worth $${stats.revenue}.`}
                </p>

                <dl className="grid gap-x-8 gap-y-4 border-y border-border py-5 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        { label: 'Revenue', value: `$${stats.revenue}` },
                        {
                            label: 'Offers received',
                            value: String(stats.offers),
                        },
                        {
                            label: 'Offers accepted',
                            value: String(stats.accepted),
                        },
                        {
                            label: 'Awaiting payment',
                            value: String(stats.pending),
                        },
                    ].map((item) => (
                        <div key={item.label}>
                            <dt className="text-xs tracking-wide text-muted-foreground uppercase">
                                {item.label}
                            </dt>
                            <dd className="mt-1 text-lg font-semibold tabular-nums">
                                {item.value}
                            </dd>
                        </div>
                    ))}
                </dl>

                <section>
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-medium">Recent orders</h2>
                        <Link
                            href="/orders"
                            className="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            All orders
                        </Link>
                    </div>

                    {recent.length === 0 ? (
                        <p className="mt-3 rounded-lg border border-border p-6 text-sm text-muted-foreground">
                            Nothing sold yet. Share your shop and the first
                            verified payment will appear here.
                        </p>
                    ) : (
                        <ul className="mt-3 divide-y divide-border rounded-lg border border-border">
                            {recent.map((order) => (
                                <li
                                    key={order.order_number}
                                    className="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                                >
                                    <div>
                                        <p className="font-medium tabular-nums">
                                            {order.order_number}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {order.customer ?? 'Guest'} ·{' '}
                                            {order.items}{' '}
                                            {order.items === 1
                                                ? 'item'
                                                : 'items'}{' '}
                                            · {order.placed_at ?? ''}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="tabular-nums">
                                            ${order.total}
                                        </span>
                                        <Badge
                                            variant={
                                                order.paid
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {order.paid ? 'Paid' : 'Pending'}
                                        </Badge>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-lg border border-border p-5">
                    <h2 className="text-sm font-medium">Needs attention</h2>
                    <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                        <li>
                            {stats.pending === 0
                                ? 'No orders waiting on payment.'
                                : `${stats.pending} ${stats.pending === 1 ? 'order is' : 'orders are'} waiting on payment.`}
                        </li>
                        <li>
                            {stats.low_stock === 0
                                ? 'Nothing is running low.'
                                : `${stats.low_stock} ${stats.low_stock === 1 ? 'item is' : 'items are'} at one unit or fewer.`}
                        </li>
                        <li>{stats.items} items in your shop.</li>
                    </ul>
                </section>
            </div>
        </SellerLayout>
    );
}
