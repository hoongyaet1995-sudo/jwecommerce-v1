<?php
namespace Tests\Feature;
use App\Services\Marketplace\LazadaLabelPdf;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LazadaLabelPdfTest extends TestCase
{
    public function test_pdf_is_verified_and_html_is_rejected():void
    {
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::sequence()->push("%PDF-1.7\nfixture\n%%EOF")->push('<html>Login</html>')]);
        $doc=['doc_type'=>'PDF','pdf_url'=>'https://sellercenter.lazada.com.my/oss/galaxy/proxy/logistics-waybill-oss-sg.oss-ap-southeast-1.aliyuncs.com/PDF/abc?Signature=private'];
        $this->assertStringStartsWith('%PDF-',app(LazadaLabelPdf::class)->download($doc));
        $this->expectException(\RuntimeException::class);
        app(LazadaLabelPdf::class)->download($doc);
    }
    public function test_foreign_url_never_receives_a_request():void
    {
        Http::preventStrayRequests();
        try { app(LazadaLabelPdf::class)->download(['doc_type'=>'PDF','pdf_url'=>'https://example.com/a.pdf']); $this->fail('Accepted'); }
        catch (\RuntimeException $error) { $this->assertSame('Lazada PDF location is not supported.',$error->getMessage()); }
        Http::assertNothingSent();
    }
}
