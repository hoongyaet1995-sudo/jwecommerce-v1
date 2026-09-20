<?php

namespace App\Services\Marketplace;

/** A provider response that explicitly reports the requested write was rejected. */
final class PublishingProviderRejected extends \RuntimeException
{
    private array $safeDiagnostics=[];

    public function __construct(string $message, array $diagnostics=[])
    {
        parent::__construct($message);
        $status=$diagnostics['http_status']??null;
        if (is_int($status) && $status>=100 && $status<=599) $this->safeDiagnostics['http_status']=$status;
        $code=$diagnostics['provider_code']??null;
        if ((is_int($code)||is_string($code)) && preg_match('/\A[0-9]{1,6}\z/D',(string)$code)) $this->safeDiagnostics['provider_code']=(string)$code;
        $id=$diagnostics['request_id']??null;
        if (is_string($id) && preg_match('/\A[0-9a-fA-F]{16,64}\z/D',$id)) $this->safeDiagnostics['request_id']=$id;
        $providerMessage=$diagnostics['provider_message']??null;
        if (is_string($providerMessage) && strlen($providerMessage)<=500
            && preg_match('/\A[\x20-\x7E]+\z/D',$providerMessage)) {
            $this->safeDiagnostics['provider_message']=$providerMessage;
        }
        $providerDetail=$diagnostics['provider_detail']??null;
        if (is_string($providerDetail) && strlen($providerDetail)<=2000
            && preg_match('/\A[\x20-\x7E]+\z/D',$providerDetail)) {
            $this->safeDiagnostics['provider_detail']=$providerDetail;
        }
    }

    public function diagnostics(): array { return $this->safeDiagnostics; }
}
