<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class NegotiateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Guests may negotiate: the buyer does not need an account to talk to the
     * assistant, and the issued quote is bound to their session.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The buyer does not have to state a price to talk to the assistant: they may
     * simply ask a question. When they do name a figure, the assistant negotiates.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'target_price_cents' => ['nullable', 'integer', 'min:1'],
            'ceiling_price_cents' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
