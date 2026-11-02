<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterAgeGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_profile_requires_age_and_collection_notice(): void
    {
        $response = $this->from(route('signup.register'))
            ->post(route('signup.register.profile'), [
                'work_email' => 'newuser@example.com',
                'first_name' => 'New',
                'last_name' => 'User',
                'mobile' => '9876543210',
                'new_password' => 'Password1',
                'whatsapp_addendum' => '1',
            ]);

        $response->assertRedirect(route('signup.register'));
        $response->assertSessionHasErrors(['age_confirmed', 'collection_notice_acknowledged']);
    }
}
