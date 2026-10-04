<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    protected $table = 'agent_sources';
    protected $guarded = ['id'];
    protected $hidden = ['credentials'];
    protected function casts(): array { return ['credentials' => 'encrypted', 'allowed_domains' => 'array', 'enabled' => 'boolean', 'last_synced_at' => 'datetime', 'next_sync_at' => 'datetime']; }
    public function documents() { return $this->hasMany(SourceDocument::class, 'source_id')->latest(); }
    public function runs() { return $this->hasMany(SourceRun::class, 'source_id')->latest(); }
    public function brand() { return $this->belongsTo(\App\Models\Brand::class); }
}
