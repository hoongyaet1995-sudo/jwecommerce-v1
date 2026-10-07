<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class PruneSupportCustomerMemories extends Command {
    protected $signature='support:prune-customer-memories';protected $description='Remove expired derived customer memory content, preserving replay suppression';
    public function handle():int{\App\Models\SupportCustomerMemory::where('expires_at','<=',now())->whereNotNull('content')->update(['content'=>null]);$this->info('Expired customer memory content cleared.');return self::SUCCESS;}
}
