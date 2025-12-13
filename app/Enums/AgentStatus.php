<?php

declare(strict_types=1);

namespace App\Enums;

enum AgentStatus: string
{
    case Pending = 'pending';
    case Starting = 'starting';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
