<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Guests must be redirected away from the dashboard.
     */
    public function test_the_dashboard_redirects_guests(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
