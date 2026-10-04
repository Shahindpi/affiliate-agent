<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    protected $table = 'agent_publications';
    protected $guarded = ['id'];
    protected function casts(): array { return ['scheduled_at' => 'datetime', 'published_at' => 'datetime']; }
}
