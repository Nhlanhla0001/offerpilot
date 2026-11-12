<?php

use App\Models\Merchant;
use App\Models\StorefrontEvent;
use App\Models\User;
use App\Services\StorefrontExperiment;
use Illuminate\Http\Request;

/**
 * Build a storefront request bound to a fresh session and an optional buyer.
 */
function experimentRequest(?User $user = null, string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0 Safari/537.36'): Request
{
    $session = app('session')->driver();
    $session->start();

    $request = Request::create('/store', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
    ]);
    $request->setLaravelSession($session);

    if ($user !== null) {
        $request->setUserResolver(fn (): ?User => $user);
    }

    return $request;
}

/**
 * Bind an experiment that returns the requested arm whenever a random split is drawn.
 */
function forcedExperiment(string $variant): StorefrontExperiment
{
    return new class($variant) extends StorefrontExperiment
    {
        public function __construct(private readonly string $forcedVariant)
        {
            //
        }

        protected function randomVariant(): string
        {
            return $this->forcedVariant;
        }
    };
}

test('an eligible session is assigned the control arm and keeps it stable while inactive', function () {
    Merchant::factory()->create();

    $request = experimentRequest();
    $experiment = app(StorefrontExperiment::class);

    expect($experiment->assign($request))->toBe('control')
        ->and($experiment->assign($request))->toBe('control')
        ->and(StorefrontEvent::query()->where('name', 'storefront_entry')->count())->toBe(1);
});

test('eligible storefront requests in one browser count the assignment denominator once', function () {
    Merchant::factory()->create();

    $firstVisit = $this->get('/store');
    $secondVisit = $this->get('/store');

    $firstVisit->assertOk();
    $secondVisit->assertOk();

    expect(StorefrontEvent::query()->where('name', 'storefront_entry')->count())->toBe(1);
});

test('an eligible session is assigned each arm deterministically when the experiment is active', function (string $variant) {
    config(['storefront.experiment.active' => true]);
    app()->instance(StorefrontExperiment::class, forcedExperiment($variant));
    Merchant::factory()->create();

    $request = experimentRequest();
    $experiment = app(StorefrontExperiment::class);

    expect($experiment->assign($request))->toBe($variant)
        ->and($request->session()->get(StorefrontExperiment::SESSION_KEY))->toBe($variant)
        ->and(StorefrontEvent::query()->where('name', 'storefront_entry')->where('variant', $variant)->count())->toBe(1);
})->with(['control', 'treatment']);

test('an already assigned arm is kept stable and is not counted again', function () {
    Merchant::factory()->create();

    $request = experimentRequest();
    $request->session()->put(StorefrontExperiment::SESSION_KEY, 'treatment');

    $experiment = app(StorefrontExperiment::class);

    expect($experiment->assign($request))->toBe('treatment')
        ->and(StorefrontEvent::query()->where('name', 'storefront_entry')->count())->toBe(0);
});

test('a seller is never assigned to the experiment', function () {
    $seller = User::factory()->create();
    Merchant::factory()->create(['user_id' => $seller->id]);

    $request = experimentRequest($seller);
    $experiment = app(StorefrontExperiment::class);

    expect($experiment->assign($request))->toBe('control')
        ->and($request->session()->get(StorefrontExperiment::SESSION_KEY))->toBeNull()
        ->and(StorefrontEvent::query()->count())->toBe(0);
});

test('a request with no published merchant is not assigned', function () {
    $request = experimentRequest();
    $experiment = app(StorefrontExperiment::class);

    expect($experiment->assign($request))->toBe('control')
        ->and(StorefrontEvent::query()->count())->toBe(0);
});
