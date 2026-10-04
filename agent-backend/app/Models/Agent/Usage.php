<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class Usage extends Model
{
    protected $table = 'agent_usage';
    protected $guarded = ['id'];
    protected function casts(): array { return ['mock' => 'boolean']; }
    public const UPDATED_AT = null;
}
