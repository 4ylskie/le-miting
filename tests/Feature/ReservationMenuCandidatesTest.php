<?php

use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\ReservationMenuCandidates;
use App\Services\ReservationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('temporary preorder candidates preserve existing availability and active category rules', function () {
    $available = MenuItem::factory()->create();
    MenuItem::factory()->unavailable()->create();
    $deleted = MenuItem::factory()->create();
    $deleted->delete();
    $inactiveCategoryItem = MenuItem::factory()->create();
    $inactiveCategoryItem->category->update(['is_active' => false]);

    expect(app(ReservationMenuCandidates::class)->query()->pluck('id')->all())->toBe([$available->id]);
});

test('a candidate restriction reaches display validation review and locked creation without changing the normal menu', function () {
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $allowed = MenuItem::factory()->create(['name' => 'CANDIDATE-ALLOWED-FIXTURE', 'price' => 12.50]);
    $excluded = MenuItem::factory()->create(['name' => 'CANDIDATE-EXCLUDED-FIXTURE']);
    $this->app->instance(ReservationMenuCandidates::class, new class($excluded->id) extends ReservationMenuCandidates
    {
        public function __construct(private int $excludedId) {}

        public function query(): Builder
        {
            return parent::query()->whereKeyNot($this->excludedId);
        }
    });
    $data = [
        'reservation_type' => 'table_and_food', 'table_id' => $table->id,
        'reservation_date' => now()->addDay()->toDateString(), 'reservation_time' => '11:30',
        'guest_count' => 2, 'customer_name' => 'Candidate Guest',
        'customer_email' => 'candidate@example.com', 'customer_phone' => '0123456789',
        'food_items' => [['menu_item_id' => $excluded->id, 'quantity' => 2]],
    ];

    $this->get(route('reservation.availability', $data))
        ->assertSee($allowed->name)->assertDontSee($excluded->name);
    $this->get(route('menu.index'))->assertSee($allowed->name)->assertSee($excluded->name);
    $this->post(route('reservation.review'), $data)->assertSessionHasErrors('food_items.0.menu_item_id');
    $this->post(route('reservation.store'), $data)->assertSessionHasErrors('food_items.0.menu_item_id');
    expect(fn () => app(ReservationService::class)->createTableAndFood($data))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('reservations', 0);
    $this->assertDatabaseCount('payments', 0);

    $data['food_items'] = [['menu_item_id' => $allowed->id, 'quantity' => 2]];
    $this->post(route('reservation.review'), $data)->assertOk()->assertSee('Food total: RM 25.00');
    $this->post(route('reservation.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $reservation = Reservation::firstOrFail();
    expect($reservation->status)->toBe('pending')
        ->and($reservation->total)->toBe(25.0)
        ->and($reservation->reservationFoods->sole()->menu_item_id)->toBe($allowed->id);
});
