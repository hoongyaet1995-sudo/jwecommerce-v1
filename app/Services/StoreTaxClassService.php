<?php

namespace App\Services;

use App\Models\TaxClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreTaxClassService
{
    public function create(string $name, string $ratePercent, bool $isActive): TaxClass
    {
        return DB::transaction(function () use ($name, $ratePercent, $isActive): TaxClass {
            $taxClass = TaxClass::query()->create([
                'code' => $this->uniqueCode($name),
                'name' => $name,
                'rate_percent' => $ratePercent,
                'is_active' => false,
                'active_slot' => null,
            ]);

            return $isActive ? $this->activate($taxClass) : $taxClass;
        });
    }

    public function update(TaxClass $taxClass, string $name, string $ratePercent, bool $isActive): TaxClass
    {
        return DB::transaction(function () use ($taxClass, $name, $ratePercent, $isActive): TaxClass {
            $taxClass->update(['name' => $name, 'rate_percent' => $ratePercent]);

            return $isActive ? $this->activate($taxClass) : $taxClass->fresh();
        });
    }
    public function active(): ?TaxClass
    {
        return TaxClass::query()->where('active_slot', 1)->first();
    }

    public function activate(TaxClass $taxClass): TaxClass
    {
        return DB::transaction(function () use ($taxClass): TaxClass {
            TaxClass::query()->lockForUpdate()->get();

            TaxClass::query()
                ->where('is_active', true)
                ->update(['is_active' => false, 'active_slot' => null]);

            TaxClass::query()
                ->whereKey($taxClass)
                ->update(['is_active' => true, 'active_slot' => 1]);

            return $taxClass->fresh();
        });
    }

    public function deactivate(TaxClass $taxClass): TaxClass
    {
        return DB::transaction(function () use ($taxClass): TaxClass {
            TaxClass::query()->lockForUpdate()->get();

            TaxClass::query()
                ->whereKey($taxClass)
                ->update(['is_active' => false, 'active_slot' => null]);

            return $taxClass->fresh();
        });
    }

    public function delete(TaxClass $taxClass): void
    {
        DB::transaction(function () use ($taxClass): void {
            $lockedTaxClass = TaxClass::query()->lockForUpdate()->findOrFail($taxClass->getKey());

            if ($lockedTaxClass->is_active || $lockedTaxClass->active_slot === 1) {
                throw ValidationException::withMessages([
                    'tax_class' => ['Deactivate the active Tax Class before deleting it.'],
                ]);
            }

            $lockedTaxClass->delete();
        });
    }

    private function uniqueCode(string $name): string
    {
        $base = str($name)->slug()->limit(220, '')->toString() ?: 'tax-class';
        $code = $base;
        $suffix = 2;

        while (TaxClass::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }
}
