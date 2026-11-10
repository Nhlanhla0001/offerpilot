import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { Input } from '@/components/ui/input';

/**
 * A debounced search field that asks the server for a filtered list.
 *
 * The term lives in the page's query string, so a reload keeps the results and
 * a partial visit keeps the field focused while typing.
 */
export default function SearchField({
    url,
    value,
    placeholder,
    label,
    className = '',
}: {
    url: string;
    value: string;
    placeholder: string;
    label: string;
    className?: string;
}) {
    const [term, setTerm] = useState(value);
    const initial = useRef(true);

    useEffect(() => {
        if (initial.current) {
            initial.current = false;

            return;
        }

        const timer = setTimeout(() => {
            const query = term.trim();

            router.get(url, query === '' ? {} : { q: query }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);

        return () => clearTimeout(timer);
    }, [term, url]);

    return (
        <div className={`relative w-full sm:w-72 ${className}`}>
            <Search
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                type="text"
                value={term}
                onChange={(event) => setTerm(event.target.value)}
                placeholder={placeholder}
                aria-label={label}
                className="h-11 pr-10 pl-9"
            />
            {term !== '' && (
                <button
                    type="button"
                    onClick={() => setTerm('')}
                    aria-label="Clear search"
                    className="absolute top-1/2 right-1 flex size-9 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground hover:text-foreground"
                >
                    <X aria-hidden="true" className="size-4" />
                </button>
            )}
        </div>
    );
}
