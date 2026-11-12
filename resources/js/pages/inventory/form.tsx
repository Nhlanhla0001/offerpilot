import { type FormEvent } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SellerLayout from '@/layouts/seller-layout';

type ProductForm = {
    id: number;
    name: string;
    sku: string;
    brand: string | null;
    category: string | null;
    type: string;
    description: string | null;
    list_price: string;
    unit_cost: string | null;
    floor_price: string;
    stock_quantity: number;
    image_path: string | null;
    features: string[];
    specifications: SpecificationRow[];
    negotiation_enabled: boolean;
    max_rounds: number;
};

type SpecificationRow = { key: string; value: string };

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
        unit_cost: product?.unit_cost ?? '',
        floor_price: product?.floor_price ?? '',
        stock_quantity: product?.stock_quantity ?? 1,
        image_path: product?.image_path ?? 'demo/audio.svg',
        image: null as File | null,
        features: product?.features ?? [],
        specifications: product?.specifications ?? [],
        negotiation_enabled: product?.negotiation_enabled ?? true,
        max_rounds: product?.max_rounds ?? 3,
    });

    const { errors } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            features: data.features
                .map((feature) => feature.trim())
                .filter((feature) => feature !== ''),
            specifications: data.specifications
                .map((specification) => ({
                    key: specification.key.trim(),
                    value: specification.value.trim(),
                }))
                .filter(
                    (specification) =>
                        specification.key !== '' || specification.value !== '',
                ),
        }));

        if (product) {
            form.patch(`/inventory/${product.id}`, { forceFormData: true });
        } else {
            form.post('/inventory', { forceFormData: true });
        }
    };

    return (
        <SellerLayout
            title={product ? 'Edit product' : 'Add product'}
            description="The private minimum is what the assistant may never go below. Buyers never see it."
            actions={
                <Button asChild variant="outline" className="h-11">
                    <Link href="/inventory">Back to inventory</Link>
                </Button>
            }
        >
            <form onSubmit={submit} className="max-w-3xl space-y-5">
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
                            <option value="physical">Physical product</option>
                            <option value="service">Service</option>
                        </select>
                    </div>

                    <div>
                        <Label htmlFor="image_path">Image</Label>
                        <select
                            id="image_path"
                            value={form.data.image_path ?? 'demo/audio.svg'}
                            onChange={(e) =>
                                form.setData('image_path', e.target.value)
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
                            form.setData('image', e.target.files?.[0] ?? null)
                        }
                        className="mt-2 block w-full text-sm file:mr-3 file:h-10 file:rounded-md file:border file:border-border file:bg-background file:px-3 file:text-sm"
                    />
                    <p className="mt-1 text-xs text-muted-foreground">
                        JPG, PNG or WebP up to 2MB. Replaces the illustration
                        above.
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

                <section className="space-y-3 rounded-md border border-border p-4">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Product features
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            These appear on your product page and help the
                            assistant answer buyer questions.
                        </p>
                    </div>
                    {form.data.features.map((feature, index) => (
                        <div key={index} className="flex items-center gap-2">
                            <Label
                                htmlFor={`feature-${index}`}
                                className="sr-only"
                            >
                                Feature {index + 1}
                            </Label>
                            <Input
                                id={`feature-${index}`}
                                value={feature}
                                maxLength={200}
                                onChange={(event) => {
                                    const features = [...form.data.features];
                                    features[index] = event.target.value;
                                    form.setData('features', features);
                                }}
                                placeholder="e.g. USB-C connection"
                                className="h-11"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                className="size-11 shrink-0 p-0"
                                aria-label={`Remove feature ${index + 1}`}
                                onClick={() =>
                                    form.setData(
                                        'features',
                                        form.data.features.filter(
                                            (_, rowIndex) => rowIndex !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 aria-hidden="true" className="size-4" />
                            </Button>
                            {errors[`features.${index}`] && (
                                <p className="text-xs text-destructive">
                                    {errors[`features.${index}`]}
                                </p>
                            )}
                        </div>
                    ))}
                    {errors.features && (
                        <p role="alert" className="text-sm text-destructive">
                            {errors.features}
                        </p>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11"
                        disabled={form.data.features.length >= 20}
                        onClick={() =>
                            form.setData('features', [
                                ...form.data.features,
                                '',
                            ])
                        }
                    >
                        <Plus aria-hidden="true" className="size-4" />
                        Add feature
                    </Button>
                </section>

                <section className="space-y-3 rounded-md border border-border p-4">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Specifications
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Add clear labels and values. The assistant only
                            states specifications you provide here.
                        </p>
                    </div>
                    {form.data.specifications.map((specification, index) => (
                        <div
                            key={index}
                            className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                        >
                            <div>
                                <Label
                                    htmlFor={`specification-key-${index}`}
                                    className="sr-only"
                                >
                                    Specification name {index + 1}
                                </Label>
                                <Input
                                    id={`specification-key-${index}`}
                                    value={specification.key}
                                    maxLength={80}
                                    onChange={(event) => {
                                        const specifications = [
                                            ...form.data.specifications,
                                        ];
                                        specifications[index] = {
                                            ...specification,
                                            key: event.target.value,
                                        };
                                        form.setData(
                                            'specifications',
                                            specifications,
                                        );
                                    }}
                                    placeholder="Specification name"
                                    className="h-11"
                                />
                                {errors[`specifications.${index}.key`] && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {errors[`specifications.${index}.key`]}
                                    </p>
                                )}
                            </div>
                            <div>
                                <Label
                                    htmlFor={`specification-value-${index}`}
                                    className="sr-only"
                                >
                                    Specification value {index + 1}
                                </Label>
                                <Input
                                    id={`specification-value-${index}`}
                                    value={specification.value}
                                    maxLength={200}
                                    onChange={(event) => {
                                        const specifications = [
                                            ...form.data.specifications,
                                        ];
                                        specifications[index] = {
                                            ...specification,
                                            value: event.target.value,
                                        };
                                        form.setData(
                                            'specifications',
                                            specifications,
                                        );
                                    }}
                                    placeholder="Specification value"
                                    className="h-11"
                                />
                                {errors[`specifications.${index}.value`] && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {
                                            errors[
                                                `specifications.${index}.value`
                                            ]
                                        }
                                    </p>
                                )}
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                className="size-11 shrink-0 p-0"
                                aria-label={`Remove specification ${index + 1}`}
                                onClick={() =>
                                    form.setData(
                                        'specifications',
                                        form.data.specifications.filter(
                                            (_, rowIndex) => rowIndex !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 aria-hidden="true" className="size-4" />
                            </Button>
                        </div>
                    ))}
                    {errors.specifications && (
                        <p role="alert" className="text-sm text-destructive">
                            {errors.specifications}
                        </p>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11"
                        disabled={form.data.specifications.length >= 20}
                        onClick={() =>
                            form.setData('specifications', [
                                ...form.data.specifications,
                                { key: '', value: '' },
                            ])
                        }
                    >
                        <Plus aria-hidden="true" className="size-4" />
                        Add specification
                    </Button>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <Label htmlFor="list_price">Listed price (USD)</Label>
                        <Input
                            id="list_price"
                            value={form.data.list_price}
                            onChange={(e) =>
                                form.setData('list_price', e.target.value)
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
                        <Label htmlFor="unit_cost">
                            Unit cost (USD, private)
                        </Label>
                        <Input
                            id="unit_cost"
                            value={form.data.unit_cost}
                            onChange={(e) =>
                                form.setData('unit_cost', e.target.value)
                            }
                            inputMode="decimal"
                            placeholder="Optional"
                            className="mt-1 h-11"
                        />
                        {errors.unit_cost && (
                            <p className="mt-1 text-xs text-destructive">
                                {errors.unit_cost}
                            </p>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="floor_price">Private minimum</Label>
                        <Input
                            id="floor_price"
                            value={form.data.floor_price}
                            onChange={(e) =>
                                form.setData('floor_price', e.target.value)
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
                        className="inline-flex h-11 items-center text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </SellerLayout>
    );
}
