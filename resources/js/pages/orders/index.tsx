import { BadgeCheck, ReceiptText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
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
}: {
    merchantName: string;
    orders: Order[];
    summary: {
        paid_count: number;
        revenue: string;
        inventory_count: number;
        low_stock: number;
    };
}) {
    const description =
        summary.paid_count === 0
            ? 'No payments captured yet. An order appears here the moment PayPal confirms it.'
            : `${summary.paid_count} paid ${summary.paid_count === 1 ? 'order' : 'orders'} worth $${summary.revenue}. ${summary.inventory_count} items in your shop${summary.low_stock > 0 ? `, ${summary.low_stock} running low` : ''}.`;

    return (
        <SellerLayout title="Orders" description={description}>
            {orders.length === 0 ? (
                <div className="rounded-lg border border-dashed border-border px-6 py-12 text-center">
                    <ReceiptText
                        aria-hidden="true"
                        className="mx-auto size-8 text-muted-foreground"
                    />
                    <p className="mt-3 text-sm font-medium">No orders yet</p>
                    <p className="mx-auto mt-1 max-w-md text-sm text-muted-foreground">
                        When a buyer pays through PayPal, the verified order
                        lands here with its items, agreed prices and capture
                        reference.
                    </p>
                </div>
            ) : (
                <section className="overflow-hidden rounded-lg border border-border bg-card">
                    <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                        <h2 className="text-sm font-semibold">All orders</h2>
                        <p className="text-xs text-muted-foreground tabular-nums">
                            {orders.length}{' '}
                            {orders.length === 1 ? 'order' : 'orders'}
                        </p>
                    </div>

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
                                {orders.map((order) => (
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
                                            {order.items}
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
                </section>
            )}
        </SellerLayout>
    );
}
