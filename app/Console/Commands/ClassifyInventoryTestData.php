<?php
namespace App\Console\Commands;

use App\Models\User;
use App\Services\Inventory\TestDataClassificationService;
use Illuminate\Console\Command;

class ClassifyInventoryTestData extends Command
{
    protected $signature='inventory:classify-test {type : store, sku, warehouse, order or marketplace_order} {id} {--actor= : Administrator ID} {--reason= : Explicit test purpose}';
    protected $description='Record explicit test provenance without changing inventory or granting platform writes';

    public function handle(TestDataClassificationService $service):int
    {
        $actor=User::whereKey($this->option('actor'))->where('is_admin',true)->firstOrFail();
        $result=$service->markTest((string)$this->argument('type'),(int)$this->argument('id'),$actor,(string)$this->option('reason'));
        $this->line(json_encode($result,JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
