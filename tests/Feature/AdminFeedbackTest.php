<?php

use App\Models\Feedback;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an administrator can view feedback with average ratings', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Feedback::factory()->create(['food_rating' => 5, 'service_rating' => 4, 'experience_rating' => 4]);

    $this->actingAs($admin)
        ->get(route('admin.feedback.index'))
        ->assertOk()
        ->assertSee('Average food')
        ->assertSee('5.0');
});

test('an administrator can filter feedback by rating', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Feedback::factory()->create(['food_rating' => 5, 'comment' => 'Amazing food']);
    Feedback::factory()->create(['food_rating' => 2, 'comment' => 'Not great']);

    $this->actingAs($admin)
        ->get(route('admin.feedback.index', ['dimension' => 'food_rating', 'rating' => 5]))
        ->assertOk()
        ->assertSee('Amazing food')
        ->assertDontSee('Not great');
});

test('an administrator can view feedback details with the linked order', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create();
    $feedback = Feedback::factory()->create([
        'order_id' => $order->id,
        'comment' => 'Wonderful service',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.feedback.show', $feedback))
        ->assertOk()
        ->assertSee('Wonderful service')
        ->assertSee($order->order_number)
        ->assertSee('Linked order');
});
