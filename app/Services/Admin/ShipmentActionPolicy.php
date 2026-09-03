<?php

namespace App\Services\Admin;

class ShipmentActionPolicy
{
    private const CAPABILITIES = [
        'fulfil',
        'resume',
        'printLabel',
        'viewTimeline',
        'correct',
        'amendAddress',
        'updateStatus',
        'refreshProvider',
        'copyTracking',
    ];

    /** @param array<string, mixed> $row @return array<string, mixed> */
    public function forRow(array $row): array
    {
        $source = (string) ($row['source'] ?? 'pending');
        $status = (string) ($row['status'] ?? 'pending');
        $isPendingOrder = $source === 'pending';
        $isProviderManaged = $source === 'easyparcel' || ($row['managementMode'] ?? null) === 'provider_managed';
        $isTeamManaged = ($row['managementMode'] ?? null) === 'team_managed';
        $hasLabel = (bool) ($row['hasLabel'] ?? false);
        $hasTracking = trim((string) ($row['trackingNumber'] ?? '')) !== '';
        $hasTimeline = $hasTracking || count($row['trackingEvents'] ?? []) > 0;
        $correctionReady = (bool) data_get($row, 'activeCorrection.readyToResume', false);
        $rebookingReady = (bool) data_get($row, 'easyParcelRecovery.readyToRebook', false);
        $canResume = $correctionReady || $rebookingReady;

        $lifecycleStage = $this->lifecycleStage($source, $status, $canResume);
        $labelState = $this->labelState($source, $status, $hasLabel);
        $recoveryState = $correctionReady
            ? 'replacement_required'
            : ($rebookingReady ? 'rebooking_ready' : ($lifecycleStage === 'action_required' ? 'review_required' : 'none'));

        $enabled = [
            'fulfil' => $isPendingOrder && ! $canResume,
            'resume' => $canResume,
            'printLabel' => $hasLabel && in_array($lifecycleStage, ['ready_to_ship', 'in_transit', 'delivered'], true),
            'viewTimeline' => ! $isPendingOrder && $hasTimeline,
            'correct' => ! $isPendingOrder && $lifecycleStage === 'ready_to_ship',
            'amendAddress' => $isPendingOrder || $lifecycleStage === 'ready_to_ship' || $canResume,
            'updateStatus' => $isTeamManaged && ($lifecycleStage === 'in_transit' || $status === 'failed_delivery'),
            'refreshProvider' => $isProviderManaged && ! in_array($status, ['delivered', 'returned', 'cancelled', 'booking_failed'], true),
            'copyTracking' => $hasTracking,
        ];

        return [
            'lifecycleStage' => $lifecycleStage,
            'labelState' => $labelState,
            'recoveryState' => $recoveryState,
            'primaryAction' => $this->primaryAction($lifecycleStage, $status, $enabled),
            'capabilities' => array_combine(
                self::CAPABILITIES,
                array_map(fn (string $capability): bool => $enabled[$capability], self::CAPABILITIES),
            ),
        ];
    }

    private function lifecycleStage(string $source, string $status, bool $canResume): string
    {
        if ($canResume || in_array($status, ['failed_delivery', 'returned', 'booking_failed', 'cancelled', 'on_hold', 'provider_update'], true)) {
            return 'action_required';
        }

        if ($status === 'delivered') {
            return 'delivered';
        }

        if (in_array($status, ['shipped', 'picked_up', 'in_transit', 'out_for_delivery'], true)) {
            return 'in_transit';
        }

        if ($status === 'ready_to_ship') {
            return 'ready_to_ship';
        }

        return 'pending_fulfilment';
    }

    private function labelState(string $source, string $status, bool $hasLabel): string
    {
        if ($hasLabel) {
            return 'available';
        }

        if ($source !== 'easyparcel') {
            return 'not_required';
        }

        if (in_array($status, ['delivered', 'returned', 'cancelled'], true)) {
            return 'not_required';
        }

        return $status === 'booking_failed' ? 'failed' : 'pending';
    }

    /** @param array<string, bool> $enabled */
    private function primaryAction(string $lifecycleStage, string $status, array $enabled): ?string
    {
        if ($enabled['resume']) {
            return 'resume';
        }

        if ($enabled['fulfil']) {
            return 'fulfil';
        }

        if ($lifecycleStage === 'ready_to_ship' && $enabled['printLabel']) {
            return 'printLabel';
        }

        if ($enabled['updateStatus']) {
            return 'updateStatus';
        }

        if ($lifecycleStage === 'action_required' && ! in_array($status, ['returned', 'delivered'], true)) {
            return 'reviewRecovery';
        }

        return null;
    }
}
