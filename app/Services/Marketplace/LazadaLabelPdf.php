<?php
namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class LazadaLabelPdf
{
    /** Signed provider URLs and recipient-bearing bytes must remain private. */
    public function download(#[\SensitiveParameter] array $document): string
    {
        $url=$document['pdf_url']??null;
        $parts=is_string($url)?parse_url($url):false;
        if (($document['doc_type']??null)!=='PDF'||!is_array($parts)
            ||($parts['scheme']??null)!=='https'||($parts['host']??null)!=='sellercenter.lazada.com.my'
            ||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||isset($parts['fragment'])
            ||!preg_match('#\A/oss/(?:galaxy/)?proxy/(?:logistics-waybill-oss-sg|waybillprintbucket)\.oss-ap-southeast-1\.aliyuncs\.com/PDF/[A-Za-z0-9-]+\z#D',$parts['path']??'')) {
            throw new RuntimeException('Lazada PDF location is not supported.');
        }
        $stream=fopen('php://temp/maxmemory:2097152','w+');
        try {
            $response=Http::connectTimeout(10)->timeout(30)->withoutRedirecting()->withOptions([
                'sink'=>$stream,
                'progress'=>static function($total,$downloaded):void {
                    if ($total>10485760||$downloaded>10485760) throw new RuntimeException('PDF exceeds size limit.');
                },
            ])->get($url);
            rewind($stream);
            $bytes=stream_get_contents($stream,10485761);
            // Laravel HTTP fakes do not write the configured sink.
            if ($bytes==='') $bytes=$response->body();
            if (!$response->successful()||!is_string($bytes)||strlen($bytes)>10485760
                ||!str_starts_with($bytes,'%PDF-')||!str_contains(substr($bytes,-1024),'%%EOF')) {
                throw new RuntimeException('Invalid PDF response.');
            }
            return $bytes;
        } catch (\Throwable) {
            throw new RuntimeException('Lazada PDF download could not be verified. Request a fresh label.');
        } finally { fclose($stream); }
    }
}
