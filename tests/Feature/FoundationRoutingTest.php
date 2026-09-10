<?php

test('the root route boots the application and serves the spa shell', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertViewIs('app');
});

test('an arbitrary frontend route serves the spa shell', function () {
    $response = $this->get('/some-arbitrary-frontend-route');

    $response->assertOk();
    $response->assertViewIs('app');
});

test('an unknown api route returns a json 404 instead of the spa shell', function () {
    $response = $this->getJson('/api/some-unknown-resource');

    $response->assertStatus(404);
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertJsonStructure(['message']);
    $response->assertDontSee('id="app"');
});
