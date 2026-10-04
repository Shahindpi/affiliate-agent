<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class SourceDocument extends Model
{
    protected $table = 'agent_source_documents';
    protected $guarded = ['id'];
    protected function casts(): array { return ['synced_at' => 'datetime', 'approved_at' => 'datetime']; }
    public function source() { return $this->belongsTo(Source::class, 'source_id'); }
}
