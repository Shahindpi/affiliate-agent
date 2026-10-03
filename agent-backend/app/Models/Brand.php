<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'website',
        'logo',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function affiliateEvents(): HasMany
    {
        return $this->hasMany(AffiliateEvent::class);
    }

    public function affiliateProducts(): HasMany
    {
        return $this->hasMany(AffiliateProduct::class);
    }
}