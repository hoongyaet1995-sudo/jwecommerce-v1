<?php

namespace App\Console\Commands;

use App\Domain\Cms\Data\MigrationResult;
use App\Domain\Cms\LegacyPageDocumentMigrator;
use App\Domain\Cms\PageDocumentHasher;
use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class MigrateLegacyCmsDocuments extends Command
{
    protected $signature = 'cms:migrate-page-documents
        {--verify : Validate and compare the legacy source without writing}
        {--force : Recovery-only overwrite; forbidden in production}';

    protected $description = 'Compose legacy pages and sections into canonical CMS page documents';

    public function handle(LegacyPageDocumentMigrator $migrator): int
    {
        $verifyOnly = (bool) $this->option('verify');
        $force = (bool) $this->option('force');

        if ($verifyOnly && $force) {
            $this->error('The --verify and --force options cannot be combined.');

            return self::FAILURE;
        }

        if ($force && app()->environment('production')) {
            $this->error('The --force option is forbidden in production.');

            return self::FAILURE;
        }

        $pages = Page::query()->orderBy('id')->get();
        $preflight = $this->inspectPages($pages, $migrator);

        if ($preflight === null) {
            return self::FAILURE;
        }

        $this->writeSummary($verifyOnly ? 'verify' : 'preflight', $preflight);

        if (! $force && $preflight->contains(static fn (MigrationResult $result): bool => ! $result->verified)) {
            $this->error('Legacy CMS document verification failed; no migration writes were attempted.');

            return self::FAILURE;
        }

        if ($verifyOnly) {
            return self::SUCCESS;
        }

        $outcomes = collect();
        $failedPages = 0;
        $attemptedPages = 0;
        $failureMessage = null;

        foreach ($pages as $page) {
            $attemptedPages++;

            try {
                $outcomes->push($migrator->migratePage($page, $force));
            } catch (Throwable $exception) {
                $failedPages = 1;
                $failureMessage = "Page [{$page->getKey()}] migration failed: {$exception->getMessage()}";

                break;
            }
        }

        $this->writeMigrationSummary(
            $preflight,
            $outcomes,
            $attemptedPages,
            $failedPages,
        );

        if ($failureMessage !== null) {
            $this->error($failureMessage);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Page>  $pages
     * @return Collection<int, MigrationResult>|null
     */
    private function inspectPages(Collection $pages, LegacyPageDocumentMigrator $migrator): ?Collection
    {
        $results = collect();

        foreach ($pages as $page) {
            try {
                $result = $migrator->verifyPage($page);
                $results->push($result);

                foreach ($result->errors as $error) {
                    $this->error("Page [{$page->getKey()}]: {$error}");
                }
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $path => $messages) {
                    foreach ($messages as $message) {
                        $this->error("Page [{$page->getKey()}] {$path}: {$message}");
                    }
                }

                return null;
            } catch (Throwable $exception) {
                $this->error("Page [{$page->getKey()}] verification failed: {$exception->getMessage()}");

                return null;
            }
        }

        return $results;
    }

    /** @param Collection<int, MigrationResult> $results */
    private function writeSummary(string $mode, Collection $results): void
    {
        $snapshot = $results->map(static fn (MigrationResult $result): array => [
            'page_id' => $result->pageId,
            'slug' => $result->slug,
            'source_hash' => $result->sourceHash,
        ])->values()->all();
        $sourceSnapshotHash = PageDocumentHasher::hash(['pages' => $snapshot]);

        $this->line("mode={$mode}");
        $this->line('pages='.$results->count());
        $this->line('source_sections='.$results->sum('sourceSectionCount'));
        $this->line('key_losses='.$results->sum('keyLossCount'));
        $this->line('normalizations='.$results->sum(
            static fn (MigrationResult $result): int => count($result->normalizations),
        ));
        $this->line('draft_documents='.$results->where('draftExists', true)->count());
        $this->line('published_documents='.$results->where('publishedExists', true)->count());
        $this->line('canonical_verified_pages='.$results->where('canonicalVerified', true)->count());
        $this->line('migrated_pages='.$results->where('migrated', true)->count());
        $this->line('skipped_pages='.$results->where('skipped', true)->count());
        $this->line("source_snapshot_hash={$sourceSnapshotHash}");
    }

    /**
     * @param  Collection<int, MigrationResult>  $preflight
     * @param  Collection<int, MigrationResult>  $outcomes
     */
    private function writeMigrationSummary(
        Collection $preflight,
        Collection $outcomes,
        int $attemptedPages,
        int $failedPages,
    ): void {
        $snapshot = $preflight->map(static fn (MigrationResult $result): array => [
            'page_id' => $result->pageId,
            'slug' => $result->slug,
            'source_hash' => $result->sourceHash,
        ])->values()->all();
        $sourceSnapshotHash = PageDocumentHasher::hash(['pages' => $snapshot]);
        $migratedPages = $outcomes->where('migrated', true)->count();

        $this->line('mode=migrate');
        $this->line('pages='.$preflight->count());
        $this->line('source_sections='.$preflight->sum('sourceSectionCount'));
        $this->line('key_losses='.$outcomes->sum('keyLossCount'));
        $this->line('normalizations='.$preflight->sum(
            static fn (MigrationResult $result): int => count($result->normalizations),
        ));
        $this->line('draft_documents='.$outcomes->where('draftExists', true)->count());
        $this->line('published_documents='.$outcomes->where('publishedExists', true)->count());
        $this->line('canonical_verified_pages='.$outcomes->where('canonicalVerified', true)->count());
        $this->line("attempted_pages={$attemptedPages}");
        $this->line('completed_pages='.$outcomes->count());
        $this->line("migrated_pages={$migratedPages}");
        $this->line('skipped_pages='.$outcomes->where('skipped', true)->count());
        $this->line("failed_pages={$failedPages}");
        $this->line('unattempted_pages='.($preflight->count() - $attemptedPages));
        $this->line('partial_commit='.(($failedPages > 0 && $migratedPages > 0) ? 'true' : 'false'));
        $this->line("source_snapshot_hash={$sourceSnapshotHash}");
    }
}
