<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
final class PrepareSocialPairing extends Command {
 protected $signature='social-hub:prepare-pairing';
 protected $description='Prepare a private one-time Social Hub deployment pairing code';
 public function handle():int {
  if(DB::table('social_hub_settings')->where('id',1)->exists()){$this->info('Already configured; installation preserved.');return 0;}
  $domain=strtolower((string)parse_url(config('app.url'),PHP_URL_HOST));
  if(!filter_var($domain,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)||(!app()->environment('local')&&parse_url(config('app.url'),PHP_URL_SCHEME)!=='https')){$this->error('Set a valid HTTPS APP_URL before pairing.');return 1;}
  $code=bin2hex(random_bytes(32));
  DB::transaction(function()use($domain,$code){DB::table('social_pairing_tokens')->whereNull('consumed_at')->delete();DB::table('social_pairing_tokens')->insert(['token_hash'=>hash('sha256',$code),'domain'=>$domain,'expires_at'=>now()->addHours(24)]);});
  $path=storage_path('app/private/social-hub-pairing.txt');File::ensureDirectoryExists(dirname($path),0700);File::put($path,$code);chmod($path,0600);
  $this->info('Pairing code prepared in private storage (expires in 24 hours). No credentials printed.');return 0;
 }
}
