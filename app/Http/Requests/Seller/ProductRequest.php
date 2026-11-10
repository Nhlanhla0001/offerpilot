<?php

namespace App\Http\Requests\Seller;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->merchant !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'sku' => [
                'required', 'string', 'max:64', 'alpha_dash',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'brand' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:80'],
            'type' => ['required', Rule::in(['physical', 'service'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'list_price' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'floor_price' => ['required', 'numeric', 'min:0.01', 'lte:list_price'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'negotiation_enabled' => ['boolean'],
            'max_rounds' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'floor_price.lte' => 'The private minimum cannot be higher than the listed price.',
        ];
    }
}
