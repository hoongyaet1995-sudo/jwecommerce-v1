<?php

namespace App\Services\Inventory\StoreManagement;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReviewService
{
    public function __construct(private readonly CountMutation $counts, private readonly TransferMutation $transfers,
        private readonly SyncOutbox $outbox, private readonly AccountResolver $accounts) {}

    /** @return array<string,mixed> */
    public function create(User $actor, string $kind, array $input): array
    {
        $this->authorize($actor);
        if (! in_array($kind, ['count', 'intake', 'correction', 'transfer'], true)) {
            throw ValidationException::withMessages(['kind' => 'Choose count, intake, correction, or transfer.']);
        }
        if ($kind === 'count' && empty($input['balance_id']) && ! empty($input['variant_id'])) {
            $resolved = $this->accounts->balance((int) $input['variant_id'], (string) ($input['channel_key'] ?? 'website'));
            $input['balance_id'] = $resolved['id'];
        }

        return DB::transaction(function () use ($actor, $kind, $input): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            if ($kind === 'transfer' && $configuration->operating_mode !== 'independent_stores') {
                throw ValidationException::withMessages(['kind' => 'Store transfers are available only in Independent Stock mode.']);
            }
            $balanceIds = $kind === 'transfer'
                ? [(int) $input['source_balance_id'], (int) $input['destination_balance_id']]
                : [(int) $input['balance_id']];
            sort($balanceIds);
            $identity = DB::table('inventory_balances')->find($balanceIds[0]);
            abort_unless($identity, 404);
            DB::table('product_variants')->where('id', $identity->product_variant_id)->lockForUpdate()->first();
            $locked = DB::table('inventory_balances')->whereIn('id', $balanceIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($locked->count() === count(array_unique($balanceIds)), 404);
            if ($kind === 'transfer') {
                $source = $locked[(int) $input['source_balance_id']];
                $destination = $locked[(int) $input['destination_balance_id']];
                $projection = $this->transfers->preview($source, $destination, $input);
                $versions = [(string) $source->id => (int) $source->version, (string) $destination->id => (int) $destination->version];
            } else {
                $balance = $locked[$balanceIds[0]];
                $projection = $this->counts->preview($kind, $balance, $input);
                $versions = [(string) $balance->id => (int) $balance->version];
            }
            $id = (string) Str::uuid();
            $review = [
                'id' => $id,
                'kind' => $kind,
                'actor_id' => $actor->id,
                'configuration_version' => (int) $configuration->version,
                'balance_versions' => $versions,
                'payload' => $input,
                'before' => $projection['before'],
                'after' => $projection['after'],
                'blockers' => [],
                'status' => 'pending',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ];
            DB::table('inventory_store_reviews')->insert([
                ...$review,
                'balance_versions' => json_encode($review['balance_versions'], JSON_THROW_ON_ERROR),
                'payload' => json_encode($review['payload'], JSON_THROW_ON_ERROR),
                'before' => json_encode($review['before'], JSON_THROW_ON_ERROR),
                'after' => json_encode($review['after'], JSON_THROW_ON_ERROR),
                'blockers' => json_encode([], JSON_THROW_ON_ERROR),
                'expires_at' => Carbon::parse($review['expires_at']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $review;
        }, 3);
    }

    /** @return array<string,mixed> */
    public function confirm(User $actor, string $reviewId, string $operationKey): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $reviewId, $operationKey): array {
            $configuration = DB::table('inventory_configuration')->where('id', 1)->lockForUpdate()->first();
            $byKey = DB::table('inventory_store_operations')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($byKey) {
                if ($byKey->review_id !== $reviewId) {
                    $this->conflict('operation_key_reused', 'This operation key belongs to a different reviewed input.', 'Create a new operation key.');
                }

                return json_decode($byKey->receipt, true, 512, JSON_THROW_ON_ERROR);
            }
            $review = DB::table('inventory_store_reviews')->where('id', $reviewId)->lockForUpdate()->first();
            abort_unless($review, 404);
            if ((int) $review->actor_id !== (int) $actor->id && ! $actor->is_super_admin) {
                abort(403);
            }
            $existing = DB::table('inventory_store_operations')->where('review_id', $reviewId)->lockForUpdate()->first();
            if ($existing) {
                $this->conflict('review_confirmed', 'This review was already confirmed with another operation key.', 'Use the saved operation receipt.');
            }
            if (Carbon::parse($review->expires_at)->lte(now())) {
                $this->conflict('review_expired', 'The review expired before confirmation.', 'Review current stock again.');
            }
            if ((int) $review->configuration_version !== (int) $configuration->version) {
                $this->conflict('configuration_changed', 'Inventory settings changed after review.', 'Review current stock again.');
            }
            $payload = json_decode($review->payload, true, 512, JSON_THROW_ON_ERROR);
            $versions = json_decode($review->balance_versions, true, 512, JSON_THROW_ON_ERROR);
            $balanceIds = $review->kind === 'transfer'
                ? [(int) $payload['source_balance_id'], (int) $payload['destination_balance_id']]
                : [(int) $payload['balance_id']];
            sort($balanceIds);
            $identity = DB::table('inventory_balances')->find($balanceIds[0]);
            abort_unless($identity, 404);
            DB::table('product_variants')->where('id', $identity->product_variant_id)->lockForUpdate()->first();
            $locked = DB::table('inventory_balances')->whereIn('id', $balanceIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($locked->count() === count(array_unique($balanceIds)), 404);
            foreach ($locked as $balance) {
                if ((int) $balance->version !== (int) ($versions[(string) $balance->id] ?? -1)) {
                    $this->conflict('balance_changed', 'Inventory changed after review.', 'Review current stock again.');
                }
            }
            if ($review->kind === 'transfer') {
                $source = $locked[(int) $payload['source_balance_id']];
                $destination = $locked[(int) $payload['destination_balance_id']];
                $projection = $this->transfers->preview($source, $destination, $payload);
            } else {
                $balance = $locked[$balanceIds[0]];
                $projection = $this->counts->preview($review->kind, $balance, $payload);
            }
            $saved = json_decode($review->after, true, 512, JSON_THROW_ON_ERROR);
            if ($projection['after'] !== $saved) {
                $this->conflict('balance_changed', 'Inventory inputs changed after review.', 'Review current stock again.');
            }
            $operationId = (string) Str::uuid();
            $result = $review->kind === 'transfer'
                ? $this->transfers->apply($source, $destination, $saved)
                : [$this->counts->apply($balance, $saved)];
            $receipt = ['operation_id' => $operationId, 'review_id' => $reviewId,
                'ledger_status' => 'applied', 'balances' => $result, 'external_stages' => []];
            DB::table('inventory_store_operations')->insert([
                'id' => $operationId, 'review_id' => $reviewId, 'operation_key' => $operationKey,
                'kind' => $review->kind, 'actor_id' => $actor->id, 'ledger_status' => 'applied',
                'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($review->kind === 'transfer') {
                $freshBalances = collect($result)->map(fn (array $item) => DB::table('inventory_balances')->find($item['id']))->all();
                $receipt['external_stages'] = $this->outbox->enqueueTransfer($operationId, $freshBalances[0], $freshBalances[1]);
                DB::table('inventory_store_operations')->where('id', $operationId)->update([
                    'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                ]);
            } else {
                $fresh = DB::table('inventory_balances')->find($result[0]['id']);
                $receipt['external_stages'] = $this->outbox->enqueueMutation($operationId, $fresh);
                DB::table('inventory_store_operations')->where('id', $operationId)->update([
                    'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                ]);
            }
            DB::table('inventory_store_reviews')->where('id', $reviewId)->update([
                'status' => 'confirmed', 'confirmed_at' => now(), 'updated_at' => now(),
            ]);

            return $receipt;
        }, 3);
    }

    private function authorize(User $actor): void
    {
        $allowed = $actor->exists && $actor->is_admin
            && ($actor->is_super_admin || $actor->permissions()->where('permission', 'inventory.edit')->exists());
        abort_unless($allowed, 403);
    }

    private function conflict(string $code, string $message, string $nextAction): never
    {
        throw new HttpResponseException(response()->json(['error' => [
            'code' => $code, 'message' => $message, 'field' => null,
            'variant_id' => null, 'store_id' => null, 'next_action' => $nextAction,
        ]], 409));
    }
}
