<?php

namespace App\Console\Commands;

use App\Domain\Bargain\OpenAiBargainPolisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BargainTestPolisherCommand extends Command
{
    protected $signature = 'bargain:test-polisher';

    protected $description = 'Check bargain AI config and run one sample rewrite (see logs if it fails)';

    public function handle(OpenAiBargainPolisher $polisher): int
    {
        $this->line('Config (runtime):');
        $this->line('  BARGAIN_AI enabled: '.(filter_var(config('bargain.ai.enabled'), FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no'));
        $key = (string) config('bargain.ai.api_key', '');
        if ($key === '') {
            $this->error('  API key: MISSING (set BARGAIN_AI_API_KEY or OPENAI_API_KEY, then php artisan config:clear)');

            return self::FAILURE;
        }
        $this->line('  API key: set (len='.strlen($key).', tail ...'.substr($key, -4).')');
        $this->line('  Model: '.config('bargain.ai.model'));
        $this->line('  Base URL: '.config('bargain.ai.base_url'));
        $this->line('  HTTP verify SSL: '.(config('bargain.ai.http_verify') ? 'yes' : 'no'));
        $this->newLine();

        $sample = "Assalam-o-alaikum — Our shelf price is PKR 12999.00. Tell me your offer in PKR.";

        $this->line('Input:');
        $this->line('  '.$sample);
        $this->newLine();

        $out = $polisher->polishEnglishShopkeeper($sample);

        if (trim($out) === trim($sample)) {
            $this->warn('Output is unchanged (same as draft template). Diagnosing OpenAI response…');
            $this->newLine();

            $baseUrl = rtrim((string) config('bargain.ai.base_url'), '/');
            $model = (string) config('bargain.ai.model', 'gpt-4o-mini');
            $verifySsl = filter_var(config('bargain.ai.http_verify', true), FILTER_VALIDATE_BOOLEAN);

            $probe = Http::timeout(30)->connectTimeout(15)->withToken($key)->acceptJson()->asJson();
            if (! $verifySsl) {
                $probe = $probe->withOptions(['verify' => false]);
            }

            $probeResponse = $probe->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => 'Reply with only: OK'],
                ],
                'max_tokens' => 5,
            ]);

            if (! $probeResponse->successful()) {
                $err = data_get($probeResponse->json(), 'error.message') ?? $probeResponse->body();
                $this->error('OpenAI returned HTTP '.$probeResponse->status());
                $this->line((string) $err);
                $this->newLine();
                $this->comment('Common fix: add billing / credits on platform.openai.com (429 insufficient_quota = no quota).');

                return self::FAILURE;
            }

            $this->warn('OpenAI accepted a probe request but the polisher still returned the draft — check storage/logs/laravel.log for bargain.ai_* entries.');

            return self::FAILURE;
        }

        $this->info('Polished (AI is working):');
        $this->line($out);

        return self::SUCCESS;
    }
}
