<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    protected $table = 'agent_publications';
    protected $guarded = ['id'];
    protected function casts(): array { return ['scheduled_at' => 'datetime', 'published_at' => 'datetime', 'started_at' => 'datetime', 'response_metadata' => 'array']; }
    public function account() { return $this->belongsTo(SocialAccount::class, 'social_account_id'); }
    public function content() { return $this->belongsTo(Content::class, 'content_id'); }
}
