<?php

// Install as app/Jobs/ForwardMetaMessengerToEcommerce.php in the retained JW project.
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ForwardMetaMessengerToEcommerce implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries=12;
    public int $timeout=20;
    public string $encryptedBody;

    public function __construct(string $body,public string $signature)
    {
        $this->encryptedBody=Crypt::encryptString($body);
        $this->onConnection('database')->onQueue('meta-ecommerce-relay');
    }
    public function backoff(): array {return [10,30,60,120,300,600,900,1800,3600];}
    public function handle(): void
    {
        try {
            $response=Http::connectTimeout(5)->timeout(12)->withoutRedirecting()
                ->withHeaders(['X-Hub-Signature-256'=>$this->signature,'Accept'=>'application/json'])
                ->withBody(Crypt::decryptString($this->encryptedBody),'application/json')
                ->post('https://ecom.jwaistudio.online/webhooks/facebook/messenger');
            if(!$response->successful())throw new RuntimeException('Messenger relay was not acknowledged.');
        } catch(\Throwable $e){throw new RuntimeException('Messenger relay was not acknowledged.');}
    }
}
