<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an unauthenticated request to /api/user is rejected', function () {
    $response = $this->getJson('/api/user');

    $response->assertStatus(401);
});

test('an authenticated request to /api/user returns only the expected fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertStatus(200);
    $response->assertExactJson([
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ],
    ]);
});
