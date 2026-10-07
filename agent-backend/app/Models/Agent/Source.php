<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    protected $table = 'agent_sources';
    protected $guarded = ['id'];
    protected $hidden = ['credentials'];
    protected $appends = ['sync_is_stale'];
    protected function casts(): array { return ['credentials' => 'encrypted', 'allowed_domains' => 'array', 'enabled' => 'boolean', 'last_synced_at' => 'datetime', 'next_sync_at' => 'datetime', 'last_tested_at' => 'datetime', 'sync_started_at' => 'datetime', 'last_sync_failed_at' => 'datetime']; }
    public function getSyncIsStaleAttribute(): bool { return $this->status === 'SYNCING' && $this->sync_started_at?->lt(now()->subMinutes(3)) === true; }
    public function documents() { return $this->hasMany(SourceDocument::class, 'source_id')->latest(); }
    public function runs() { return $this->hasMany(SourceRun::class, 'source_id')->orderByDesc('id'); }
    public function brand() { return $this->belongsTo(\App\Models\Brand::class); }
}
