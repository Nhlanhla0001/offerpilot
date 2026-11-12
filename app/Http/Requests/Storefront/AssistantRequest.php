<?php

namespace App\Http\Requests\Storefront;

use App\Models\Merchant;
use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\VerifiesConversationOwnership;
use Laravel\Ai\Models\Conversation;

class AssistantRequest extends FormRequest
{
    /**
     * The session key that binds a guest's conversation identifier to their session.
     */
    public const CONVERSATION_SESSION_KEY = 'storefront_conversation';

    /**
     * The session key holding the pending, buyer-confirmed cart proposals.
     */
    public const CART_PROPOSALS_SESSION_KEY = 'storefront_cart_proposals';

    /**
     * The session key holding explicit buyer price-ceiling authorizations.
     */
    public const CONFIRMED_CEILINGS_SESSION_KEY = 'storefront_confirmed_ceilings';

    /**
     * Determine if the user is authorized to make this request.
     *
     * Guests may talk to the shop assistant: the buyer does not need an account,
     * and any quote they are offered is bound to their session.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required_unless:confirmation_action,confirm_ceiling,confirm_quote_to_cart', 'nullable', 'string', 'max:2000'],
            'product_sku' => ['required_if:confirmation_action,confirm_ceiling,confirm_quote_to_cart', 'nullable', 'string', 'max:100'],
            'conversation_id' => ['nullable', 'string', 'max:36'],
            'action_id' => ['nullable', 'string', 'max:64'],
            'quote_id' => ['required_if:confirmation_action,confirm_quote_to_cart', 'nullable', 'string', 'max:64'],
            'confirmation_action' => ['nullable', 'in:confirm_ceiling,confirm_quote_to_cart'],
            'ceiling_price_cents' => ['required_if:confirmation_action,confirm_ceiling', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Configure the validator instance with the cross-shop and session-ownership checks.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('product_sku') && $this->product() === null) {
                    $validator->errors()->add('product_sku', 'This product is not available in this shop.');
                }

                if ($this->filled('conversation_id') && ! $this->ownsConversation()) {
                    $validator->errors()->add('conversation_id', 'This conversation does not belong to the current session.');
                }
            },
        ];
    }

    /**
     * Resolve the storefront merchant the same way ProductController does.
     */
    public function merchant(): ?Merchant
    {
        return Merchant::query()->orderBy('id')->first();
    }

    /**
     * Resolve the focused product within the current storefront merchant.
     */
    public function product(): ?Product
    {
        if (! $this->filled('product_sku')) {
            return null;
        }

        return $this->merchant()?->products()
            ->where('sku', (string) $this->input('product_sku'))
            ->first();
    }

    /**
     * Determine whether the supplied conversation belongs to the current buyer session.
     */
    private function ownsConversation(): bool
    {
        $conversationId = (string) $this->input('conversation_id');

        $user = $this->user();

        if ($user !== null) {
            $store = app(ConversationStore::class);

            if (! $store instanceof VerifiesConversationOwnership) {
                return false;
            }

            return $store->conversationBelongsTo(
                $conversationId,
                Conversation::participantType($user),
                Conversation::participantKey($user),
            );
        }

        // Guests own a conversation only when it is bound to their Laravel session.
        return $this->session()->get(self::CONVERSATION_SESSION_KEY) === $conversationId;
    }
}
