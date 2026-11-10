import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import SellerLayout from '@/layouts/seller-layout';

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
    notifications: Item[];
}) {
    return (
        <SellerLayout title="Notifications">
            <div className="-mt-3">
                {notifications.length === 0 ? (
                    <div className="rounded-lg border border-border p-8">
                        <p className="text-sm font-medium">Nothing yet</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Captured payments and shop events appear here.
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border">
                        {notifications.map((item) => (
                            <li
                                key={item.id}
                                className="flex items-start justify-between gap-4 px-4 py-3"
                            >
                                <div>
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-medium">
                                            {item.title}
                                        </p>
                                        {!item.read && (
                                            <Badge variant="secondary">
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
                                            className="mt-1 inline-block text-xs underline-offset-4 hover:underline"
                                        >
                                            View
                                        </Link>
                                    )}
                                </div>
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {item.at}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </SellerLayout>
    );
}
