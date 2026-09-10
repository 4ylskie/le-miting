<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a guest visiting the admin dashboard is redirected to the admin login', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});

test('the admin login page renders', function () {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('Admin Login')
        ->assertSee('Sign in');
});

test('an administrator can log in and is sent to the dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->post(route('admin.login.submit'), [
        'email' => $admin->email,
        'password' => 'password',
    ])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($admin);
});

test('invalid credentials are rejected', function () {
    User::factory()->create(['role' => 'admin']);

    $this->post(route('admin.login.submit'), [
        'email' => 'wrong@example.com',
        'password' => 'wrong-password',
    ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a non-admin user cannot log in to the admin panel', function () {
    User::factory()->create(['role' => 'customer']);

    $this->post(route('admin.login.submit'), [
        'email' => 'customer@example.com',
        'password' => 'password',
    ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an authenticated non-admin cannot access the admin panel', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('an administrator can log out', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.logout'))
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();
});
