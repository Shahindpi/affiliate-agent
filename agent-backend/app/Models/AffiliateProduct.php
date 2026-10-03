<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;

use App\Models\AffiliateNetwork;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\SeoMeta;

class AffiliateProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'brand_id',
        'affiliate_network_id',
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'website_url',
        'affiliate_url',
        'price',
        'currency',
        'commission_rate',
        'free_trial',
        'rating',
        'featured_image',
        'pros',
        'cons',
        'featured',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'rating' => 'decimal:1',

            'free_trial' => 'boolean',
            'featured' => 'boolean',
            'status' => 'boolean',

            'pros' => 'array',
            'cons' => 'array',
        ];
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AffiliateEvent::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function affiliateNetwork(): BelongsTo
    {
        return $this->belongsTo(
            AffiliateNetwork::class
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            Post::class,
            'post_product'
        )->withPivot([
            'sort_order',
            'is_primary',
        ]);
    }

    public function seoMeta(): MorphOne
    {
        return $this->morphOne(
            SeoMeta::class,
            'seoable'
        );
    }

}