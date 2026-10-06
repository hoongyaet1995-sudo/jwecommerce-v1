<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Social\WhatsAppAccountService;
use Illuminate\Console\Command;

class WhatsAppImportLegacy extends Command
{
    protected $signature='whatsapp:import-legacy {--actor= : Owning super-admin user ID}';
    protected $description='Verify and import the configured existing WhatsApp sender into encrypted storage';

    public function handle(WhatsAppAccountService $service): int
    {
        $actor=User::find($this->option('actor'));
        if(!$actor||!$actor->is_super_admin){$this->error('manager_required');return self::FAILURE;}
        try {
            $account=$service->importLegacy([
                'app_id'=>(string)config('services.whatsapp.app_id'),
                'waba_id'=>(string)config('services.whatsapp.waba_id'),
                'phone_number_id'=>(string)config('services.whatsapp.phone_number_id'),
                'access_token'=>(string)config('services.whatsapp.access_token'),
            ],$actor);
            app(\App\Services\Social\SocialHubOAuth::class)->call('import',['waba_id'=>$account->waba_id,'phone_number_id'=>$account->provider_account_id,'access_token'=>$account->access_token],'whatsapp');
            $this->info('Existing WhatsApp sender imported: account '.$account->id.'. Hub route registered; callback cutover and messaging verification remain required.');
            return self::SUCCESS;
        } catch(\RuntimeException $e) {
            $safe=['manager_required','configuration_required','account_identity_mismatch','authorization_expired','permission_required','account_owner_conflict','provider_unavailable'];
            $this->error(in_array($e->getMessage(),$safe,true)?$e->getMessage():'import_failed');return self::FAILURE;
        }
    }
}
