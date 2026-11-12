import { Link } from '@inertiajs/react';

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

export default function Pagination<T>({
    pages,
    label,
}: {
    pages: Paginated<T>;
    label: string;
}) {
    if (pages.last_page < 2) {
        return null;
    }

    return (
        <nav
            aria-label={`${label} pagination`}
            className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-1 py-4"
        >
            <p className="text-xs text-muted-foreground tabular-nums">
                Showing {pages.from}–{pages.to} of {pages.total}
            </p>
            <div className="flex flex-wrap items-center gap-1">
                {pages.links.map((link) => {
                    const text = link.label
                        .replace('&laquo;', '‹')
                        .replace('&raquo;', '›');
                    const className = link.active
                        ? 'inline-flex min-h-10 min-w-10 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground'
                        : 'inline-flex min-h-10 min-w-10 items-center justify-center rounded-md border border-border px-3 text-sm text-foreground hover:bg-surface';

                    return link.url ? (
                        <Link
                            key={`${link.label}-${link.url}`}
                            href={link.url}
                            preserveScroll
                            preserveState
                            aria-current={link.active ? 'page' : undefined}
                            className={className}
                        >
                            {text}
                        </Link>
                    ) : (
                        <span
                            key={`${link.label}-disabled`}
                            aria-disabled="true"
                            className="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md px-3 text-sm text-muted-foreground/50"
                        >
                            {text}
                        </span>
                    );
                })}
            </div>
        </nav>
    );
}
