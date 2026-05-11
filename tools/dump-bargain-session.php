<?php

declare(strict_types=1);

use App\Models\BargainSession;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$id = (int) ($argv[1] ?? 0);
if ($id <= 0) {
    fwrite(STDERR, "Usage: php tools/dump-bargain-session.php <bargain_session_id>\n");
    exit(2);
}

/** @var BargainSession|null $session */
$session = BargainSession::query()->with('messages')->find($id);
if (! $session) {
    echo "NO_SESSION\n";
    exit(1);
}

echo "SESSION {$session->id} state={$session->state->value} list={$session->list_price} current={$session->current_offer} accepted={$session->accepted_price}\n";
foreach ($session->messages as $m) {
    $body = str_replace(["\r", "\n"], ' ', (string) $m->body);
    echo "{$m->id} [{$m->role}] {$body} META=".json_encode($m->meta)."\n";
}

