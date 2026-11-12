<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\StorefrontExperiment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Same-origin endpoint for client-only storefront events.
 *
 * Only the allowlisted client event names are accepted, so the browser can
 * never fabricate a server-issued event such as a verified purchase. Property
 * values are sanitised again by {@see StorefrontExperiment::record()}.
 */
class StorefrontEventController extends Controller
{
    public function __construct(private readonly StorefrontExperiment $experiment)
    {
        //
    }

    /**
     * Record a single category-only client event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', Rule::in(StorefrontExperiment::CLIENT_EVENT_NAMES)],
            'properties' => ['sometimes', 'array:intent', 'max:1'],
            'properties.intent' => ['sometimes', 'string', 'max:8', Rule::in(['question', 'offer'])],
        ]);

        $this->experiment->record(
            $request,
            (string) $validated['name'],
            $validated['properties'] ?? [],
        );

        return response()->json(['status' => 'recorded']);
    }
}
