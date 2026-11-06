<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Before /install has run, visitors end up at the installer (guests are
     * sent to the login page first, which then sends them to /install).
     */
    public function test_an_uninstalled_site_sends_visitors_to_the_installer(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/login')->assertRedirect(route('install.index'));
    }
}
