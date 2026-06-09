<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MigratePublicDiskCommand extends Command
{
    protected $signature = 'storage:migrate-public-disk
                            {--source= : Local directory to scan (default: storage/app/public)}
                            {--dry-run : List files without uploading}';

    protected $description = 'Upload local public-disk files to the configured public disk (e.g. Backblaze B2)';

    public function handle(): int
    {
        $source = $this->option('source') ?? storage_path('app/public');

        if (! is_dir($source)) {
            $this->warn("Source directory does not exist: {$source}");

            return self::SUCCESS;
        }

        $driver = config('filesystems.disks.public.driver');
        $this->info("Public disk driver: {$driver}");
        $this->info("Scanning: {$source}");

        if ($driver === 'local') {
            $this->warn('PUBLIC_DISK_DRIVER is local — nothing to migrate. Set PUBLIC_DISK_DRIVER=s3 and B2 credentials first.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $dryRun = (bool) $this->option('dry-run');

        $uploaded = 0;
        $skipped = 0;
        $failed = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($source, '/\\')) + 1)), '/');

            if ($relative === '' || $relative === '.gitignore') {
                continue;
            }

            if ($disk->exists($relative)) {
                $skipped++;
                $this->line("  skip (exists): {$relative}");

                continue;
            }

            if ($dryRun) {
                $this->line("  would upload: {$relative}");
                $uploaded++;

                continue;
            }

            $stream = fopen($file->getPathname(), 'r');
            if ($stream === false) {
                $this->error("  failed to read: {$relative}");
                $failed++;

                continue;
            }

            try {
                $disk->put($relative, $stream);
                $uploaded++;
                $this->line("  uploaded: {$relative}");
            } catch (\Throwable $e) {
                $this->error("  failed: {$relative} — {$e->getMessage()}");
                $failed++;
            } finally {
                fclose($stream);
            }
        }

        $this->newLine();
        $this->info("Done. uploaded={$uploaded}, skipped={$skipped}, failed={$failed}".($dryRun ? ' (dry-run)' : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
