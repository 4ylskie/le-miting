<?php

use App\Models\MenuCategory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('recommendations match the selected category slug', function () {
    $this->seed(DatabaseSeeder::class);

    config()->set('ai.driver', 'mock');

    $response = $this->post(route('ai.recommendations.recommend'), [
        'category' => 'main-course',
    ]);

    $response->assertOk();

    $recommendations = $response->viewData('recommendations');

    expect($recommendations)->not->toBeEmpty();
    expect($recommendations->first()->category->slug)->toBe('main-course');
    expect($recommendations->first()->is_available)->toBeTrue();
});

test('recommendations still render when no preferences are given', function () {
    $this->seed(DatabaseSeeder::class);

    config()->set('ai.driver', 'mock');

    $this->post(route('ai.recommendations.recommend'), [])
        ->assertOk()
        ->assertViewHas('recommendations');
});

test('the landing page links to the Rasa AI page', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Get Rasa AI Recommendations')
        ->assertSee('href="'.route('ai.recommendations').'"', false);
});

test('the dedicated Rasa AI page renders the preference form', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get(route('ai.recommendations'))
        ->assertOk()
        ->assertSee('id="rasa-ai-form"', false)
        ->assertSee('value="'.MenuCategory::where('slug', 'main-course')->value('slug').'"', false);
});

test('the navigation links to Rasa AI', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get(route('ai.recommendations'))
        ->assertOk()
        ->assertSee('Rasa AI')
        ->assertSee('aria-current="page"', false);
});

test('the contact page no longer links to Rasa AI', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get(route('contact.create'))
        ->assertOk()
        ->assertDontSee('Get Rasa AI Recommendations');
});
