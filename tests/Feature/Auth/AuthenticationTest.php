<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can login with valid credentials', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(200);
    $response->assertExactJson(['two_factor' => false]);
    $this->assertAuthenticatedAs($user);
});

test('login fails with invalid credentials', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
    $this->assertGuest();
});

test('an authenticated user can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/logout');

    $response->assertStatus(204);
    $this->assertGuest();
});

test('repeated failed login attempts are throttled', function () {
    $email = 'throttled@example.com';

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $response = $this->postJson('/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    $response = $this->postJson('/login', [
        'email' => $email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(429);
});

test('GET /login is served by the spa shell, not a fortify view', function () {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertViewIs('app');
});

test('GET /register is served by the spa shell, not a fortify view', function () {
    $response = $this->get('/register');

    $response->assertOk();
    $response->assertViewIs('app');
});
