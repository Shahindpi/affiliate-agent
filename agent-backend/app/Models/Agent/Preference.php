<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class Preference extends Model
{
    protected $table = 'agent_preferences';
    protected $guarded = ['id'];
    protected function casts(): array { return ['enabled' => 'boolean']; }
}
