import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';

/**
 * Switch between light and dark. The choice persists in localStorage and a
 * cookie, so the server can render the right theme on the next request.
 */
export default function ThemeToggle({ className }: { className?: string }) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';
    const label = isDark
        ? 'Switch to light appearance'
        : 'Switch to dark appearance';

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label={label}
            title={label}
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            className={className}
        >
            {isDark ? (
                <Sun aria-hidden="true" className="size-4" />
            ) : (
                <Moon aria-hidden="true" className="size-4" />
            )}
        </Button>
    );
}
