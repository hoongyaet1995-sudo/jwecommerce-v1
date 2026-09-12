<?php

namespace Tests\Feature;

use App\Services\Marketplace\MarketplaceProductDescription;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MarketplaceDescriptionTest extends TestCase
{
    public function test_lazada_html_keeps_content_without_active_markup(): void
    {
        $html=app(MarketplaceProductDescription::class)->render(['sourceDescriptionHtml'=>'<h3>Product</h3><p onclick="alert(1)">Hello <strong>world</strong></p><script>alert(1)</script><a href="javascript:alert(1)">link</a><img src="https://127.0.0.1/private"><img src="https://sg-test-11.slatic.net/p/a.png" onerror="alert(1)">']);
        $this->assertStringContainsString('<h3>Product</h3>',$html);
        $this->assertStringContainsString('<strong>world</strong>',$html);
        foreach(['script','onclick','onerror','javascript:','127.0.0.1'] as $unsafe)$this->assertStringNotContainsString($unsafe,$html);
        $this->assertStringContainsString('https://sg-test-11.slatic.net/p/a.png',$html);
    }

    public function test_normal_description_escapes_markup_and_preserves_line_breaks(): void
    {
        $this->assertSame("<p>&lt;script&gt;x&lt;/script&gt;<br />\nSecond &amp; line</p>",
            app(MarketplaceProductDescription::class)->render(['descriptionType'=>'normal', 'description'=>"<script>x</script>\nSecond & line"]));
        $this->assertNull(app(MarketplaceProductDescription::class)->render([]));
    }

    public function test_extended_description_preserves_text_image_sequence(): void
    {
        $this->assertSame('<p>First</p><img src="https://down-my.img.susercontent.com/file/photo" alt=""><p>Last</p>',
            app(MarketplaceProductDescription::class)->render(['descriptionType'=>'extended', 'description'=>'ignored',
                'descriptionFields'=>[['field_type'=>'text', 'text'=>'First'],
                    ['field_type'=>'image', 'image_info'=>['image_url'=>'https://down-my.img.susercontent.com/file/photo']],
                    ['field_type'=>'text', 'text'=>'Last']]]));
    }

    public function test_invalid_or_oversized_description_is_rejected_not_silently_dropped(): void
    {
        foreach ([['descriptionType'=>'unknown'], ['descriptionType'=>'extended'],
            ['descriptionType'=>'normal', 'description'=>['bad']],
            ['descriptionType'=>'normal', 'description'=>str_repeat('&', 11000)],
            ['descriptionType'=>'extended', 'descriptionFields'=>[['field_type'=>'video']]],
            ['descriptionType'=>'extended', 'descriptionFields'=>[['field_type'=>'image', 'image_info'=>['image_url'=>'https://127.0.0.1/private']]]]] as $data) {
            try {
                app(MarketplaceProductDescription::class)->render($data);
                $this->fail('Invalid description accepted.');
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
        }
    }
}
