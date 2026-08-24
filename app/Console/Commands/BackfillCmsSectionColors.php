<?php

namespace App\Console\Commands;

use App\Domain\Cms\SectionColorDocumentBackfiller;
use App\Models\PageDocument;
use Illuminate\Console\Command;

final class BackfillCmsSectionColors extends Command
{
    protected $signature = 'cms:backfill-section-colors
        {--apply : Persist the reviewed section color defaults; without it the command only reports changes}';

    protected $description = 'Backfill canonical CMS section color defaults without overwriting existing operator values';

    public function handle(SectionColorDocumentBackfiller $backfiller): int
    {
        $apply = (bool) $this->option('apply');
        $documents = PageDocument::query()->orderBy('id')->get();
        $documentsToUpdate = 0;
        $documentsUpdated = 0;
        $sectionsNormalized = 0;

        foreach ($documents as $document) {
            $result = $backfiller->inspect($document);

            if (! $result['changed']) {
                continue;
            }

            $documentsToUpdate++;
            $sectionsNormalized += $result['sections_normalized'];

            if ($apply) {
                $applied = $backfiller->apply($document->getKey());

                if ($applied['changed']) {
                    $documentsUpdated++;
                }
            }
        }

        $this->line('mode='.($apply ? 'apply' : 'dry-run'));
        $this->line('documents_scanned='.$documents->count());
        $this->line('documents_to_update='.$documentsToUpdate);
        $this->line('documents_updated='.$documentsUpdated);
        $this->line('sections_normalized='.$sectionsNormalized);

        return self::SUCCESS;
    }
}
