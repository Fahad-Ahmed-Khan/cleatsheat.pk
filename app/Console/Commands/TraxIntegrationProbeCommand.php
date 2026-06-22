<?php

namespace App\Console\Commands;

use App\Domain\Shipping\Trax\TraxApiClient;
use App\Domain\Shipping\Trax\TraxCityResolver;
use App\Domain\Shipping\Trax\TraxTokenResolver;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\ShippingSetting;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

class TraxIntegrationProbeCommand extends Command
{
    protected $signature = 'trax:probe
        {step=settings : settings | connectivity | cities | pickup-addresses | track | rates}
        {tracking? : Tracking number (required for track)}
        {--token= : Override API key (otherwise default Trax courier account)}
        {--env= : Override environment testing|live (otherwise account setting)}';

    protected $description = 'Probe Trax (Sonic) integration: local settings, connectivity, cities, pickup addresses, tracking, and rate calculation.';

    public function handle(): int
    {
        $step = strtolower((string) $this->argument('step'));

        return match ($step) {
            'settings' => $this->runSettings(),
            'connectivity' => $this->runConnectivity(),
            'cities' => $this->runCities(),
            'pickup-addresses' => $this->runPickupAddresses(),
            'track' => $this->runTrack(),
            'rates' => $this->runRates(),
            default => $this->invalidStep($step),
        };
    }

    private function invalidStep(string $step): int
    {
        $this->error("Unknown step \"{$step}\". Use: settings, connectivity, cities, pickup-addresses, track, rates.");

        return self::FAILURE;
    }

    private function resolveAccount(): ?CourierAccount
    {
        $courier = Courier::query()->where('code', 'trax')->first();
        if ($courier === null) {
            return null;
        }

        return CourierAccount::query()
            ->where('courier_id', $courier->id)
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();
    }

    private function resolveToken(?CourierAccount $account): string
    {
        $fromOpt = trim((string) $this->option('token'));
        if ($fromOpt !== '') {
            return $fromOpt;
        }

        return TraxTokenResolver::forCourierAccount($account);
    }

    private function maskToken(string $token): string
    {
        $len = strlen($token);
        if ($len <= 8) {
            return $len === 0 ? '(empty)' : str_repeat('*', $len);
        }

        return substr($token, 0, 4).'…'.substr($token, -4);
    }

    private function applyEnvOverride(?CourierAccount $account): ?CourierAccount
    {
        $env = strtolower(trim((string) $this->option('env')));
        if (! in_array($env, ['testing', 'live'], true) || $account === null) {
            return $account;
        }

        $creds = $account->credentials ?? [];
        $creds['api_environment'] = $env;
        $account->credentials = $creds;

        return $account;
    }

    private function runSettings(): int
    {
        $sandbox = (bool) config('shipping.sandbox', true);
        $account = $this->applyEnvOverride($this->resolveAccount());
        $token = $this->resolveToken($account);

        $this->info('Trax (Sonic) — local configuration (no HTTP call)');
        $this->line('  shipping.sandbox: '.($sandbox ? 'true (adapter returns fake booking / stub tracking)' : 'false (live API)'));
        $this->line('  TRAX base URL (testing): '.(string) config('shipping.endpoints.trax.testing'));
        $this->line('  TRAX base URL (live):    '.(string) config('shipping.endpoints.trax.live'));

        if ($account !== null) {
            $base = TraxApiClient::resolvedBaseUrl($account);
            $creds = $account->credentials ?? [];
            $this->line('  account: #'.$account->id.' ('.$account->name.')');
            $this->line('  account.api_environment: '.(string) ($creds['api_environment'] ?? 'testing'));
            $this->line('  resolved base URL:       '.$base);
        } else {
            $this->warn('  No Trax courier account found. Run ShippingCourierSeeder or create the courier/account first.');
        }

        $settings = ShippingSetting::current();
        $this->line('  trax_pickup_address_id: '.($settings->trax_pickup_address_id ?: '(not set)'));
        $this->line('  trax_shipping_mode_id: '.(string) ($settings->trax_shipping_mode_id ?? 1));
        $this->line('  trax_charges_mode_id: '.(string) ($settings->trax_charges_mode_id ?? 4));
        $this->line('  trax_item_product_type_id: '.(string) ($settings->trax_item_product_type_id ?? 24));
        $this->line('  trax_delivery_type_id: '.(string) ($settings->trax_delivery_type_id ?? 1));

        $this->line('  api key: '.$this->maskToken($token));
        if ($token === '' && trim((string) $this->option('token')) === '') {
            $this->warn('  Save a key under Admin → Shipping → Trax “Primary account”, or pass --token=…');
        }

        $this->newLine();
        $this->comment('Next: php artisan trax:probe connectivity');

        return self::SUCCESS;
    }

    private function runConnectivity(): int
    {
        $account = $this->applyEnvOverride($this->resolveAccount());
        if ($account === null) {
            $this->error('No Trax courier account found.');

            return self::FAILURE;
        }

        $base = TraxApiClient::resolvedBaseUrl($account);
        $host = (string) parse_url($base, PHP_URL_HOST);
        if ($host === '') {
            $this->error('Could not parse host from base URL: '.$base);

            return self::FAILURE;
        }

        $this->info('Trax (Sonic) — connectivity (no API key required for DNS/TCP)');
        $this->line('  resolved base URL: '.$base);
        $this->line('  host: '.$host);

        $dnsStart = microtime(true);
        $ip = gethostbyname($host);
        $dnsMs = (int) round((microtime(true) - $dnsStart) * 1000);
        if ($ip === $host) {
            $this->error("  DNS: FAILED — could not resolve {$host}");
        } else {
            $this->line("  DNS: {$host} → {$ip} ({$dnsMs} ms)");
        }

        $tcpStart = microtime(true);
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen('ssl://'.$host, 443, $errno, $errstr, 8);
        $tcpMs = (int) round((microtime(true) - $tcpStart) * 1000);
        if ($socket === false) {
            $this->error("  TCP 443: FAILED after {$tcpMs} ms — [{$errno}] {$errstr}");
            $this->newLine();
            $this->warn('Outbound HTTPS to Trax appears blocked or unreachable from this server.');
            $this->comment('Contact Hostinger support or Trax — booking will fail until 443 to '.$host.' works.');

            return self::FAILURE;
        }

        fclose($socket);
        $this->line("  TCP 443: OK ({$tcpMs} ms)");

        $token = $this->resolveToken($account);
        if ($token === '') {
            $this->warn('  HTTP: skipped (no API key)');

            return self::SUCCESS;
        }

        $url = $base.'/api/cities';
        $this->line('  HTTP GET: '.$url.' (8s connect / 15s total)');
        $httpStart = microtime(true);

        try {
            $res = TraxApiClient::probeRequest($token)->get($url);
        } catch (ConnectionException $e) {
            $httpMs = (int) round((microtime(true) - $httpStart) * 1000);
            $this->error('  HTTP: connection failed after '.$httpMs.' ms — '.$e->getMessage());

            return self::FAILURE;
        }

        $httpMs = (int) round((microtime(true) - $httpStart) * 1000);
        $this->line('  HTTP: '.$res->status().' ('.$httpMs.' ms)');

        return $res->successful() ? self::SUCCESS : self::FAILURE;
    }

    private function runCities(): int
    {
        $account = $this->applyEnvOverride($this->resolveAccount());
        if ($account === null) {
            $this->error('No Trax courier account found.');

            return self::FAILURE;
        }

        $token = $this->resolveToken($account);
        if ($token === '') {
            $this->error('Missing API key (token).');

            return self::FAILURE;
        }

        $this->info('Trax (Sonic) — cities');
        $base = TraxApiClient::resolvedBaseUrl($account);
        $url = $base.'/api/cities';
        $this->line('  GET '.$url);
        $this->line('  (8s connect / 15s timeout — use trax:probe connectivity if this hangs)');

        $start = microtime(true);

        try {
            $res = TraxApiClient::probeRequest($token)->get($url);
        } catch (ConnectionException $e) {
            $elapsed = round(microtime(true) - $start, 1);
            $this->error("  Connection failed after {$elapsed}s: ".$e->getMessage());
            $this->newLine();
            $this->warn('Shipment booking will also fail until this server can reach '.$base);
            $this->comment('Diagnostics: php artisan trax:probe connectivity --env='.($account->credentials['api_environment'] ?? 'testing'));
            $this->comment('Workaround (cities only): fetch cities locally, upload JSON, run php artisan trax:import-cities /path/to/cities.json');

            return self::FAILURE;
        }

        $elapsed = round(microtime(true) - $start, 1);
        $this->line('  HTTP '.$res->status().' ('.$elapsed.'s) '.$url);
        if (! $res->successful()) {
            $this->line((string) $res->body());

            return self::FAILURE;
        }

        $rows = TraxCityResolver::parseCitiesResponse($res->json());
        TraxCityResolver::seedCache($account, $rows);
        $this->line('  parsed count: '.count($rows));
        foreach (array_slice($rows, 0, 25) as $r) {
            $this->line('  - '.$r['id'].' '.$r['name']);
        }
        if (count($rows) > 25) {
            $this->line('  ...');
        }

        return self::SUCCESS;
    }

    private function runPickupAddresses(): int
    {
        $account = $this->applyEnvOverride($this->resolveAccount());
        if ($account === null) {
            $this->error('No Trax courier account found.');

            return self::FAILURE;
        }

        $token = $this->resolveToken($account);
        if ($token === '') {
            $this->error('Missing API key (token).');

            return self::FAILURE;
        }

        $base = TraxApiClient::resolvedBaseUrl($account);
        $url = $base.'/api/pickup_addresses';

        $this->info('Trax (Sonic) — pickup addresses');
        $this->line('  GET '.$url);
        $start = microtime(true);

        try {
            $res = TraxApiClient::probeRequest($token)->get($url);
        } catch (ConnectionException $e) {
            $elapsed = round(microtime(true) - $start, 1);
            $this->error("  Connection failed after {$elapsed}s: ".$e->getMessage());

            return self::FAILURE;
        }

        $elapsed = round(microtime(true) - $start, 1);
        $this->line('  HTTP '.$res->status().' ('.$elapsed.'s) '.$url);
        if (! $res->successful()) {
            $this->error('HTTP '.$res->status().' from '.$url);
            $this->line((string) $res->body());

            return self::FAILURE;
        }

        $body = $res->json();
        if (! is_array($body) || ! isset($body['pickup_addresses']) || ! is_array($body['pickup_addresses'])) {
            $this->error('Unexpected response shape.');
            $this->line(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '(json encode failed)');

            return self::FAILURE;
        }

        foreach ($body['pickup_addresses'] as $r) {
            if (! is_array($r)) {
                continue;
            }
            $id = $r['id'] ?? null;
            $addr = trim((string) ($r['address'] ?? ''));
            $city = is_array($r['city'] ?? null) ? trim((string) (($r['city']['name'] ?? '') ?: '')) : '';
            $this->line('  - id='.$id.' city='.($city !== '' ? $city : '—').' address='.($addr !== '' ? $addr : '—'));
        }

        $this->newLine();
        $this->comment('Tip: copy the desired pickup address ID into Admin → Shipping → Trax defaults.');

        return self::SUCCESS;
    }

    private function runTrack(): int
    {
        $tracking = trim((string) $this->argument('tracking'));
        if ($tracking === '') {
            $this->error('Usage: php artisan trax:probe track 101101000405');

            return self::FAILURE;
        }

        $account = $this->applyEnvOverride($this->resolveAccount());
        if ($account === null) {
            $this->error('No Trax courier account found.');

            return self::FAILURE;
        }

        $token = $this->resolveToken($account);
        if ($token === '') {
            $this->error('Missing API key (token).');

            return self::FAILURE;
        }

        $base = TraxApiClient::resolvedBaseUrl($account);
        $url = $base.'/api/shipment/track';

        $res = TraxApiClient::request($token)->get($url, [
            'tracking_number' => $tracking,
            'type' => 0,
        ]);

        $this->line('HTTP '.$res->status().' '.$url);
        $body = $res->json();
        if (is_array($body)) {
            $this->line(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '(json encode failed)');
        } else {
            $this->line((string) $res->body());
        }

        return $res->successful() ? self::SUCCESS : self::FAILURE;
    }

    private function runRates(): int
    {
        $account = $this->applyEnvOverride($this->resolveAccount());
        if ($account === null) {
            $this->error('No Trax courier account found.');

            return self::FAILURE;
        }

        $token = $this->resolveToken($account);
        if ($token === '') {
            $this->error('Missing API key (token).');

            return self::FAILURE;
        }

        $settings = ShippingSetting::current();
        $origin = (int) ($settings->trax_pickup_address_id ?? 0);
        if ($origin <= 0) {
            $this->error('Set trax_pickup_address_id first (Admin → Shipping → Trax defaults).');

            return self::FAILURE;
        }

        $base = TraxApiClient::resolvedBaseUrl($account);
        $url = $base.'/api/charges_calculate';

        $payload = [
            'service_type_id' => 1,
            'origin_city_id' => 202,
            'destination_city_id' => 202,
            'estimated_weight' => 1.0,
            'shipping_mode_id' => (int) ($settings->trax_shipping_mode_id ?? 1),
            'amount' => 1000,
        ];

        $res = TraxApiClient::request($token)->post($url, $payload);
        $this->line('HTTP '.$res->status().' '.$url);
        $body = $res->json();
        if (is_array($body)) {
            $this->line(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '(json encode failed)');
        } else {
            $this->line((string) $res->body());
        }

        return $res->successful() ? self::SUCCESS : self::FAILURE;
    }
}

