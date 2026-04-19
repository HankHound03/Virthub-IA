<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeWorkspaceStateTest extends TestCase
{
    public function test_home_state_requires_authenticated_registered_user(): void
    {
        $this->get('/home/state')->assertStatus(403);
        $this->post('/home/state', [
            'todos' => [],
            'notes' => '',
            'calendarEvents' => [],
        ])->assertStatus(403);
    }
}