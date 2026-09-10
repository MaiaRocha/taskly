<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can register with valid data and is authenticated afterwards', function () {
    $response = $this->postJson('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(201);
    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
});

test('registration fails with a duplicate email', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->postJson('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
    $this->assertGuest();
});

test('registration fails when the password confirmation does not match', function () {
    $response = $this->postJson('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'something-else',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('password');
    $this->assertGuest();
});

test('registration fails when required fields are missing', function () {
    $response = $this->postJson('/register', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'email', 'password']);
    $this->assertGuest();
});
