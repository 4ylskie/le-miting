<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $this->seed(DatabaseSeeder::class);

    $response = $this->get('/');

    $response->assertStatus(200);
});
