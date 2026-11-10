<?php

namespace App\Services;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Identifies the buyer without requiring an account.
 *
 * A signed-in buyer is identified by their user id. A guest gets a ULID in the
 * session the first time they negotiate or start a checkout, and every quote
 * they own carries that token so one browser can never reach another's quote.
 */
class BuyerSession
{
    public const KEY = 'buyer_token';

    /**
     * Get the guest token for this session, creating one on first use.
     */
    public function token(Request $request): string
    {
        $token = $request->session()->get(self::KEY);

        if (! is_string($token) || $token === '') {
            $token = (string) Str::ulid();
            $request->session()->put(self::KEY, $token);
        }

        return $token;
    }

    /**
     * Get the token that should be stored on a new quote, or null when signed in.
     */
    public function tokenForQuote(Request $request): ?string
    {
        return $request->user() === null ? $this->token($request) : null;
    }

    /**
     * Restrict a quote query to the buyer making this request.
     *
     * @param  Builder<Quote>  $query
     * @return Builder<Quote>
     */
    public function scope(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        return $user !== null
            ? $query->where('user_id', $user->id)
            : $query->where('guest_token', $this->token($request));
    }

    /**
     * Determine whether this request owns the given quote.
     */
    public function owns(Request $request, ?int $userId, ?string $guestToken): bool
    {
        $user = $request->user();

        if ($user !== null) {
            return $userId !== null && $userId === $user->id;
        }

        return $guestToken !== null && $guestToken === $request->session()->get(self::KEY);
    }
}
