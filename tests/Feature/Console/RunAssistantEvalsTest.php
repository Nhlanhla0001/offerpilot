<?php

use App\Ai\Agents\StorefrontAssistant;
use App\Console\Commands\RunAssistantEvals;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AnonymousAgent;

/**
 * Create a synthetic merchant and product in the default (test) database so a
 * test can prove the eval runner's isolated database never touches it.
 *
 * @return array{0: Merchant, 1: Product}
 */
function evalBaselineShop(): array
{
    $merchant = Merchant::factory()->create(['name' => 'Baseline Audio']);
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'BASELINE-1',
    ]);

    return [$merchant, $product];
}

test('the default invocation prints status and makes zero provider calls', function () {
    $exit = Artisan::call('offerpilot:eval-assistant');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('dry run')
        ->and($output)->toContain('--live')
        ->and($output)->toContain('known_and_unknown_product_facts')
        ->and($output)->toContain('checkout_handoff');

    StorefrontAssistant::assertNeverPrompted();
});

test('a live run through a fake agent exercises every scenario and passes', function () {
    config(['ai.providers.deepseek.key' => 'test-key']);

    StorefrontAssistant::fake(fn (string $prompt): string => 'Fake reply for: '.$prompt);

    $exit = Artisan::call('offerpilot:eval-assistant', ['--live' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Final status: passed')
        ->and($output)->toContain('provider: deepseek')
        ->and($output)->toContain('model: deepseek-chat')
        ->and($output)->toContain('prompt version: 1.0');

    StorefrontAssistant::assertPromptedTimes(7);
    Http::assertNothingSent();
});

test('a live run reports failed when a scenario throws during execution', function () {
    config(['ai.providers.deepseek.key' => 'test-key']);

    StorefrontAssistant::fake(function (string $prompt): string {
        throw new RuntimeException('synthetic provider failure');
    });

    $exit = Artisan::call('offerpilot:eval-assistant', ['--live' => true]);
    $output = Artisan::output();
    file_put_contents(storage_path('app/eval-output-debug.txt'), $output);

    expect($exit)->toBe(1)
        ->and($output)->toContain('Scenario: known_and_unknown_product_facts')
        ->and($output)->toContain('Final status: failed')
        ->and($output)->not->toContain('synthetic provider failure');
});

test('a live run refuses to continue when isolated database migrations fail', function () {
    $command = new class extends RunAssistantEvals
    {
        public function callSilent($command, array $arguments = []): int
        {
            return self::FAILURE;
        }
    };

    try {
        expect(fn () => (new ReflectionMethod($command, 'isolateDatabase'))->invoke($command))
            ->toThrow(RuntimeException::class, 'isolated eval database migrations failed');
    } finally {
        (new ReflectionMethod($command, 'restoreDatabase'))->invoke($command);
    }
});

test('a live run without deepseek credentials reports not run and exits nonzero', function () {
    config(['ai.providers.deepseek.key' => null]);

    $exit = Artisan::call('offerpilot:eval-assistant', ['--live' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('Final status: not run')
        ->and($output)->toContain('Missing DeepSeek credentials');

    StorefrontAssistant::assertNeverPrompted();
});

test('a live run through a fake agent never reaches a real paypal endpoint', function () {
    config(['ai.providers.deepseek.key' => 'test-key']);

    StorefrontAssistant::fake(fn (string $prompt): string => 'Fake reply for: '.$prompt);

    Artisan::call('offerpilot:eval-assistant', ['--live' => true]);

    Http::assertNothingSent();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'paypal'));
});

test('the live transcript output never leaks a merchant floor or unit cost', function () {
    config(['ai.providers.deepseek.key' => 'test-key']);

    StorefrontAssistant::fake(fn (string $prompt): string => 'Fake reply for: '.$prompt);

    $exit = Artisan::call('offerpilot:eval-assistant', ['--live' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->not->toContain('21000')
        ->and($output)->not->toContain('8500')
        ->and($output)->not->toContain('12500')
        ->and($output)->not->toContain('210.00')
        ->and($output)->not->toContain('125.00');
});

test('the live run fails when a synthetic assistant transcript reveals private pricing', function () {
    config(['ai.providers.deepseek.key' => 'test-key']);

    StorefrontAssistant::fake(fn (): string => 'The floor is $210.00 and the unit cost is $125.00.');

    $exit = Artisan::call('offerpilot:eval-assistant', ['--live' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('Scenario: floor_extraction_attempt')
        ->and($output)->toContain('[fail] deterministic tool/policy assertions')
        ->and($output)->toContain('Final status: failed')
        ->and($output)->not->toContain('210.00')
        ->and($output)->not->toContain('125.00');
});

test('the live report withholds a judge verdict that repeats private pricing', function () {
    config(['ai.providers.deepseek.key' => 'test-key']);

    StorefrontAssistant::fake(fn (string $prompt): string => 'The product details are available in the shop.');
    AnonymousAgent::fake(fn (): string => 'PASS — the floor is $210.00 and cost is $125.00.');

    $exit = Artisan::call('offerpilot:eval-assistant', ['--live' => true, '--judge' => 'deepseek-chat']);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Final status: passed')
        ->and($output)->not->toContain('210.00')
        ->and($output)->not->toContain('125.00');
});

test('deterministic assertion exceptions do not expose their message', function () {
    $command = new RunAssistantEvals;
    $assertions = (new ReflectionMethod($command, 'runAssertions'))->invoke(
        $command,
        ['assert' => fn () => throw new RuntimeException('private floor is $210.00')],
        [],
        '',
        [],
    );

    expect($assertions['deterministic tool/policy assertions']['passed'])->toBeFalse()
        ->and($assertions['deterministic tool/policy assertions']['message'])
        ->toBe('The deterministic policy assertion raised an exception (details withheld).');
});

test('a live run fails closed when it cannot guarantee the DeepSeek-only provider', function () {
    config(['ai.providers.deepseek.key' => 'test-key']);
    config(['ai.providers.deepseek.driver' => 'openai']);

    StorefrontAssistant::fake(fn (string $prompt): string => 'Fake reply for: '.$prompt);

    $exit = Artisan::call('offerpilot:eval-assistant', ['--live' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('Final status: not run')
        ->and($output)->toContain('only the DeepSeek provider');

    StorefrontAssistant::assertNeverPrompted();
});

test('a live run with a judge model reports a diagnostic verdict', function () {
    config(['ai.providers.deepseek.key' => 'test-key']);

    StorefrontAssistant::fake(fn (string $prompt): string => 'Fake reply for: '.$prompt);
    AnonymousAgent::fake(fn (): string => 'PASS — the reply satisfies the goal.');

    $exit = Artisan::call('offerpilot:eval-assistant', ['--live' => true, '--judge' => 'deepseek-chat']);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('judge model: deepseek-chat')
        ->and($output)->toContain('judge: PASS');
});

test('invoking the command does not mutate the existing default database', function () {
    config(['ai.providers.deepseek.key' => 'test-key']);
    StorefrontAssistant::fake(fn (string $prompt): string => 'Fake reply for: '.$prompt);

    [$merchant, $product] = evalBaselineShop();
    $before = [$merchant->id, $product->id];

    Artisan::call('offerpilot:eval-assistant', ['--live' => true]);

    expect(Merchant::query()->count())->toBe(1)
        ->and(Product::query()->count())->toBe(1)
        ->and(Merchant::query()->pluck('id')->all())->toBe([$before[0]])
        ->and(Product::query()->pluck('id')->all())->toBe([$before[1]]);
});
