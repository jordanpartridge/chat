<?php

use App\Models\Agent;

it('can be created with a factory', function () {
    $agent = Agent::factory()->create();
    expect($agent)->toBeInstanceOf(Agent::class);
});
