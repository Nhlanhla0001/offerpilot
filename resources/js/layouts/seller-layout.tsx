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
    const { auth, merchantName } = usePage().props as {
        auth?: { user?: { name: string } | null };
        merchantName?: string | null;
    };

    const path = typeof window === 'undefined' ? '' : window.location.pathname;

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
    ];

    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title={title} />

            <header className="border-b border-border">
                <div className="mx-auto flex max-w-6xl items-center gap-6 px-6 py-3">
                    <Link href="/inventory" className="text-sm font-semibold">
                        OfferPilot
                    </Link>

                    <nav
                        className="flex items-center gap-1"
                        aria-label="Seller"
                    >
                        {nav.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={item.current ? 'page' : undefined}
                                className={
                                    item.current
                                        ? 'bg-surface flex h-11 items-center rounded-md px-3 text-sm font-medium'
                                        : 'flex h-11 items-center rounded-md px-3 text-sm text-muted-foreground hover:text-foreground'
                                }
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-4 text-sm">
                        <Link
                            href="/store"
                            className="flex h-11 items-center text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            View shop
                        </Link>
                        <span className="hidden text-muted-foreground sm:inline">
                            {merchantName ?? auth?.user?.name}
                        </span>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-6 py-8">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    {actions}
                </div>

                {children}
            </main>
        </div>
    );
}
