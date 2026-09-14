<?php

namespace Tests\Unit;

use App\Services\ProductDescriptionHtml;
use PHPUnit\Framework\TestCase;

class ProductDescriptionHtmlTest extends TestCase
{
    public function test_preserves_copy_and_formatting_but_removes_executable_content(): void
    {
        $html = (new ProductDescriptionHtml)->clean('<p>Fit &amp; size<br><strong>Cotton</strong></p><ul><li>Black</li></ul><script>alert(1)</script><a href="javascript:alert(1)" onclick="bad()">Details</a>');
        $this->assertStringContainsString('<strong>Cotton</strong>', $html);
        $this->assertStringContainsString('<li>Black</li>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }
}
