import { Head, Link } from '@inertiajs/react';
import { show as storeShow } from '@/routes/store';

type Product = {
    sku: string;
    name: string;
    description: string | null;
    price: string;
    merchant: string;
};

export default function StoreIndex({ products }: { products: Product[] }) {
    return (
        <>
            <Head title="OfferPilot" />

            <div className="min-h-screen bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <header className="border-b border-[#19140014] dark:border-[#3E3E3A]">
                    <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
                        <div>
                            <p className="text-lg font-semibold">OfferPilot</p>
                            <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                Name your budget. The merchant meets you where it can.
                            </p>
                        </div>
                        <Link
                            href="/"
                            className="text-sm text-[#706f6c] underline-offset-4 hover:underline dark:text-[#A1A09A]"
                        >
                            Home
                        </Link>
                    </div>
                </header>

                <main className="mx-auto max-w-5xl px-6 py-10">
                    <h1 className="mb-6 text-2xl font-semibold">Open to offers</h1>

                    {products.length === 0 ? (
                        <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                            No products are open to offers yet. Run{' '}
                            <code className="rounded bg-[#1914000f] px-1.5 py-0.5 dark:bg-[#ffffff14]">
                                php artisan db:seed
                            </code>
                            .
                        </p>
                    ) : (
                        <ul className="grid gap-4 sm:grid-cols-2">
                            {products.map((product) => (
                                <li
                                    key={product.sku}
                                    className="rounded-lg border border-[#19140014] p-5 dark:border-[#3E3E3A]"
                                >
                                    <p className="text-xs uppercase tracking-wide text-[#706f6c] dark:text-[#A1A09A]">
                                        {product.merchant}
                                    </p>
                                    <h2 className="mt-1 text-lg font-medium">{product.name}</h2>
                                    <p className="mt-1 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                        {product.description}
                                    </p>
                                    <div className="mt-4 flex items-center justify-between">
                                        <span className="text-base font-semibold">${product.price}</span>
                                        <Link
                                            href={storeShow(product.sku).url}
                                            className="rounded-sm border border-[#19140035] px-4 py-1.5 text-sm hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
                                        >
                                            Negotiate
                                        </Link>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </main>
            </div>
        </>
    );
}
