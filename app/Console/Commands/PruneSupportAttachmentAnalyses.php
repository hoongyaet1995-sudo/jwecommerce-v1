<?php
namespace App\Console\Commands;
use App\Models\SupportAttachmentAnalysis;
use Illuminate\Console\Command;
class PruneSupportAttachmentAnalyses extends Command
{
    protected $signature='support:prune-attachment-analyses';
    protected $description='Expire derived support attachment analysis without deleting customer files.';
    public function handle(): int
    {
        $count=SupportAttachmentAnalysis::where('expires_at','<=',now())->where('status','!=','expired')->update(['status'=>'expired','result'=>null]);
        $this->info('Expired analyses: '.$count);
        return self::SUCCESS;
    }
}
