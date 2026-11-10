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
    return (
        <SellerLayout title="Orders">
            <p className="-mt-3 mb-6 text-sm text-muted-foreground">
                {summary.paid_count === 0
                    ? 'No payments captured yet. An order appears here the moment PayPal confirms it.'
                    : `${summary.paid_count} paid ${summary.paid_count === 1 ? 'order' : 'orders'} worth $${summary.revenue}. ${summary.inventory_count} items in your shop${summary.low_stock > 0 ? `, ${summary.low_stock} running low` : ''}.`}
            </p>

            {orders.length === 0 ? (
                <div className="rounded-lg border border-border p-8">
                    <p className="text-sm font-medium">No orders yet</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        When a buyer pays through PayPal, the verified order
                        lands here with its items, agreed prices and capture
                        reference.
                    </p>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-surface text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Order</th>
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
                                <th className="px-4 py-3 font-medium">
                                    Placed
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.map((order) => (
                                <tr
                                    key={order.order_number}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-3 font-medium tabular-nums">
                                        {order.order_number}
                                    </td>
                                    <td className="px-4 py-3">
                                        {order.customer ?? '—'}
                                        {order.email && (
                                            <span className="block text-xs text-muted-foreground">
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
                                        <Badge
                                            variant={
                                                order.paid
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {order.paid ? 'Paid' : 'Pending'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-muted-foreground tabular-nums">
                                        {order.capture_id ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-muted-foreground">
                                        {order.placed_at ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </SellerLayout>
    );
}
