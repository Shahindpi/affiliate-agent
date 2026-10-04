<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class ContentVersion extends Model
{
    protected $table = 'agent_versions';
    protected $guarded = ['id'];
    protected function casts(): array { return ['snapshot' => 'array', 'artifacts' => 'array', 'changes' => 'array', 'steps' => 'array', 'qa' => 'array', 'references' => 'array', 'mock' => 'boolean']; }
    public const UPDATED_AT = null;
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Content versions are immutable.'));
        static::deleting(fn () => throw new \LogicException('Content history cannot be deleted.'));
    }
    public function content() { return $this->belongsTo(Content::class, 'content_id'); }
}
