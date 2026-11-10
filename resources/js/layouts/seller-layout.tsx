import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Bell,
    LayoutDashboard,
    Package,
    ReceiptText,
    Store,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import ThemeToggle from '@/components/theme-toggle';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';

type NavItem = {
    href: string;
    label: string;
    icon: LucideIcon;
    current: boolean;
    badge?: number;
};

/**
 * The seller's workspace shell: a persistent shop sidebar beside the work.
 */
export default function SellerLayout({
    title,
    description,
    children,
    actions,
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
    actions?: React.ReactNode;
}) {
    const page = usePage();
    const { auth, merchantName, unreadNotifications, sidebarOpen } =
        page.props as {
            auth?: {
                user?: { name: string | null; email: string | null } | null;
            };
            merchantName?: string | null;
            unreadNotifications?: number;
            sidebarOpen?: boolean;
        };

    const path = page.url.split('?')[0];

    const overview: NavItem[] = [
        {
            href: '/dashboard',
            label: 'Dashboard',
            icon: LayoutDashboard,
            current: path === '/dashboard',
        },
    ];

    const shop: NavItem[] = [
        {
            href: '/inventory',
            label: 'Inventory',
            icon: Package,
            current: path.startsWith('/inventory'),
        },
        {
            href: '/orders',
            label: 'Orders',
            icon: ReceiptText,
            current: path.startsWith('/orders'),
        },
        {
            href: '/notifications',
            label: 'Notifications',
            icon: Bell,
            current: path.startsWith('/notifications'),
            badge: unreadNotifications,
        },
    ];

    const renderGroup = (label: string, items: NavItem[]) => (
        <SidebarGroup>
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.href}>
                        <SidebarMenuButton
                            asChild
                            isActive={item.current}
                            tooltip={item.label}
                            className="h-11"
                        >
                            <Link
                                href={item.href}
                                aria-current={item.current ? 'page' : undefined}
                            >
                                <item.icon aria-hidden="true" />
                                <span>{item.label}</span>
                                {(item.badge ?? 0) > 0 && (
                                    <Badge
                                        variant="secondary"
                                        className="ml-auto h-5 min-w-5 justify-center rounded-full px-1.5 text-[11px] tabular-nums group-data-[collapsible=icon]:hidden"
                                    >
                                        {item.badge}
                                    </Badge>
                                )}
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );

    return (
        <div className="min-h-svh bg-background text-foreground">
            <Head title={title} />

            <SidebarProvider defaultOpen={sidebarOpen ?? true}>
                <Sidebar collapsible="icon" variant="inset">
                    <SidebarHeader>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton size="lg" asChild>
                                    <Link href="/dashboard" className="gap-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary text-sm font-semibold text-primary-foreground">
                                            OP
                                        </span>
                                        <span className="grid min-w-0 flex-1 leading-tight group-data-[collapsible=icon]:hidden">
                                            <span className="truncate text-sm font-semibold">
                                                OfferPilot
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {merchantName ??
                                                    auth?.user?.name ??
                                                    'Seller'}
                                            </span>
                                        </span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarHeader>

                    <SidebarContent>
                        {overview.length > 0 &&
                            renderGroup('Overview', overview)}
                        {renderGroup('Shop', shop)}
                    </SidebarContent>

                    <SidebarFooter>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    tooltip="View shop"
                                    className="h-11"
                                >
                                    <Link href="/store">
                                        <Store aria-hidden="true" />
                                        <span>View shop</span>
                                        <ArrowUpRight
                                            aria-hidden="true"
                                            className="ml-auto size-3.5 text-muted-foreground group-data-[collapsible=icon]:hidden"
                                        />
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    tooltip="Account"
                                    className="h-11"
                                >
                                    <Link href="/settings/profile">
                                        <span className="flex size-6 shrink-0 items-center justify-center rounded-full border border-border text-[11px] font-medium">
                                            {(
                                                merchantName ??
                                                auth?.user?.name ??
                                                'S'
                                            )
                                                .slice(0, 1)
                                                .toUpperCase()}
                                        </span>
                                        <span className="truncate">
                                            {auth?.user?.name ?? 'Account'}
                                        </span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarFooter>
                </Sidebar>

                <SidebarInset className="min-w-0">
                    <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-2 border-b border-border bg-background/85 px-4 backdrop-blur-sm md:px-6">
                        <SidebarTrigger className="-ml-2 size-11 md:-ml-1 md:size-7" />
                        <span
                            className="h-4 w-px bg-border"
                            aria-hidden="true"
                        />
                        <p className="truncate text-sm text-muted-foreground">
                            <span className="hidden sm:inline">Seller</span>
                            <span className="hidden sm:inline"> / </span>
                            <span className="font-medium text-foreground">
                                {title}
                            </span>
                        </p>

                        <div className="ml-auto flex items-center gap-2">
                            <ThemeToggle className="size-11 md:size-9" />
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="h-11 md:h-9"
                            >
                                <Link href="/store">
                                    View shop
                                    <ArrowUpRight
                                        aria-hidden="true"
                                        className="ml-1 size-3.5"
                                    />
                                </Link>
                            </Button>
                        </div>
                    </header>

                    <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div className="min-w-0">
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    {title}
                                </h1>
                                {description && (
                                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                                        {description}
                                    </p>
                                )}
                            </div>
                            {actions}
                        </div>

                        {children}
                    </main>
                </SidebarInset>
            </SidebarProvider>
        </div>
    );
}
