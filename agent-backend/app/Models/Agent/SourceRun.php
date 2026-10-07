<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class SourceRun extends Model
{
    protected $table = 'agent_source_runs';
    protected $guarded = ['id'];
    protected function casts(): array { return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'content_changed' => 'boolean', 'metadata' => 'array']; }
}
