<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;

class BankPaymentProofPanelSourceTest extends TestCase
{
    public function test_blade_panel_preserves_approved_source_contract_without_demo_mocks(): void
    {
        $source = file_get_contents(resource_path('views/components/storefront/bank-payment-proof-panel.blade.php'));

        $this->assertStringContainsString('data-bank-payment-proof', $source);
        $this->assertStringContainsString("\$variant === 'compact'", $source);
        $this->assertStringContainsString('Upload Payment Proof', $source);
        $this->assertStringContainsString('Payment Proof Pending Verification', $source);
        $this->assertStringContainsString('Payment Proof Verification Required', $source);
        $this->assertStringContainsString('Payment Verified & Settled', $source);
        $this->assertStringContainsString('PDF, JPG, PNG', $source);
        $this->assertStringContainsString('Maximum 10 MB', $source);
        $this->assertStringContainsString('aria-live="polite"', $source);
        $this->assertStringNotContainsString('ORD-2026-69083', $source);
        $this->assertStringNotContainsString('showDemoStateBar', $source);
    }
}
