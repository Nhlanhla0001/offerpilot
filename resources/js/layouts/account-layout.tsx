import { Link, usePage } from '@inertiajs/react';

/**
 * The signed-in account shell: a shop link, the account pages and a way out.
 *
 * Replaces the starter kit's team layout, which OfferPilot does not use.
 */
export default function AccountLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { auth } = usePage().props as {
        auth?: { user?: { name: string } | null };
    };

    return (
        <div className="min-h-screen bg-background text-foreground">
            <header className="border-b border-border">
                <div className="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-6 py-3">
                    <Link href="/store" className="text-sm font-semibold">
                        OfferPilot
                    </Link>

                    <nav
                        className="flex flex-wrap items-center gap-1"
                        aria-label="Account"
                    >
                        <Link
                            href="/inventory"
                            className="flex h-11 items-center rounded-md px-3 text-sm text-muted-foreground hover:text-foreground"
                        >
                            Seller
                        </Link>
                        <Link
                            href="/settings/profile"
                            className="flex h-11 items-center rounded-md px-3 text-sm text-muted-foreground hover:text-foreground"
                        >
                            Account
                        </Link>
                    </nav>

                    <div className="ml-auto flex items-center gap-3 text-sm">
                        <span className="hidden text-muted-foreground sm:inline">
                            {auth?.user?.name}
                        </span>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="flex h-11 items-center text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            Log out
                        </Link>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-5xl px-6 py-8">{children}</main>
        </div>
    );
}
