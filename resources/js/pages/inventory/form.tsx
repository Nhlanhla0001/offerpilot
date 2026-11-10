import { type FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ProductForm = {
    id: number;
    name: string;
    sku: string;
    brand: string | null;
    category: string | null;
    type: string;
    description: string | null;
    list_price: string;
    floor_price: string;
    stock_quantity: number;
    image_path: string | null;
    negotiation_enabled: boolean;
    max_rounds: number;
};

const CATEGORIES = [
    'Audio',
    'Computing',
    'Displays',
    'Accessories',
    'Services',
];
const IMAGES = [
    { value: 'demo/audio.svg', label: 'Audio' },
    { value: 'demo/computing.svg', label: 'Computing' },
    { value: 'demo/displays.svg', label: 'Displays' },
    { value: 'demo/accessories.svg', label: 'Accessories' },
];

export default function InventoryForm({
    product,
}: {
    product: ProductForm | null;
}) {
    const form = useForm({
        name: product?.name ?? '',
        sku: product?.sku ?? '',
        brand: product?.brand ?? '',
        category: product?.category ?? 'Audio',
        type: product?.type ?? 'physical',
        description: product?.description ?? '',
        list_price: product?.list_price ?? '',
        floor_price: product?.floor_price ?? '',
        stock_quantity: product?.stock_quantity ?? 1,
        image_path: product?.image_path ?? 'demo/audio.svg',
        image: null as File | null,
        negotiation_enabled: product?.negotiation_enabled ?? true,
        max_rounds: product?.max_rounds ?? 3,
    });

    const { errors } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (product) {
            form.patch(`/inventory/${product.id}`, { forceFormData: true });
        } else {
            form.post('/inventory', { forceFormData: true });
        }
    };

    return (
        <>
            <Head title={product ? 'Edit product' : 'Add product'} />

            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b border-border">
                    <div className="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
                        <Link
                            href="/inventory"
                            className="flex h-11 items-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            ← Inventory
                        </Link>
                    </div>
                </header>

                <main className="mx-auto max-w-3xl px-6 py-8">
                    <h1 className="text-xl font-semibold">
                        {product ? 'Edit product' : 'Add a product or service'}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        The private minimum is what the assistant may never go
                        below. Buyers never see it.
                    </p>

                    <form onSubmit={submit} className="mt-6 space-y-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    className="mt-1 h-11"
                                />
                                {errors.name && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="sku">SKU</Label>
                                <Input
                                    id="sku"
                                    value={form.data.sku}
                                    onChange={(e) =>
                                        form.setData('sku', e.target.value)
                                    }
                                    placeholder="HEADPHONES-PRO"
                                    className="mt-1 h-11"
                                />
                                {errors.sku && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {errors.sku}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="brand">Brand</Label>
                                <Input
                                    id="brand"
                                    value={form.data.brand}
                                    onChange={(e) =>
                                        form.setData('brand', e.target.value)
                                    }
                                    className="mt-1 h-11"
                                />
                            </div>

                            <div>
                                <Label htmlFor="category">Category</Label>
                                <select
                                    id="category"
                                    value={form.data.category}
                                    onChange={(e) =>
                                        form.setData('category', e.target.value)
                                    }
                                    className="mt-1 h-11 w-full rounded-md border border-border bg-background px-3 text-sm"
                                >
                                    {CATEGORIES.map((c) => (
                                        <option key={c} value={c}>
                                            {c}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <Label htmlFor="type">Type</Label>
                                <select
                                    id="type"
                                    value={form.data.type}
                                    onChange={(e) =>
                                        form.setData('type', e.target.value)
                                    }
                                    className="mt-1 h-11 w-full rounded-md border border-border bg-background px-3 text-sm"
                                >
                                    <option value="physical">
                                        Physical product
                                    </option>
                                    <option value="service">Service</option>
                                </select>
                            </div>

                            <div>
                                <Label htmlFor="image_path">Image</Label>
                                <select
                                    id="image_path"
                                    value={
                                        form.data.image_path ?? 'demo/audio.svg'
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'image_path',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 h-11 w-full rounded-md border border-border bg-background px-3 text-sm"
                                >
                                    {IMAGES.map((i) => (
                                        <option key={i.value} value={i.value}>
                                            {i.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="rounded-md border border-border p-4">
                            <Label htmlFor="image">Upload your own photo</Label>
                            <input
                                id="image"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(e) =>
                                    form.setData(
                                        'image',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                                className="mt-2 block w-full text-sm file:mr-3 file:h-10 file:rounded-md file:border file:border-border file:bg-background file:px-3 file:text-sm"
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                JPG, PNG or WebP up to 2MB. Replaces the
                                illustration above.
                            </p>
                            {errors.image && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.image}
                                </p>
                            )}
                        </div>

                        <div>
                            <Label htmlFor="description">Description</Label>
                            <textarea
                                id="description"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                rows={3}
                                className="mt-1 w-full rounded-md border border-border bg-background p-3 text-sm"
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <Label htmlFor="list_price">
                                    Listed price (USD)
                                </Label>
                                <Input
                                    id="list_price"
                                    value={form.data.list_price}
                                    onChange={(e) =>
                                        form.setData(
                                            'list_price',
                                            e.target.value,
                                        )
                                    }
                                    inputMode="decimal"
                                    placeholder="250.00"
                                    className="mt-1 h-11"
                                />
                                {errors.list_price && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {errors.list_price}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="floor_price">
                                    Private minimum
                                </Label>
                                <Input
                                    id="floor_price"
                                    value={form.data.floor_price}
                                    onChange={(e) =>
                                        form.setData(
                                            'floor_price',
                                            e.target.value,
                                        )
                                    }
                                    inputMode="decimal"
                                    placeholder="210.00"
                                    className="mt-1 h-11"
                                />
                                {errors.floor_price && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {errors.floor_price}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="stock_quantity">Stock</Label>
                                <Input
                                    id="stock_quantity"
                                    value={String(form.data.stock_quantity)}
                                    onChange={(e) =>
                                        form.setData(
                                            'stock_quantity',
                                            Number(e.target.value),
                                        )
                                    }
                                    inputMode="numeric"
                                    className="mt-1 h-11"
                                />
                                {errors.stock_quantity && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {errors.stock_quantity}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-6 rounded-md border border-border p-4">
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.negotiation_enabled}
                                    onChange={(e) =>
                                        form.setData(
                                            'negotiation_enabled',
                                            e.target.checked,
                                        )
                                    }
                                    className="size-4"
                                />
                                Open to offers
                            </label>

                            <div className="flex items-center gap-2">
                                <Label htmlFor="max_rounds" className="text-sm">
                                    Rounds
                                </Label>
                                <Input
                                    id="max_rounds"
                                    value={String(form.data.max_rounds)}
                                    onChange={(e) =>
                                        form.setData(
                                            'max_rounds',
                                            Number(e.target.value),
                                        )
                                    }
                                    inputMode="numeric"
                                    className="h-11 w-20"
                                />
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="h-11 bg-brand text-brand-foreground hover:bg-brand-strong"
                            >
                                {product ? 'Save changes' : 'Add product'}
                            </Button>
                            <Link
                                href="/inventory"
                                className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                </main>
            </div>
        </>
    );
}
