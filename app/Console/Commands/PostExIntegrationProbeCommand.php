<?php

namespace App\Console\Commands;

use App\Domain\Shipping\PostEx\PostExApiClient;
use App\Domain\Shipping\PostEx\PostExTokenResolver;
use App\Models\ShippingSetting;
use Illuminate\Console\Command;

class PostExIntegrationProbeCommand extends Command
{
    protected $signature = 'postex:probe
        {step=settings : settings | cities | addresses | track}
        {tracking? : Tracking number (required for track)}
        {--token= : Override API token (otherwise default PostEx courier account)}
        {--city= : Optional city filter for addresses}
        {--operational-city-type= : Optional query for cities endpoint}';

    protected $description = 'Probe PostEx integration: local settings, operational cities, merchant addresses, or track-order';

    public function handle(): int
    {
        $step = strtolower((string) $this->argument('step'));

        return match ($step) {
            'settings' => $this->runSettings(),
            'cities' => $this->runCities(),
            'addresses' => $this->runAddresses(),
            'track' => $this->runTrack(),
            default => $this->invalidStep($step),
        };
    }

    private function invalidStep(string $step): int
    {
        $this->error("Unknown step \"{$step}\". Use: settings, cities, addresses, track.");

        return self::FAILURE;
    }

    private function resolveToken(): string
    {
        $fromOpt = trim((string) $this->option('token'));
        if ($fromOpt !== '') {
            return $fromOpt;
        }

        return PostExTokenResolver::defaultActiveToken();
    }

    private function maskToken(string $token): string
    {
        $len = strlen($token);
        if ($len <= 8) {
            return $len === 0 ? '(empty)' : str_repeat('*', $len);
        }

        return substr($token, 0, 4).'…'.substr($token, -4);
    }

    private function runSettings(): int
    {
        $sandbox = (bool) config('shipping.sandbox', true);
        $base = (string) config('shipping.endpoints.postex');
        $resolved = PostExApiClient::resolvedBaseUrl();

        $this->info('PostEx — local configuration (no HTTP call)');
        $this->line('  shipping.sandbox: '.($sandbox ? 'true (adapter returns fake booking / stub tracking)' : 'false (live API)'));
        $this->line('  POSTEX base URL (configured): '.$base);
        if ($resolved !== rtrim(trim($base), '/')) {
            $this->line('  POSTEX base URL (resolved):   '.$resolved.'  ← used for API calls (path suffix stripped)');
        } else {
            $this->line('  POSTEX base URL (resolved):   '.$resolved);
        }

        $settings = ShippingSetting::current();
        $this->line('  postex_pickup_address_code: '.($settings->postex_pickup_address_code ?: '(not set)'));
        $this->line('  postex_store_address_code: '.($settings->postex_store_address_code ?: '(not set)'));

        $token = $this->resolveToken();
        $this->line('  default account token: '.$this->maskToken($token));
        if ($token === '' && trim((string) $this->option('token')) === '') {
            $this->warn('  Save a token under Admin → Shipping → PostEx “Primary account”, or pass --token=…');
        }

        $this->newLine();
        $this->comment('Next: php artisan postex:probe cities');

        return self::SUCCESS;
    }

    private function requireToken(): ?string
    {
        $token = $this->resolveToken();
        if ($token === '') {
            $this->error('No API token. Set it in shipping settings (PostEx account) or use --token=.');

            return null;
        }

        return $token;
    }

    private function runCities(): int
    {
        $token = $this->requireToken();
        if ($token === null) {
            return self::FAILURE;
        }

        $type = trim((string) $this->option('operational-city-type'));
        $typeArg = $type !== '' ? $type : null;

        $this->info('GET get-operational-city …');
        $client = PostExApiClient::fromConfig();
        $body = $client->getOperationalCities($token, $typeArg);
        $this->line(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $this->summarizePostExResponse($body);
    }

    private function runAddresses(): int
    {
        $token = $this->requireToken();
        if ($token === null) {
            return self::FAILURE;
        }

        $city = trim((string) $this->option('city'));
        $cityArg = $city !== '' ? $city : null;

        $this->info('GET get-merchant-address …');
        $client = PostExApiClient::fromConfig();
        $body = $client->getMerchantAddresses($token, $cityArg);
        $this->line(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $this->summarizePostExResponse($body);
    }

    private function runTrack(): int
    {
        $tracking = trim((string) $this->argument('tracking'));
        if ($tracking === '') {
            $this->error('Usage: php artisan postex:probe track CX-123456');

            return self::FAILURE;
        }

        $token = $this->requireToken();
        if ($token === null) {
            return self::FAILURE;
        }

        $this->info('GET track-order/'.$tracking.' …');
        $client = PostExApiClient::fromConfig();
        $body = $client->trackOrder($token, $tracking);
        $this->line(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $this->summarizePostExResponse($body);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function summarizePostExResponse(array $body): int
    {
        if (isset($body['status']) && is_numeric($body['status']) && (int) $body['status'] >= 400) {
            $path = (string) ($body['path'] ?? '');
            $this->warn('PostEx gateway returned HTTP '.(int) $body['status'].' (path: '.$path.'). Check POSTEX_API_BASE in .env — use host only, e.g. https://api.postex.pk');

            return self::FAILURE;
        }

        $code = (string) ($body['statusCode'] ?? '');
        $msg = (string) ($body['statusMessage'] ?? '');
        if ($code !== '' && $code !== '200') {
            $this->warn("PostEx statusCode={$code} {$msg}");

            return self::FAILURE;
        }

        $this->info('OK (statusCode empty or 200).');

        return self::SUCCESS;
    }
}
