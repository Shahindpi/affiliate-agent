<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = [

        'email',

        'name',

        'status',

        'subscribed_at',

        'unsubscribed_at',

    ];

    protected function casts(): array
    {
        return [

            'status' => 'boolean',

            'subscribed_at' => 'datetime',

            'unsubscribed_at' => 'datetime',

        ];
    }

    /**
     * Scope active subscribers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope unsubscribed.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', false);
    }
}