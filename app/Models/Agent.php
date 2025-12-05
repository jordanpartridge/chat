<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    /** @use HasFactory<\Database\Factories\AgentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'user_id',
        'default_model_id',
        'system_prompt',
        'avatar',
        'tools',
        'capabilities',
        'is_active',
    ];
}
