<?php

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\ShipmentActionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShipmentActionPolicyTest extends TestCase
{
    #[DataProvider('rowProvider')]
    public function test_it_returns_the_canonical_lifecycle_label_and_action_capabilities(
        array $row,
        string $lifecycleStage,
        string $labelState,
        string $recoveryState,
        ?string $primaryAction,
        array $enabledCapabilities,
    ): void {
        $result = (new ShipmentActionPolicy)->forRow($row);

        self::assertSame($lifecycleStage, $result['lifecycleStage']);
        self::assertSame($labelState, $result['labelState']);
        self::assertSame($recoveryState, $result['recoveryState']);
        self::assertSame($primaryAction, $result['primaryAction']);

        foreach ($result['capabilities'] as $capability => $enabled) {
            self::assertSame(
                in_array($capability, $enabledCapabilities, true),
                $enabled,
                "Unexpected {$capability} capability.",
            );
        }
    }

    public static function rowProvider(): array
    {
        return [
            'first pending fulfilment' => [
                ['source' => 'pending', 'status' => 'pending', 'hasLabel' => false, 'trackingNumber' => '', 'trackingEvents' => []],
                'pending_fulfilment', 'not_required', 'none', 'fulfil',
                ['fulfil', 'amendAddress'],
            ],
            'replacement is action required rather than pending AWB' => [
                ['source' => 'pending', 'status' => 'pending', 'hasLabel' => false, 'trackingNumber' => '', 'trackingEvents' => [],
                    'activeCorrection' => ['status' => 'awaiting_replacement', 'readyToResume' => true]],
                'action_required', 'not_required', 'replacement_required', 'resume',
                ['resume', 'amendAddress'],
            ],
            'ready provider shipment can be printed corrected and inspected' => [
                ['source' => 'easyparcel', 'fulfillmentMethod' => 'easyparcel', 'managementMode' => 'provider_managed',
                    'status' => 'ready_to_ship', 'hasLabel' => true, 'trackingNumber' => 'EP-1001', 'trackingEvents' => []],
                'ready_to_ship', 'available', 'none', 'printLabel',
                ['printLabel', 'viewTimeline', 'correct', 'amendAddress', 'refreshProvider', 'copyTracking'],
            ],
            'team managed shipment in transit permits status updates but not correction or address edits' => [
                ['source' => 'manual', 'fulfillmentMethod' => 'external_courier', 'managementMode' => 'team_managed',
                    'status' => 'in_transit', 'hasLabel' => false, 'trackingNumber' => 'JNT-1001', 'trackingEvents' => [['status' => 'in_transit']]],
                'in_transit', 'not_required', 'none', 'updateStatus',
                ['viewTimeline', 'updateStatus', 'copyTracking'],
            ],
            'delivered manual shipment never waits for an AWB' => [
                ['source' => 'manual', 'fulfillmentMethod' => 'manual_delivery', 'managementMode' => 'team_managed',
                    'status' => 'delivered', 'hasLabel' => false, 'trackingNumber' => 'JW-MANUAL-14-1', 'trackingEvents' => [['status' => 'delivered']]],
                'delivered', 'not_required', 'none', null,
                ['viewTimeline', 'copyTracking'],
            ],
            'delivered provider shipment without a retained label is not pending an AWB' => [
                ['source' => 'easyparcel', 'fulfillmentMethod' => 'easyparcel', 'managementMode' => 'provider_managed',
                    'status' => 'delivered', 'hasLabel' => false, 'trackingNumber' => 'EP-DELIVERED', 'trackingEvents' => [['status' => 'delivered']]],
                'delivered', 'not_required', 'none', null,
                ['viewTimeline', 'copyTracking'],
            ],
            'cancelled provider attempt cannot print its voided label' => [
                ['source' => 'easyparcel', 'fulfillmentMethod' => 'easyparcel', 'managementMode' => 'provider_managed',
                    'status' => 'cancelled', 'hasLabel' => true, 'trackingNumber' => 'EP-CANCELLED', 'trackingEvents' => [['status' => 'cancelled']]],
                'action_required', 'available', 'review_required', 'reviewRecovery',
                ['viewTimeline', 'copyTracking'],
            ],
            'team managed failed delivery uses its legal recovery transition' => [
                ['source' => 'manual', 'fulfillmentMethod' => 'external_courier', 'managementMode' => 'team_managed',
                    'status' => 'failed_delivery', 'hasLabel' => false, 'trackingNumber' => 'JNT-FAILED', 'trackingEvents' => [['status' => 'failed_delivery']]],
                'action_required', 'not_required', 'review_required', 'updateStatus',
                ['viewTimeline', 'updateStatus', 'copyTracking'],
            ],
            'recoverable provider exception can resume and amend before rebooking' => [
                ['source' => 'easyparcel', 'fulfillmentMethod' => 'easyparcel', 'managementMode' => 'provider_managed',
                    'status' => 'booking_failed', 'hasLabel' => false, 'trackingNumber' => '', 'trackingEvents' => [],
                    'easyParcelRecovery' => ['readyToRebook' => true]],
                'action_required', 'failed', 'rebooking_ready', 'resume',
                ['resume', 'amendAddress'],
            ],
            'unresolved provider exception is review-only' => [
                ['source' => 'easyparcel', 'fulfillmentMethod' => 'easyparcel', 'managementMode' => 'provider_managed',
                    'status' => 'provider_update', 'hasLabel' => false, 'trackingNumber' => 'EP-REVIEW', 'trackingEvents' => []],
                'action_required', 'pending', 'review_required', 'reviewRecovery',
                ['viewTimeline', 'refreshProvider', 'copyTracking'],
            ],
        ];
    }
}
