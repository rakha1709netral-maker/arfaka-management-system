<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_visiting_the_root_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirectToRoute('login');
    }
}
