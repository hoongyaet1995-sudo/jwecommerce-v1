<?php

namespace App\Console\Commands;

use App\Services\Search\SearchExposureBaseline;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class RecordSearchBaseline extends Command
{
    protected $signature = 'search:baseline {file? : A single observed or blocked evidence JSON record}';

    protected $description = 'Read the internal AI exposure evidence ledger, or append one validated real observation';

    public function handle(SearchExposureBaseline $baseline): int
    {
        if ($path = $this->argument('file')) {
            if (! is_file($path) || filesize($path) > 200000) {
                $this->error('Provide a local evidence JSON file under 200KB.');

                return self::FAILURE;
            }
            try {
                $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($data) || array_is_list($data)) {
                    $this->error('Provide a single JSON observation object.');

                    return self::FAILURE;
                }
                $result = $baseline->record($data);
                $this->info('Recorded '.$result['id']);
            } catch (\JsonException|ValidationException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        } else {
            $this->line(json_encode($baseline->report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }

        return self::SUCCESS;
    }
}
