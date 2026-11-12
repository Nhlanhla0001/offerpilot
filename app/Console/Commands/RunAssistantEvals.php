<?php

namespace App\Console\Commands;

use App\Ai\Agents\StorefrontAssistant;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Attributes\Model as ModelAttribute;
use Laravel\Ai\Attributes\Provider as ProviderAttribute;
use Laravel\Ai\Enums\Lab;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Run the deterministic storefront assistant evaluation scenarios.
 *
 * The no-live path prints status and makes zero provider calls. Only the
 * explicit --live flag sends a real DeepSeek request, and it refuses to do so
 * unless it can prove it is running against an isolated in-memory SQLite
 * database with PayPal faked, so it can never touch the real database, a
 * logged-in demo cart, or a PayPal endpoint.
 */
class RunAssistantEvals extends Command
{
    /**
     * The version of the evaluation prompt/runner contract.
     */
    public const PROMPT_VERSION = '1.0';

    /**
     * The directory holding the versioned scenario fixtures.
     */
    private const FIXTURE_DIR = 'tests/Fixtures/assistant-evals';

    /**
     * The name of the dedicated, in-memory SQLite connection the runner uses.
     */
    private const ISOLATED_CONNECTION = 'offerpilot_eval_sqlite';

    protected $signature = 'offerpilot:eval-assistant
        {--live : Send real DeepSeek requests (opt-in; without it no provider call is made)}
        {--judge= : Optional judge model name for a diagnostic transcript review}';

    protected $description = 'Run the deterministic storefront assistant evaluation scenarios';

    private ?string $originalConnection = null;

    public function handle(): int
    {
        if (! (bool) $this->option('live')) {
            $this->printStatus();

            return self::SUCCESS;
        }

        if (! $this->hasDeepSeekCredentials()) {
            $this->reportNotRun('Missing DeepSeek credentials (ai.providers.deepseek.key).');

            return self::FAILURE;
        }

        if (! $this->guardOnlyDeepSeek()) {
            $this->reportNotRun('The runner could not guarantee that only the DeepSeek provider would be used.');

            return self::FAILURE;
        }

        try {
            $this->isolateDatabase();
            $this->fakePayPal();
        } catch (Throwable $exception) {
            $this->restoreDatabase();

            $this->reportNotRun('The runner could not guarantee an isolated test database.');

            return self::FAILURE;
        }

        try {
            $report = $this->runAll();

            $paypalRequests = Http::recorded(
                fn ($request): bool => str_contains($request->url(), 'paypal')
            );

            if ($paypalRequests->isNotEmpty()) {
                $this->error('Safety violation: a PayPal HTTP request was attempted during the eval run.');
                $report['passed'] = false;
            }
        } catch (Throwable) {
            $report = [
                'results' => [[
                    'name' => 'evaluation_runner',
                    'version' => 1,
                    'goal' => 'Every scenario completes with deterministic policy assertions.',
                    'prompt' => '[not available]',
                    'transcript' => '[evaluation runner failed before a transcript was available]',
                    'assertions' => ['scenario execution' => [
                        'passed' => false,
                        'message' => 'The evaluation could not complete safely.',
                    ]],
                    'judge' => 'not run',
                ]],
                'passed' => false,
            ];
        } finally {
            $this->restoreDatabase();
        }

        $this->renderReport($report);

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Print the dry-run status without touching a provider.
     */
    private function printStatus(): void
    {
        $this->newLine();
        $this->line('OfferPilot assistant evaluation — dry run');
        $this->line('  provider: '.($this->providerFor(StorefrontAssistant::class) ?? 'unknown'));
        $this->line('  model: '.($this->modelFor(StorefrontAssistant::class) ?? 'unknown'));
        $this->line('  judge model: '.($this->option('judge') ?: 'not configured'));
        $this->line('  prompt version: '.self::PROMPT_VERSION);
        $this->line('  fixtures:');

        foreach ($this->loadFixtures() as $fixture) {
            $this->line('    - '.$fixture['name'].' (v'.$fixture['version'].')');
        }

        $this->newLine();
        $this->line('Run with --live to send real DeepSeek requests. This dry run made no provider calls.');
    }

    /**
     * Print a not-run report with the reason, before any provider call.
     */
    private function reportNotRun(string $reason): void
    {
        $this->newLine();
        $this->line('OfferPilot assistant evaluation');
        $this->line('  provider: '.($this->providerFor(StorefrontAssistant::class) ?? 'unknown'));
        $this->line('  model: '.($this->modelFor(StorefrontAssistant::class) ?? 'unknown'));
        $this->line('  judge model: '.($this->option('judge') ?: 'not configured'));
        $this->line('  prompt version: '.self::PROMPT_VERSION);
        $this->newLine();
        $this->line('Final status: not run');
        $this->line('  reason: '.$reason);
    }

    /**
     * Run every scenario and return the report plus an overall pass flag.
     *
     * @return array{results: list<array<string, mixed>>, passed: bool}
     */
    private function runAll(): array
    {
        $results = [];

        foreach ($this->loadFixtures() as $fixture) {
            try {
                $results[] = $this->runScenario($fixture);
            } catch (Throwable) {
                $results[] = [
                    'name' => $fixture['name'],
                    'version' => $fixture['version'],
                    'goal' => $fixture['goal'],
                    'prompt' => '[scenario failed before prompt completion]',
                    'transcript' => '[scenario failed before a transcript was available]',
                    'assertions' => ['scenario execution' => [
                        'passed' => false,
                        'message' => 'The scenario raised an exception before deterministic assertions completed.',
                    ]],
                    'passed' => false,
                    'judge' => 'not run',
                ];
            }
        }

        return [
            'results' => $results,
            'passed' => collect($results)->every(fn (array $result): bool => (bool) $result['passed']),
        ];
    }

    /**
     * Run one scenario: seed, prompt, then assert deterministic tool/policy behaviour.
     *
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function runScenario(array $fixture): array
    {
        $context = $fixture['setup']();

        $prompt = $fixture['prompt'];
        $prompt = $prompt instanceof Closure ? $prompt($context) : $prompt;

        $agent = $fixture['agent']($context);

        $response = $agent->prompt($prompt);

        $transcript = (string) $response;
        $toolResults = $response->toolResults
            ->map(fn ($result): array => $result->toArray())
            ->all();

        $assertions = $this->runAssertions($fixture, $context, $transcript, $toolResults);

        return [
            'name' => $fixture['name'],
            'version' => $fixture['version'],
            'goal' => $fixture['goal'],
            'prompt' => $prompt,
            'transcript' => $transcript,
            'tool_results' => $toolResults,
            'assertions' => $assertions,
            'passed' => collect($assertions)->every(fn (array $assertion): bool => (bool) $assertion['passed']),
            'judge' => $this->judge($fixture, $transcript),
        ];
    }

    /**
     * Run the fixture's deterministic tool/policy assertion, capturing pass/fail.
     *
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $context
     * @param  list<array<string, mixed>>  $toolResults
     * @return array<string, array{passed: bool, message?: string}>
     */
    private function runAssertions(array $fixture, array $context, string $transcript, array $toolResults): array
    {
        $assert = function (bool $condition, string $message): void {
            if (! $condition) {
                throw new RuntimeException($message);
            }
        };

        try {
            $fixture['assert']($context, $transcript, $toolResults, $assert);

            return ['deterministic tool/policy assertions' => ['passed' => true]];
        } catch (Throwable) {
            return ['deterministic tool/policy assertions' => [
                'passed' => false,
                'message' => 'The deterministic policy assertion raised an exception (details withheld).',
            ]];
        }
    }

    /**
     * Ask the configured judge model for a diagnostic verdict, when one is configured.
     *
     * @param  array<string, mixed>  $fixture
     */
    private function judge(array $fixture, string $transcript): string
    {
        $model = $this->option('judge');

        if (! is_string($model) || $model === '') {
            return 'not configured';
        }

        $judge = new AnonymousAgent(
            "You are reviewing a shop assistant's reply for an evaluation. "
            ."The scenario goal is: {$fixture['goal']} "
            .'Reply with PASS or FAIL and one sentence of reasoning.',
            [],
            [],
        );

        try {
            $verdict = (string) $judge->prompt($transcript, [], Lab::DeepSeek, $model);

            if (preg_match('/^\s*(PASS|FAIL)\b/i', $verdict, $matches) === 1) {
                return strtoupper($matches[1]).' (diagnostic only)';
            }

            return 'unrecognized verdict (details withheld)';
        } catch (Throwable) {
            return 'judge error (details withheld)';
        }
    }

    /**
     * Print the full evaluation report.
     *
     * @param  array{results: list<array<string, mixed>>, passed: bool}  $report
     */
    private function renderReport(array $report): void
    {
        $this->newLine();
        $this->line('OfferPilot assistant evaluation');
        $this->line('  provider: '.($this->providerFor(StorefrontAssistant::class) ?? 'unknown'));
        $this->line('  model: '.($this->modelFor(StorefrontAssistant::class) ?? 'unknown'));
        $this->line('  judge model: '.($this->option('judge') ?: 'not configured'));
        $this->line('  prompt version: '.self::PROMPT_VERSION);

        foreach ($report['results'] as $result) {
            $this->newLine();
            $this->line("Scenario: {$result['name']} (fixture v{$result['version']})");
            $this->line('  goal: '.$result['goal']);
            $this->line('  prompt: '.$result['prompt']);
            $transcript = $report['passed'] && $result['passed']
                ? $result['transcript']
                : '[withheld because the evaluation did not pass its policy checks]';
            $this->line('  transcript: '.$transcript);

            foreach ($result['assertions'] as $name => $assertion) {
                $mark = $assertion['passed'] ? 'pass' : 'fail';
                $detail = $assertion['passed'] ? '' : ' — '.$assertion['message'];
                $this->line("    [{$mark}] {$name}{$detail}");
            }

            $this->line('  judge: '.$result['judge']);
        }

        $this->newLine();
        $this->line('Final status: '.($report['passed'] ? 'passed' : 'failed'));
    }

    /**
     * Load every fixture file in deterministic order.
     *
     * @return list<array<string, mixed>>
     */
    private function loadFixtures(): array
    {
        $files = glob(base_path(self::FIXTURE_DIR.'/*.php')) ?: [];

        sort($files);

        $fixtures = [];

        foreach ($files as $file) {
            $fixture = require $file;

            if (is_array($fixture)) {
                $fixtures[] = $fixture;
            }
        }

        return $fixtures;
    }

    /**
     * Determine whether a DeepSeek API key is configured.
     */
    private function hasDeepSeekCredentials(): bool
    {
        $key = config('ai.providers.deepseek.key');

        return is_string($key) && $key !== '';
    }

    /**
     * Fail closed unless the agent is locked to the DeepSeek provider.
     */
    private function guardOnlyDeepSeek(): bool
    {
        $provider = $this->providerFor(StorefrontAssistant::class);
        $driver = (string) config('ai.providers.deepseek.driver', 'deepseek');

        return ($provider === 'deepseek') && ($driver === 'deepseek');
    }

    /**
     * Point the default connection at a dedicated in-memory SQLite database and migrate it.
     */
    private function isolateDatabase(): void
    {
        $this->originalConnection = config('database.default');

        config(['database.connections.'.self::ISOLATED_CONNECTION => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        config(['database.default' => self::ISOLATED_CONNECTION]);

        DB::purge(self::ISOLATED_CONNECTION);

        $migrationStatus = $this->callSilent('migrate', [
            '--database' => self::ISOLATED_CONNECTION,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        if ($migrationStatus !== self::SUCCESS) {
            throw new RuntimeException('The isolated eval database migrations failed.');
        }
    }

    /**
     * Restore the original default connection and drop the isolated one.
     */
    private function restoreDatabase(): void
    {
        if ($this->originalConnection !== null) {
            config(['database.default' => $this->originalConnection]);
            $this->originalConnection = null;
        }

        DB::purge(self::ISOLATED_CONNECTION);
    }

    /**
     * Fake the PayPal hosts so no real PayPal endpoint can ever be reached.
     */
    private function fakePayPal(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/*' => Http::response(['error' => 'blocked by the eval runner'], 500),
            'api.paypal.com/*' => Http::response(['error' => 'blocked by the eval runner'], 500),
            '*.paypal.com/*' => Http::response(['error' => 'blocked by the eval runner'], 500),
        ]);
    }

    /**
     * Read the provider attribute from an agent class.
     */
    private function providerFor(string $agent): ?string
    {
        $attributes = (new ReflectionClass($agent))->getAttributes(ProviderAttribute::class);

        if ($attributes === []) {
            return null;
        }

        $value = $attributes[0]->newInstance()->value;

        return $value instanceof Lab ? $value->value : (string) $value;
    }

    /**
     * Read the model attribute from an agent class.
     */
    private function modelFor(string $agent): ?string
    {
        $attributes = (new ReflectionClass($agent))->getAttributes(ModelAttribute::class);

        if ($attributes === []) {
            return null;
        }

        return (string) $attributes[0]->newInstance()->value;
    }
}
