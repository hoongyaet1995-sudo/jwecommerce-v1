<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxClass;
use App\Models\TaxConfiguration;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxRule;
use App\Models\TaxZone;
use App\Services\TaxQuoteService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaxSettingsApiController extends Controller
{
    public function retired(): JsonResponse
    {
        return response()->json([
            'message' => 'Tax zones, tax rules, and tax engine configuration are retired. Use Tax Classes.',
        ], 410);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->document()]);
    }

    public function preview(Request $request, TaxQuoteService $quotes): JsonResponse
    {
        $data = $request->validate([
            'taxClassId' => ['required', 'integer', Rule::exists('tax_classes', 'id')->where('is_active', true)],
            'basePrice' => ['required', 'numeric', 'min:0', 'max:1000000000', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'shippingAmount' => ['nullable', 'numeric', 'min:0', 'max:1000000000', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'state' => ['nullable', 'string', 'max:255'],
            'postalCode' => ['nullable', 'string', 'max:32'],
            'effectiveAt' => ['nullable', 'date'],
        ]);
        $basePrice = $this->moneyToCents($data['basePrice']);
        $shipping = $this->moneyToCents($data['shippingAmount'] ?? 0);
        $product = (new Product())->forceFill(['tax_class_id' => (int) $data['taxClassId']]);
        $variant = (new ProductVariant())->forceFill(['price' => $basePrice, 'sku' => 'tax-preview']);
        $variant->setRelation('product', $product);
        $quote = $quotes->quote(
            collect([['variant' => $variant, 'quantity' => 1]]),
            [
                'country' => strtoupper($data['country']),
                'state' => $data['state'] ?? '',
                'postal_code' => $data['postalCode'] ?? '',
            ],
            $shipping,
            0,
            filled($data['effectiveAt'] ?? null) ? CarbonImmutable::parse($data['effectiveAt']) : null,
        );

        return response()->json(['data' => [
            'basePriceCents' => $basePrice,
            'shippingAmountCents' => $shipping,
            'quote' => $quote,
        ]]);
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
        $unsupported = [];
        if ($data['pricesIncludeTax']) {
            $unsupported['pricesIncludeTax'] = ['Tax-inclusive catalog prices cannot be published until the storefront gross-price UI is installed.'];
        }
        if (($data['addressBasis'] ?? 'shipping_address') !== 'shipping_address') {
            $unsupported['addressBasis'] = ['Only the shipping address is currently available to the authoritative checkout tax quote.'];
        }
        if ($unsupported !== []) {
            throw ValidationException::withMessages($unsupported);
        }

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
        $class = TaxClass::query()->create($this->classData($request));

        return response()->json(['data' => $this->taxClass($class)], 201);
    }

    public function updateClass(Request $request, TaxClass $taxClass): JsonResponse
    {
        $taxClass->update($this->classData($request, $taxClass));

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
        $taxRule->update($this->ruleData($request, $taxRule));

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

    /** @return array<string, mixed> */
    private function classData(Request $request, ?TaxClass $class = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'defaultRate' => ['nullable', 'numeric', 'between:0,100'], 'isActive' => ['nullable', 'boolean'],
        ]);
        return ['code' => $this->uniqueCode('class', $data['name'], $class?->id), 'name' => trim($data['name']),
            'description' => $data['description'] ?? null, 'is_active' => $data['isActive'] ?? true];
    }

    /** @return array<string, mixed> */
    private function ruleData(Request $request, ?TaxRule $currentRule = null): array
    {
        $data = $request->validate([
            'taxClassId' => ['required', 'integer', Rule::exists('tax_classes', 'id')],
            'geoZoneId' => ['required', 'integer', Rule::exists('tax_zones', 'id')],
            'description' => ['required', 'string', 'max:255'], 'ratePercent' => ['required', 'numeric', 'between:0,100'],
            'priority' => ['required', 'integer', 'min:0', 'max:100000'], 'compound' => ['required', 'boolean'],
            'isEnabled' => ['required', 'boolean'], 'effectiveFrom' => ['nullable', 'date'],
            'effectiveUntil' => ['nullable', 'date', 'after_or_equal:effectiveFrom'],
        ]);
        $this->ensureNoRuleConflict($data, $currentRule);

        return ['tax_class_id' => $data['taxClassId'], 'tax_zone_id' => $data['geoZoneId'], 'label' => trim($data['description']),
            'tax_type' => $data['compound'] ? 'compound' : 'standard', 'rate_percent' => $data['ratePercent'],
            'applies_to_shipping' => false, 'priority' => $data['priority'], 'is_enabled' => $data['isEnabled'],
            'effective_from' => $data['effectiveFrom'] ?? null, 'effective_until' => $data['effectiveUntil'] ?? null];
    }

    /** @param array<string, mixed> $data */
    private function ensureNoRuleConflict(array $data, ?TaxRule $currentRule): void
    {
        if (! $data['isEnabled']) {
            return;
        }

        $start = filled($data['effectiveFrom'] ?? null) ? CarbonImmutable::parse($data['effectiveFrom']) : null;
        $end = filled($data['effectiveUntil'] ?? null) ? CarbonImmutable::parse($data['effectiveUntil']) : null;
        $conflict = TaxRule::query()
            ->where('tax_class_id', $data['taxClassId'])
            ->where('tax_zone_id', $data['geoZoneId'])
            ->where('priority', $data['priority'])
            ->where('is_enabled', true)
            ->when($currentRule, fn ($query) => $query->whereKeyNot($currentRule->id))
            ->get()
            ->contains(fn (TaxRule $rule): bool => $this->periodsOverlap(
                $start,
                $end,
                $rule->effective_from,
                $rule->effective_until,
            ));

        if ($conflict) {
            throw ValidationException::withMessages([
                'priority' => ['An enabled rule with this class, zone, and priority already overlaps the selected effective period.'],
            ]);
        }
    }

    private function periodsOverlap(?CarbonInterface $leftStart, ?CarbonInterface $leftEnd, ?CarbonInterface $rightStart, ?CarbonInterface $rightEnd): bool
    {
        return ($leftEnd === null || $rightStart === null || $rightStart->lessThanOrEqualTo($leftEnd))
            && ($rightEnd === null || $leftStart === null || $leftStart->lessThanOrEqualTo($rightEnd));
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

    private function moneyToCents(mixed $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
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
        return ['id' => (string) $class->id, 'name' => $class->name, 'description' => $class->description ?? '',
            'defaultRate' => 0, 'productCount' => $class->products_count ?? $class->products()->count()];
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
