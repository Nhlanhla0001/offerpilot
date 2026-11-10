import { Head, Link, usePage } from '@inertiajs/react';

type NavItem = { href: string; label: string; current: boolean };

export default function SellerLayout({
    title,
    children,
    actions,
}: {
    title: string;
    children: React.ReactNode;
    actions?: React.ReactNode;
}) {
    const page = usePage();
    const { auth, merchantName } = page.props as {
        auth?: { user?: { name: string } | null };
        merchantName?: string | null;
    };

    const path = page.url.split('?')[0];

    const nav: NavItem[] = [
        {
            href: '/dashboard',
            label: 'Dashboard',
            current: path === '/dashboard',
        },
        {
            href: '/inventory',
            label: 'Inventory',
            current: path.startsWith('/inventory'),
        },
        {
            href: '/orders',
            label: 'Orders',
            current: path.startsWith('/orders'),
        },
        {
            href: '/notifications',
            label: 'Notifications',
            current: path.startsWith('/notifications'),
        },
    ];

    const linkClass = (item: NavItem) =>
        item.current
            ? 'flex h-11 items-center rounded-md bg-surface px-3 text-sm font-medium text-foreground'
            : 'flex h-11 items-center rounded-md px-3 text-sm text-muted-foreground hover:bg-surface hover:text-foreground';

    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title={title} />

            <div className="flex min-h-screen">
                <aside className="hidden w-60 shrink-0 flex-col border-r border-border px-4 py-4 md:flex">
                    <Link
                        href="/dashboard"
                        className="px-3 pb-6 text-sm font-semibold"
                    >
                        OfferPilot
                    </Link>

                    <nav className="flex flex-col gap-1" aria-label="Seller">
                        {nav.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={item.current ? 'page' : undefined}
                                className={linkClass(item)}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="mt-auto space-y-1 border-t border-border pt-3">
                        <p className="truncate px-3 text-xs text-muted-foreground">
                            {merchantName ?? auth?.user?.name}
                        </p>
                        <Link
                            href="/store"
                            className="hover:bg-surface flex h-11 items-center rounded-md px-3 text-sm text-muted-foreground hover:text-foreground"
                        >
                            View shop
                        </Link>
                    </div>
                </aside>

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="border-b border-border md:hidden">
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2">
                            <Link
                                href="/dashboard"
                                className="text-sm font-semibold"
                            >
                                OfferPilot
                            </Link>
                            <nav
                                className="flex flex-wrap items-center gap-1"
                                aria-label="Seller"
                            >
                                {nav.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        aria-current={
                                            item.current ? 'page' : undefined
                                        }
                                        className={
                                            item.current
                                                ? 'bg-surface flex h-11 items-center rounded-md px-2 text-xs font-medium'
                                                : 'flex h-11 items-center rounded-md px-2 text-xs text-muted-foreground'
                                        }
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </nav>
                        </div>
                    </header>

                    <main className="w-full min-w-0 px-4 py-6 sm:px-6">
                        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                            <h1 className="text-xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            {actions}
                        </div>

                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}
