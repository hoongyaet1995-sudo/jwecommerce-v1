<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxClass;
use App\Services\StoreTaxClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaxClassApiController extends Controller
{
    public function __construct(private readonly StoreTaxClassService $taxClasses)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->document()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $taxClass = $this->taxClasses->create($data['name'], $data['ratePercent'], $data['isActive']);

        return response()->json(['data' => $this->document()], 201);
    }

    public function update(Request $request, TaxClass $taxClass): JsonResponse
    {
        $data = $this->validated($request, $taxClass);
        $this->taxClasses->update($taxClass, $data['name'], $data['ratePercent'], $data['isActive']);

        return response()->json(['data' => $this->document()]);
    }

    public function activate(TaxClass $taxClass): JsonResponse
    {
        $this->taxClasses->activate($taxClass);

        return response()->json(['data' => $this->document()]);
    }

    public function deactivate(TaxClass $taxClass): JsonResponse
    {
        $this->taxClasses->deactivate($taxClass);

        return response()->json(['data' => $this->document()]);
    }

    public function destroy(TaxClass $taxClass): JsonResponse
    {
        $this->taxClasses->delete($taxClass);

        return response()->json(['data' => $this->document()]);
    }

    /** @return array{name: string, ratePercent: string, isActive: bool} */
    private function validated(Request $request, ?TaxClass $except = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ratePercent' => ['required', 'regex:/^\\d+(?:\\.\\d{1,4})?$/'],
            'isActive' => ['required', 'boolean'],
        ]);
        $name = trim($data['name']);
        $rate = (float) $data['ratePercent'];
        $duplicate = TaxClass::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->whereRaw('lower(name) = ?', [strtolower($name)])
            ->exists();

        if ($name === '' || $duplicate || $rate < 0 || $rate > 100) {
            throw ValidationException::withMessages(array_filter([
                'name' => ($name === '' || $duplicate) ? ['A Tax Class with this name already exists.'] : null,
                'ratePercent' => ($rate < 0 || $rate > 100) ? ['Tax Rate must be between 0 and 100 and use no more than four decimal places.'] : null,
            ]));
        }

        return ['name' => $name, 'ratePercent' => (string) $data['ratePercent'], 'isActive' => (bool) $data['isActive']];
    }

    /** @return array{classes: list<array<string, mixed>>, activeTaxClassId: ?string} */
    private function document(): array
    {
        $classes = TaxClass::query()->orderBy('id')->get();

        return [
            'classes' => $classes->map(fn (TaxClass $taxClass): array => [
                'id' => (string) $taxClass->id,
                'name' => $taxClass->name,
                'ratePercent' => (float) $taxClass->rate_percent,
                'isActive' => $taxClass->is_active,
                'createdAt' => $taxClass->created_at?->toIso8601String(),
                'updatedAt' => $taxClass->updated_at?->toIso8601String(),
            ])->values()->all(),
            'activeTaxClassId' => $classes->firstWhere('active_slot', 1)?->id ? (string) $classes->firstWhere('active_slot', 1)->id : null,
        ];
    }
}
