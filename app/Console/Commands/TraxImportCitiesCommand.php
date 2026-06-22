<?php

namespace App\Console\Commands;

use App\Domain\Shipping\Trax\TraxCityResolver;
use App\Models\Courier;
use App\Models\CourierAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TraxImportCitiesCommand extends Command
{
    protected $signature = 'trax:import-cities
        {path : JSON file — Trax /api/cities response or [{id,name},...]}
        {--env= : Override environment testing|live (otherwise account setting)}';

    protected $description = 'Seed Trax city cache from a JSON file (use when the server cannot reach the Trax API).';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! File::isFile($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $this->error('Invalid JSON.');

            return self::FAILURE;
        }

        $rows = isset($decoded['cities'])
            ? TraxCityResolver::parseCitiesResponse($decoded)
            : TraxCityResolver::parseCitiesResponse(['cities' => $decoded]);

        if ($rows === []) {
            $this->error('No cities parsed. Expected Trax API shape {"cities":[{"id":...,"name":"..."}]} or a JSON array of city objects.');

            return self::FAILURE;
        }

        $account = $this->resolveAccount();
        if ($account === null) {
            $this->error('No Trax courier account found.');

            return self::FAILURE;
        }

        $count = TraxCityResolver::seedCache($account, $rows);
        $this->info("Seeded {$count} cities into cache for account #{$account->id}.");

        return self::SUCCESS;
    }

    private function resolveAccount(): ?CourierAccount
    {
        $courier = Courier::query()->where('code', 'trax')->first();
        if ($courier === null) {
            return null;
        }

        $account = CourierAccount::query()
            ->where('courier_id', $courier->id)
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();

        $env = strtolower(trim((string) $this->option('env')));
        if (! in_array($env, ['testing', 'live'], true) || $account === null) {
            return $account;
        }

        $creds = $account->credentials ?? [];
        $creds['api_environment'] = $env;
        $account->credentials = $creds;

        return $account;
    }
}
