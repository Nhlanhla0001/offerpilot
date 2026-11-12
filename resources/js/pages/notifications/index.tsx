import { Link } from '@inertiajs/react';
import { BadgeCheck, BellRing } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import SellerLayout from '@/layouts/seller-layout';
import Pagination, { type Paginated } from '@/components/pagination';

type Item = {
    id: string;
    title: string;
    body: string;
    href: string | null;
    read: boolean;
    at: string | null;
};

export default function NotificationsIndex({
    notifications,
}: {
    notifications: Paginated<Item>;
}) {
    const items = notifications.data;
    return (
        <SellerLayout
            title="Notifications"
            description="Payments and shop events, newest first."
        >
            {items.length === 0 ? (
                <div className="rounded-lg border border-dashed border-border px-6 py-12 text-center">
                    <BellRing
                        aria-hidden="true"
                        className="mx-auto size-8 text-muted-foreground"
                    />
                    <p className="mt-3 text-sm font-medium">Nothing yet</p>
                    <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                        Captured payments and shop events appear here.
                    </p>
                </div>
            ) : (
                <section className="overflow-hidden rounded-lg border border-border bg-card">
                    <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                        <h2 className="text-sm font-semibold">Recent</h2>
                        <p className="text-xs text-muted-foreground tabular-nums">
                            {notifications.total} total
                        </p>
                    </div>

                    <ul className="divide-y divide-border">
                        {items.map((item) => (
                            <li
                                key={item.id}
                                className="flex items-start gap-3 px-5 py-4 transition-colors hover:bg-surface"
                            >
                                <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md bg-muted">
                                    <BadgeCheck
                                        aria-hidden="true"
                                        className="size-4 text-brand-strong"
                                    />
                                </span>

                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="text-sm font-medium">
                                            {item.title}
                                        </p>
                                        {!item.read && (
                                            <Badge className="border-brand/25 bg-brand/10 text-brand-strong">
                                                New
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        {item.body}
                                    </p>
                                    {item.href && (
                                        <Link
                                            href={item.href}
                                            className="mt-1 inline-flex h-11 items-center text-xs font-medium underline-offset-4 hover:underline"
                                        >
                                            View order
                                        </Link>
                                    )}
                                </div>

                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {item.at}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
            <Pagination pages={notifications} label="Notifications" />
        </SellerLayout>
    );
}
