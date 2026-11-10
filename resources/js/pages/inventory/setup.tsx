import { type FormEvent } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function InventorySetup() {
    const { auth } = usePage().props as {
        auth: { user: { name: string; email: string } | null };
    };

    const form = useForm({
        name: '',
        tagline: '',
        email: auth.user?.email ?? '',
    });

    const { errors } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/inventory/setup');
    };

    return (
        <>
            <Head title="Open your shop" />

            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b border-border">
                    <div className="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
                        <p className="text-sm font-semibold">OfferPilot</p>
                        <Link
                            href="/store"
                            className="flex h-11 items-center text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            View the shop
                        </Link>
                    </div>
                </header>

                <main className="mx-auto max-w-xl px-6 py-12">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Open your shop
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        You are signed in as {auth.user?.name}. Name your shop
                        and you can start adding products and offers.
                    </p>

                    <form onSubmit={submit} className="mt-8 space-y-4">
                        <div>
                            <Label htmlFor="name">Shop name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                placeholder="Northsound Audio"
                                className="mt-1 h-11"
                            />
                            {errors.name && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div>
                            <Label htmlFor="tagline">Tagline</Label>
                            <Input
                                id="tagline"
                                value={form.data.tagline}
                                onChange={(e) =>
                                    form.setData('tagline', e.target.value)
                                }
                                placeholder="Studio-grade sound, sold by people who use it."
                                className="mt-1 h-11"
                            />
                            {errors.tagline && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.tagline}
                                </p>
                            )}
                        </div>

                        <div>
                            <Label htmlFor="email">Contact email</Label>
                            <Input
                                id="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                className="mt-1 h-11"
                            />
                            {errors.email && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="h-11 bg-brand text-brand-foreground hover:bg-brand-strong"
                        >
                            Create my shop
                        </Button>
                    </form>
                </main>
            </div>
        </>
    );
}
