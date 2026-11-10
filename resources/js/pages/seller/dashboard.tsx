import { Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    BadgeCheck,
    CircleDollarSign,
    Clock,
    MessagesSquare,
    Package,
    PackageX,
    ThumbsUp,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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

type Stat = {
    label: string;
    value: string;
    hint: string;
    icon: LucideIcon;
    href?: string;
    accent?: boolean;
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
    const summary =
        stats.paid_orders === 0
            ? 'No verified sales yet. Revenue appears here only after PayPal confirms a capture.'
            : `${stats.paid_orders} verified ${stats.paid_orders === 1 ? 'sale' : 'sales'} worth $${stats.revenue}.`;

    const tiles: Stat[] = [
        {
            label: 'Revenue',
            value: `$${stats.revenue}`,
            hint:
                stats.paid_orders === 0
                    ? 'Awaiting first capture'
                    : `${stats.paid_orders} verified ${stats.paid_orders === 1 ? 'payment' : 'payments'}`,
            icon: CircleDollarSign,
            href: '/orders',
            accent: true,
        },
        {
            label: 'Offers received',
            value: String(stats.offers),
            hint: 'Negotiations started by buyers',
            icon: MessagesSquare,
        },
        {
            label: 'Offers accepted',
            value: String(stats.accepted),
            hint: 'Paid after agreeing a price',
            icon: ThumbsUp,
        },
        {
            label: 'Awaiting payment',
            value: String(stats.pending),
            hint:
                stats.pending === 0
                    ? 'Nothing outstanding'
                    : 'Orders without a capture',
            icon: Clock,
            href: '/orders',
        },
    ];

    return (
        <SellerLayout title="Dashboard" description={summary}>
            <section
                aria-label="Shop performance"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                {tiles.map((tile) => {
                    const body = (
                        <>
                            <div className="flex items-start justify-between gap-3">
                                <span
                                    className={
                                        tile.accent
                                            ? 'flex size-10 items-center justify-center rounded-md bg-brand/10 text-brand-strong'
                                            : 'flex size-10 items-center justify-center rounded-md bg-muted text-foreground'
                                    }
                                >
                                    <tile.icon
                                        aria-hidden="true"
                                        className="size-5"
                                    />
                                </span>
                                {tile.href && (
                                    <span className="flex items-center gap-0.5 text-xs font-medium text-muted-foreground transition-colors group-hover:text-foreground">
                                        Open
                                        <ArrowUpRight
                                            aria-hidden="true"
                                            className="size-3"
                                        />
                                    </span>
                                )}
                            </div>
                            <p className="mt-4 text-2xl font-semibold tracking-tight tabular-nums">
                                {tile.value}
                            </p>
                            <p className="mt-0.5 text-sm font-medium">
                                {tile.label}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {tile.hint}
                            </p>
                        </>
                    );

                    const shell =
                        'rounded-lg border border-border bg-card p-5 transition-colors';

                    return tile.href ? (
                        <Link
                            key={tile.label}
                            href={tile.href}
                            className={`group ${shell} hover:border-foreground/25`}
                        >
                            {body}
                        </Link>
                    ) : (
                        <div key={tile.label} className={shell}>
                            {body}
                        </div>
                    );
                })}
            </section>

            <div className="grid items-start gap-6 lg:grid-cols-3">
                <section className="rounded-lg border border-border bg-card lg:col-span-2">
                    <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                        <h2 className="text-sm font-semibold">Recent orders</h2>
                        <Link
                            href="/orders"
                            className="-my-3 inline-flex h-11 items-center text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            All orders
                        </Link>
                    </div>

                    {recent.length === 0 ? (
                        <div className="px-5 py-10 text-center">
                            <Package
                                aria-hidden="true"
                                className="mx-auto size-8 text-muted-foreground"
                            />
                            <p className="mt-3 text-sm font-medium">
                                Nothing sold yet
                            </p>
                            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                                Share your shop link. The first verified payment
                                will appear here.
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-border">
                            {recent.map((order) => (
                                <li
                                    key={order.order_number}
                                    className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-surface"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium break-all tabular-nums">
                                            {order.order_number}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {order.customer ?? 'Guest'} ·{' '}
                                            {order.items}{' '}
                                            {order.items === 1
                                                ? 'item'
                                                : 'items'}
                                            {order.placed_at
                                                ? ` · ${order.placed_at}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-3">
                                        <span className="text-sm font-medium tabular-nums">
                                            ${order.total}
                                        </span>
                                        {order.paid ? (
                                            <Badge className="gap-1 border-brand/25 bg-brand/10 text-brand-strong">
                                                <BadgeCheck
                                                    aria-hidden="true"
                                                    className="size-3"
                                                />
                                                Paid
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                Pending
                                            </Badge>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-lg border border-border bg-card">
                    <div className="border-b border-border px-5 py-4">
                        <h2 className="text-sm font-semibold">
                            Needs attention
                        </h2>
                    </div>
                    <ul className="divide-y divide-border">
                        <li className="flex items-start gap-3 px-5 py-3.5">
                            <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md bg-muted">
                                <Clock
                                    aria-hidden="true"
                                    className="size-4 text-muted-foreground"
                                />
                            </span>
                            <div>
                                <p className="text-sm font-medium">
                                    {stats.pending === 0
                                        ? 'No orders waiting on payment'
                                        : `${stats.pending} ${stats.pending === 1 ? 'order is' : 'orders are'} waiting on payment`}
                                </p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Uncaptured orders never count as revenue.
                                </p>
                            </div>
                        </li>
                        <li className="flex items-start gap-3 px-5 py-3.5">
                            <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md bg-muted">
                                <PackageX
                                    aria-hidden="true"
                                    className="size-4 text-muted-foreground"
                                />
                            </span>
                            <div>
                                <p className="text-sm font-medium">
                                    {stats.low_stock === 0
                                        ? 'Nothing is running low'
                                        : `${stats.low_stock} ${stats.low_stock === 1 ? 'item is' : 'items are'} at one unit or fewer`}
                                </p>
                                <Link
                                    href="/inventory"
                                    className="-my-3 inline-flex h-11 items-center text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                >
                                    Review inventory
                                </Link>
                            </div>
                        </li>
                        <li className="flex items-start gap-3 px-5 py-3.5">
                            <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md bg-muted">
                                <Package
                                    aria-hidden="true"
                                    className="size-4 text-muted-foreground"
                                />
                            </span>
                            <div>
                                <p className="text-sm font-medium">
                                    {stats.items}{' '}
                                    {stats.items === 1 ? 'item' : 'items'} in
                                    your shop
                                </p>
                                <Link
                                    href="/inventory"
                                    className="-my-3 inline-flex h-11 items-center text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                >
                                    Manage products
                                </Link>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </SellerLayout>
    );
}
