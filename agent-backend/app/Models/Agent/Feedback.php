<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'agent_feedback';
    protected $guarded = ['id'];
    protected $hidden = ['checkpoint'];
    protected function casts(): array { return ['checkpoint' => 'array', 'locks' => 'array', 'patch' => 'array']; }
}
