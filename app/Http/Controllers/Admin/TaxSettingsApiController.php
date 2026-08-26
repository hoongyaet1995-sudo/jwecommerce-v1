<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxClass;
use App\Models\TaxConfiguration;
use App\Models\TaxRule;
use App\Models\TaxZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaxSettingsApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->document()]);
    }

    public function updateConfiguration(Request $request): JsonResponse
    {
        $data = $request->validate([
            'isEnabled' => ['required', 'boolean'],
            'isPublished' => ['required', 'boolean'],
            'pricesIncludeTax' => ['required', 'boolean'],
            'addressBasis' => ['nullable', Rule::in(['shipping_address', 'billing_address', 'store_origin'])],
            'merchantComplianceMetadata' => ['nullable', 'array'],
        ]);

        $configuration = TaxConfiguration::query()->firstOrCreate([], ['singleton_key' => 1]);
        $configuration->update([
            'is_enabled' => $data['isEnabled'],
            'is_published' => $data['isPublished'],
            'prices_include_tax' => $data['pricesIncludeTax'],
            'address_basis' => $data['addressBasis'] ?? null,
            'merchant_compliance_metadata' => $data['merchantComplianceMetadata'] ?? null,
        ]);

        return response()->json(['data' => $this->document()]);
    }

    public function storeZone(Request $request): JsonResponse
    {
        $zone = TaxZone::query()->create($this->zoneData($request));

        return response()->json(['data' => $this->zone($zone)], 201);
    }

    public function updateZone(Request $request, TaxZone $taxZone): JsonResponse
    {
        $taxZone->update($this->zoneData($request, $taxZone));

        return response()->json(['data' => $this->zone($taxZone->fresh())]);
    }

    public function destroyZone(TaxZone $taxZone): JsonResponse
    {
        if ($taxZone->rules()->exists()) {
            throw ValidationException::withMessages(['taxZone' => ['A tax zone referenced by a tax rule cannot be deleted.']]);
        }
        $taxZone->delete();

        return response()->json([], 204);
    }

    public function storeClass(Request $request): JsonResponse
    {
        $payload = $this->classPayload($request);
        $class = DB::transaction(function () use ($payload): TaxClass {
            $class = TaxClass::query()->create($payload['attributes']);
            $this->persistManagedDefaultRule($class, $payload);

            return $class;
        });

        return response()->json(['data' => $this->taxClass($class)], 201);
    }

    public function updateClass(Request $request, TaxClass $taxClass): JsonResponse
    {
        $payload = $this->classPayload($request, $taxClass);
        DB::transaction(function () use ($taxClass, $payload): void {
            $taxClass->update($payload['attributes']);
            $this->persistManagedDefaultRule($taxClass, $payload);
        });

        return response()->json(['data' => $this->taxClass($taxClass->fresh())]);
    }

    public function destroyClass(TaxClass $taxClass): JsonResponse
    {
        if ($taxClass->rules()->exists() || $taxClass->products()->exists()) {
            throw ValidationException::withMessages(['taxClass' => ['A tax class referenced by a tax rule or product cannot be deleted.']]);
        }
        $taxClass->delete();

        return response()->json([], 204);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $rule = TaxRule::query()->create($this->ruleData($request));

        return response()->json(['data' => $this->rule($rule->load(['taxClass', 'taxZone']))], 201);
    }

    public function updateRule(Request $request, TaxRule $taxRule): JsonResponse
    {
        $taxRule->update($this->ruleData($request));

        return response()->json(['data' => $this->rule($taxRule->fresh(['taxClass', 'taxZone']))]);
    }

    public function destroyRule(TaxRule $taxRule): JsonResponse
    {
        if ($taxRule->orderTaxLines()->exists()) {
            throw ValidationException::withMessages(['taxRule' => ['A tax rule recorded on an order cannot be deleted. Disable it instead.']]);
        }
        $taxRule->delete();

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function zoneData(Request $request, ?TaxZone $zone = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'countryIds' => ['required', 'array', 'size:1'],
            'countryIds.0' => ['required', 'string', 'size:2', 'alpha'],
            'zoneIds' => ['present', 'array', 'max:1'],
            'zoneIds.0' => ['nullable', 'string', 'max:255'],
            'isActive' => ['required', 'boolean'],
        ]);
        $country = strtoupper($data['countryIds'][0]);
        $state = filled($data['zoneIds'][0] ?? null) ? trim((string) $data['zoneIds'][0]) : null;

        return [
            'code' => $this->uniqueCode('zone', $data['name'], $zone?->id),
            'name' => trim($data['name']), 'description' => $data['description'] ?? null,
            'country' => $country, 'state' => $state, 'postcode' => null, 'is_active' => $data['isActive'],
        ];
    }

    /** @return array{attributes: array<string, mixed>, defaultRate: float|null, geoZoneId: int|null} */
    private function classPayload(Request $request, ?TaxClass $class = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'defaultRate' => ['nullable', 'numeric', 'between:0,100'], 'isActive' => ['nullable', 'boolean'],
            'geoZoneId' => ['nullable', 'integer', Rule::exists('tax_zones', 'id')],
        ]);
        $defaultRate = $data['defaultRate'] ?? null;

        if ($defaultRate !== null && empty($data['geoZoneId'])) {
            throw ValidationException::withMessages([
                'geoZoneId' => ['Select a tax zone before setting a default tax rate.'],
            ]);
        }

        return [
            'attributes' => [
                'code' => $this->uniqueCode('class', $data['name'], $class?->id), 'name' => trim($data['name']),
                'description' => $data['description'] ?? null, 'is_active' => $data['isActive'] ?? true,
            ],
            'defaultRate' => $defaultRate === null ? null : (float) $defaultRate,
            'geoZoneId' => isset($data['geoZoneId']) ? (int) $data['geoZoneId'] : null,
        ];
    }

    /** @param array{attributes: array<string, mixed>, defaultRate: float|null, geoZoneId: int|null} $payload */
    private function persistManagedDefaultRule(TaxClass $class, array $payload): void
    {
        if ($payload['defaultRate'] === null) {
            return;
        }

        $managedRules = TaxRule::query()
            ->where('tax_class_id', $class->id)
            ->where('tax_type', 'class_default')
            ->lockForUpdate()
            ->get();

        if ($managedRules->count() > 1) {
            throw ValidationException::withMessages([
                'defaultRate' => ['This tax class has multiple managed default rate rules and must be repaired before it can be updated.'],
            ]);
        }

        $attributes = [
            'tax_zone_id' => $payload['geoZoneId'],
            'label' => 'Default rate: '.$class->name,
            'rate_percent' => $payload['defaultRate'],
            'applies_to_shipping' => false,
            'priority' => 0,
            'is_enabled' => true,
            'effective_from' => null,
            'effective_until' => null,
        ];

        if ($managedRules->isNotEmpty()) {
            $managedRules->first()->update($attributes);

            return;
        }

        $class->rules()->create($attributes + ['tax_type' => 'class_default']);
    }

    /** @return array<string, mixed> */
    private function ruleData(Request $request): array
    {
        $data = $request->validate([
            'taxClassId' => ['required', 'integer', Rule::exists('tax_classes', 'id')],
            'geoZoneId' => ['required', 'integer', Rule::exists('tax_zones', 'id')],
            'description' => ['required', 'string', 'max:255'], 'ratePercent' => ['required', 'numeric', 'between:0,100'],
            'priority' => ['required', 'integer', 'min:0', 'max:100000'], 'compound' => ['required', 'boolean'],
            'isEnabled' => ['required', 'boolean'], 'effectiveFrom' => ['nullable', 'date'],
            'effectiveUntil' => ['nullable', 'date', 'after_or_equal:effectiveFrom'],
        ]);
        return ['tax_class_id' => $data['taxClassId'], 'tax_zone_id' => $data['geoZoneId'], 'label' => trim($data['description']),
            'tax_type' => $data['compound'] ? 'compound' : 'standard', 'rate_percent' => $data['ratePercent'],
            'applies_to_shipping' => false, 'priority' => $data['priority'], 'is_enabled' => $data['isEnabled'],
            'effective_from' => $data['effectiveFrom'] ?? null, 'effective_until' => $data['effectiveUntil'] ?? null];
    }

    private function uniqueCode(string $prefix, string $name, ?int $ignoreId): string
    {
        $base = Str::limit(Str::slug($name), 100, '');
        $base = $base !== '' ? "$prefix-$base" : "$prefix-item";
        $model = $prefix === 'zone' ? TaxZone::class : TaxClass::class;
        $query = $model::query()->where('code', $base);
        if ($ignoreId !== null) $query->whereKeyNot($ignoreId);
        if (! $query->exists()) return $base;
        return "$base-".Str::lower(Str::random(6));
    }

    /** @return array<string, mixed> */
    private function document(): array
    {
        return [
            'configuration' => $this->configuration(TaxConfiguration::query()->first()),
            'zones' => TaxZone::query()->orderBy('id')->get()->map(fn (TaxZone $zone): array => $this->zone($zone))->all(),
            'classes' => TaxClass::query()->withCount('products')->orderBy('id')->get()->map(fn (TaxClass $class): array => $this->taxClass($class))->all(),
            'rules' => TaxRule::query()->with(['taxClass', 'taxZone'])->orderBy('priority')->orderBy('id')->get()->map(fn (TaxRule $rule): array => $this->rule($rule))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function configuration(?TaxConfiguration $configuration): array
    {
        return ['isEnabled' => $configuration?->is_enabled ?? false, 'isPublished' => $configuration?->is_published ?? false,
            'pricesIncludeTax' => $configuration?->prices_include_tax ?? false, 'addressBasis' => $configuration?->address_basis ?? 'shipping_address',
            'merchantComplianceMetadata' => $configuration?->merchant_compliance_metadata ?? []];
    }

    /** @return array<string, mixed> */
    private function zone(TaxZone $zone): array
    {
        return ['id' => (string) $zone->id, 'name' => $zone->name, 'description' => $zone->description ?? '',
            'countryIds' => [$zone->country], 'zoneIds' => $zone->state === null ? [] : [$zone->state], 'isActive' => $zone->is_active];
    }

    /** @return array<string, mixed> */
    private function taxClass(TaxClass $class): array
    {
        $defaultRate = $class->rules()->where('tax_type', 'class_default')->value('rate_percent');
        return ['id' => (string) $class->id, 'name' => $class->name, 'description' => $class->description ?? '',
            'defaultRate' => (float) ($defaultRate ?? 0), 'productCount' => $class->products_count ?? $class->products()->count()];
    }

    /** @return array<string, mixed> */
    private function rule(TaxRule $rule): array
    {
        return ['id' => (string) $rule->id, 'taxClassId' => (string) $rule->tax_class_id, 'taxClassName' => $rule->taxClass?->name ?? 'Deleted tax class',
            'geoZoneId' => (string) $rule->tax_zone_id, 'geoZoneName' => $rule->taxZone?->name ?? 'Deleted tax zone',
            'ratePercent' => (float) $rule->rate_percent, 'description' => $rule->label, 'priority' => $rule->priority,
            'compound' => $rule->tax_type === 'compound', 'isEnabled' => $rule->is_enabled,
            'effectiveFrom' => $rule->effective_from?->toIso8601String(), 'effectiveUntil' => $rule->effective_until?->toIso8601String()];
    }
}
