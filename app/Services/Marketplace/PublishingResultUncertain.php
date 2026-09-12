<?php

namespace App\Services\Marketplace;

final class PublishingResultUncertain extends \RuntimeException
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
    }

    public function diagnostics(): array { return $this->safeDiagnostics; }
}
