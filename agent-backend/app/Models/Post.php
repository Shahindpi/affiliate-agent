<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\PostType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Category;
use App\Models\User;
use App\Models\Tag;
use App\Models\AffiliateProduct;
use App\Models\SeoMeta;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'post_type',
        'status',
        'published_at',
        'views',
        'reading_time',
        'allow_comments',
    ];

    protected function casts(): array
    {
        return [
            'post_type' => PostType::class,
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'views' => 'integer',
            'reading_time' => 'integer',
            'allow_comments' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }

    public function affiliateProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            AffiliateProduct::class,
            'post_product'
        )->withPivot([
            'sort_order',
            'is_primary',
        ])->orderByPivot('sort_order');
    }

    public function seoMeta(): MorphOne
    {
        return $this->morphOne(
            SeoMeta::class,
            'seoable'
        );
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Approved comments.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)
            ->where('status', 'approved')
            ->whereNull('parent_id')
            ->with('replies')
            ->latest();
    }

    /**
     * All comments (Admin).
     */
    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

}