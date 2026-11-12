import { Link } from '@inertiajs/react';
import { BadgeCheck, MessagesSquare } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import SellerLayout from '@/layouts/seller-layout';
import Pagination, { type Paginated } from '@/components/pagination';

type Offer = {
    id: string;
    product: string | null;
    sku: string | null;
    buyer: string | null;
    offered: string;
    held: string | null;
    rounds: number;
    status: 'Open' | 'Paid' | 'Expired';
    expires_at: string | null;
    placed_at: string | null;
};

type Counts = {
    all: number;
    open: number;
    paid: number;
    expired: number;
};

const TABS: { key: string; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'open', label: 'Open' },
    { key: 'paid', label: 'Paid' },
    { key: 'expired', label: 'Expired' },
];

export default function OffersIndex({
    offers,
    counts,
    filters,
}: {
    merchantName: string;
    offers: Paginated<Offer>;
    counts: Counts;
    filters: { status: string };
}) {
    const description =
        counts.all === 0
            ? 'No buyer has made an offer yet. Every negotiation with the assistant appears here.'
            : `${counts.open} open ${counts.open === 1 ? 'offer' : 'offers'}, ${counts.paid} paid, ${counts.expired} expired.`;
    const offerItems = offers.data;

    return (
        <SellerLayout title="Offers" description={description}>
            <div className="flex flex-wrap items-center gap-2">
                {TABS.map((tab) => {
                    const active = filters.status === tab.key;
                    const count = counts[tab.key as keyof Counts];

                    return (
                        <Link
                            key={tab.key}
                            href={
                                tab.key === 'all'
                                    ? '/offers'
                                    : `/offers?status=${tab.key}`
                            }
                            aria-current={active ? 'page' : undefined}
                            className={
                                active
                                    ? 'flex h-11 items-center gap-1.5 rounded-full border border-foreground bg-foreground px-4 text-sm font-medium text-background'
                                    : 'flex h-11 items-center gap-1.5 rounded-full border border-border px-4 text-sm text-muted-foreground hover:border-foreground/30 hover:text-foreground'
                            }
                        >
                            {tab.label}
                            <span className="text-xs tabular-nums opacity-70">
                                {count}
                            </span>
                        </Link>
                    );
                })}
            </div>

            {offerItems.length === 0 ? (
                <div className="rounded-lg border border-dashed border-border px-6 py-12 text-center">
                    <MessagesSquare
                        aria-hidden="true"
                        className="mx-auto size-8 text-muted-foreground"
                    />
                    <p className="mt-3 text-sm font-medium">
                        {filters.status === 'all'
                            ? 'Nothing offered yet'
                            : `No ${filters.status} offers`}
                    </p>
                    <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                        {filters.status === 'all'
                            ? 'Share your shop. When a buyer names a price, the negotiation shows up here with what they asked for and what the shop held.'
                            : 'Switch filters to see the rest of your negotiations.'}
                    </p>
                </div>
            ) : (
                <section className="overflow-hidden rounded-lg border border-border bg-card">
                    <div className="flex items-baseline gap-2 border-b border-border px-5 py-4">
                        <h2 className="text-sm font-semibold">Negotiations</h2>
                        <p className="text-xs text-muted-foreground tabular-nums">
                            {offers.total}{' '}
                            {offers.total === 1 ? 'offer' : 'offers'}
                        </p>
                    </div>

                    <div className="w-full overflow-x-auto contain-paint">
                        <table className="w-full text-sm">
                            <thead className="bg-surface text-left text-xs tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-5 py-3 font-medium">
                                        Item
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Buyer
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        They offered
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Shop held
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Rounds
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-5 py-3 font-medium">
                                        First heard
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {offerItems.map((offer) => (
                                    <tr
                                        key={offer.id}
                                        className="transition-colors hover:bg-surface"
                                    >
                                        <td className="px-5 py-3">
                                            <p className="font-medium">
                                                {offer.product ??
                                                    'Removed item'}
                                            </p>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {offer.sku ?? ''}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            {offer.buyer ?? 'Guest'}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            ${offer.offered}
                                        </td>
                                        <td className="px-4 py-3 text-right font-medium tabular-nums">
                                            {offer.held
                                                ? `$${offer.held}`
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {offer.rounds}
                                        </td>
                                        <td className="px-4 py-3">
                                            {offer.status === 'Paid' ? (
                                                <Badge className="gap-1 border-brand/25 bg-brand/10 text-brand-strong">
                                                    <BadgeCheck
                                                        aria-hidden="true"
                                                        className="size-3"
                                                    />
                                                    Paid
                                                </Badge>
                                            ) : offer.status === 'Open' ? (
                                                <Badge variant="secondary">
                                                    Open
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline">
                                                    Expired
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-5 py-3 text-xs text-muted-foreground">
                                            {offer.placed_at ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}
            <Pagination pages={offers} label="Offers" />
        </SellerLayout>
    );
}
