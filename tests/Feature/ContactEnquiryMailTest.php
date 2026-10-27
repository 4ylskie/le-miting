<?php

use App\Mail\ContactEnquiryMail;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\Ai\AiRecommender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('contact submissions are emailed to the configured restaurant address', function () {
    Mail::fake();
    config(['restaurant.email' => 'restaurant@lemiting.com']);

    $this->post(route('contact.store'), [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'message' => 'Do you have halal options?',
    ])->assertRedirect(route('contact.create'));

    Mail::assertSent(ContactEnquiryMail::class, function (ContactEnquiryMail $mail) {
        return $mail->hasTo('restaurant@lemiting.com');
    });
});

test('a Rasa AI failure does not prevent a contact enquiry from being emailed', function () {
    Mail::fake();
    $category = MenuCategory::factory()->create(['slug' => 'main-course']);
    MenuItem::factory()->create(['menu_category_id' => $category->id, 'protein_type' => 'chicken']);

    $recommender = Mockery::mock(AiRecommender::class);
    $recommender->shouldReceive('recommend')->once()->andThrow(new RuntimeException('provider failed'));
    $this->app->instance(AiRecommender::class, $recommender);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'chicken'])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->post(route('contact.store'), [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'message' => 'Do you have halal options?',
    ])->assertRedirect(route('contact.create'));

    Mail::assertSent(ContactEnquiryMail::class);
});

test('a contact enquiry failure does not break Rasa AI', function () {
    $category = MenuCategory::factory()->create(['slug' => 'main-course']);
    $item = MenuItem::factory()->create(['menu_category_id' => $category->id, 'protein_type' => 'chicken']);

    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('mail service failed'));

    $this->post(route('contact.store'), [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'message' => 'Do you have halal options?',
    ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Sorry, we could not send your message. Please try again.');

    $recommender = Mockery::mock(AiRecommender::class);
    $recommender->shouldReceive('recommend')->once()->andReturn([$item->id]);
    $this->app->instance(AiRecommender::class, $recommender);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'chicken'])
        ->assertOk()
        ->assertSee($item->name);
});
