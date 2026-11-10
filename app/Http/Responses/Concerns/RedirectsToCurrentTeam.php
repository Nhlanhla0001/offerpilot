<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\Request;

/**
 * OfferPilot has a single seller workspace, so post-auth redirects are flat.
 *
 * This trait keeps the existing response classes intact while dropping the
 * starter kit's team-prefixed redirect (and its URL defaults).
 */
trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        return $redirect;
    }
}
