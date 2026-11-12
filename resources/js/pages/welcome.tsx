import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Handshake,
    ShieldCheck,
    ShoppingBag,
    Store,
} from 'lucide-react';
import ThemeToggle from '@/components/theme-toggle';

export default function Welcome() {
    const { auth, role } = usePage().props as {
        auth?: { user?: { name: string | null } | null };
        role?: string;
    };
    const sellerHref = role === 'seller' ? '/dashboard' : '/login';
    const sellerLabel =
        role === 'seller' ? 'Seller workspace' : 'Seller sign in';

    return (
        <>
            <Head title="Turn more conversations into sales while protecting your profit." />

            <div className="min-h-screen bg-background text-foreground">
                <header className="sticky top-0 z-30 border-b border-border bg-background/90 backdrop-blur">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
                        <Link
                            href="/"
                            className="flex items-center gap-2.5"
                            aria-label="OfferPilot home"
                        >
                            <span className="grid size-9 place-items-center rounded-md bg-brand text-[13px] font-semibold text-brand-foreground">
                                OP
                            </span>
                            <span className="text-base font-semibold tracking-tight">
                                OfferPilot
                            </span>
                        </Link>

                        <nav className="flex items-center gap-1 sm:gap-3">
                            <ThemeToggle className="size-11 md:size-9" />
                            <Link
                                href="/store"
                                className="hidden h-11 items-center rounded-md px-3 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline sm:flex"
                            >
                                Demo shop
                            </Link>
                            <Link
                                href={sellerHref}
                                className="flex h-11 items-center gap-1.5 rounded-md bg-primary px-3.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 sm:px-4"
                            >
                                <Store aria-hidden="true" className="size-4" />
                                <span className="hidden sm:inline">
                                    {sellerLabel}
                                </span>
                                <span className="sm:hidden">Seller</span>
                            </Link>
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="mx-auto grid max-w-6xl items-center gap-12 px-6 py-14 sm:py-20 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16">
                        <div>
                            <p className="inline-flex items-center gap-2 rounded-full border border-border px-3 py-1 text-xs font-medium text-muted-foreground">
                                <ShieldCheck
                                    aria-hidden="true"
                                    className="size-3.5 text-brand-strong"
                                />
                                Built for the PayPal AI Hackathon 2026
                            </p>

                            <h1 className="mt-5 text-3xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-[3.4rem] lg:leading-[1.05]">
                                Turn buyer conversations into sales
                                <span className="text-brand-strong">
                                    {' '}
                                    while protecting your profit.
                                </span>
                            </h1>

                            <p className="mt-5 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg">
                                OfferPilot is an online shop with an AI helper
                                for sellers. Shoppers can pay the listed price
                                or make an offer. Shop rules decide whether to
                                accept an offer, suggest a different price, or
                                say no. PayPal confirms every payment before an
                                order is marked as paid.
                            </p>

                            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href="/store"
                                    className="inline-flex h-12 items-center justify-center gap-2 rounded-lg bg-brand px-6 text-base font-medium text-brand-foreground hover:bg-brand-strong"
                                >
                                    <ShoppingBag
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                    Shop the demo store
                                    <ArrowRight
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </Link>
                                <Link
                                    href={sellerHref}
                                    className="inline-flex h-12 items-center justify-center gap-2 rounded-lg border border-border px-6 text-base font-medium hover:bg-surface"
                                >
                                    <Store
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                    {role === 'seller'
                                        ? 'Open your workspace'
                                        : 'Sign in as a shop owner'}
                                </Link>
                            </div>

                            {auth?.user && (
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Signed in as {auth.user.name}. You can shop
                                    or open the seller workspace.
                                </p>
                            )}
                        </div>

                        <div className="relative">
                            <div className="overflow-hidden rounded-lg border border-border bg-surface">
                                <img
                                    src="/demo/headphones.jpg"
                                    alt="Northsound SH-1 studio headphones"
                                    className="aspect-[4/3] w-full object-cover"
                                />
                            </div>

                            <div className="mx-4 -mt-8 rounded-lg border border-border bg-background p-4 shadow-none sm:mx-8">
                                <div className="flex items-center justify-between gap-3">
                                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        A buyer makes an offer
                                    </p>
                                    <BadgeCheck
                                        aria-hidden="true"
                                        className="size-4 text-brand-strong"
                                    />
                                </div>
                                <p className="mt-2 text-sm">
                                    <span className="text-muted-foreground">
                                        Shopper:
                                    </span>{' '}
                                    &ldquo;I can do $190.&rdquo;
                                </p>
                                <p className="mt-1 text-sm">
                                    <span className="text-muted-foreground">
                                        Shop:
                                    </span>{' '}
                                    &ldquo;$225. You have 15 minutes to
                                    decide.&rdquo;
                                </p>
                                <p className="mt-3 border-t border-border pt-2 text-xs text-muted-foreground">
                                    The shop&apos;s lowest price stays private.
                                    Shoppers and the AI helper never see it.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section className="border-y border-border bg-surface/60">
                        <div className="mx-auto grid max-w-6xl gap-px px-6 py-10 sm:grid-cols-3 sm:gap-8">
                            <div>
                                <ShieldCheck
                                    aria-hidden="true"
                                    className="size-5 text-brand-strong"
                                />
                                <h2 className="mt-3 text-sm font-semibold">
                                    Your lowest price stays private
                                </h2>
                                <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                    The shop&apos;s lowest acceptable price is
                                    hidden from shoppers and the AI helper.
                                </p>
                            </div>
                            <div>
                                <Handshake
                                    aria-hidden="true"
                                    className="size-5 text-brand-strong"
                                />
                                <h2 className="mt-3 text-sm font-semibold">
                                    Your shop sets the rules
                                </h2>
                                <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                    Shop rules decide whether to accept an
                                    offer, suggest another price, or say no. The
                                    shop never suggests more than the buyer said
                                    they&apos;d pay.
                                </p>
                            </div>
                            <div>
                                <BadgeCheck
                                    aria-hidden="true"
                                    className="size-5 text-brand-strong"
                                />
                                <h2 className="mt-3 text-sm font-semibold">
                                    Only confirmed payments count
                                </h2>
                                <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                    An order counts as paid only after PayPal
                                    confirms the buyer paid the agreed price in
                                    US dollars.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section className="mx-auto max-w-6xl px-6 py-16">
                        <div className="flex flex-col gap-6 rounded-lg border border-border p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                            <div>
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    The demo shop
                                </p>
                                <h2 className="mt-2 text-2xl font-semibold tracking-tight">
                                    Northsound Audio
                                </h2>
                                <p className="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">
                                    Seven products, offer rules set by the shop,
                                    working cart, and PayPal test payments. Ask
                                    a question, make an offer, or buy at the
                                    listed price.
                                </p>
                            </div>
                            <Link
                                href="/store"
                                className="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-md border border-border px-5 text-sm font-medium hover:bg-surface"
                            >
                                Browse the shop
                                <ArrowRight
                                    aria-hidden="true"
                                    className="size-4"
                                />
                            </Link>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-border">
                    <div className="mx-auto flex max-w-6xl flex-col gap-2 px-6 py-8 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <p>OfferPilot · An AI sales helper for shops</p>
                        <p>
                            Demo data only · Payments use PayPal&apos;s test
                            system
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
