<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxZone extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'country', 'state', 'postcode', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(TaxRule::class);
    }
}
