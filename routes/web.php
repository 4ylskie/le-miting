<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\MenuCategoryController as AdminMenuCategoryController;
use App\Http\Controllers\Admin\MenuItemController as AdminMenuItemController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\TableController as AdminTableController;
use App\Http\Controllers\AiRecommendationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Menu
Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
Route::get('/menu/{menuItem}', [MenuController::class, 'show'])->name('menu.show');

// Rasa AI
Route::get('/ai/recommendations', [AiRecommendationController::class, 'index'])->name('ai.recommendations');
Route::post('/ai/recommendations', [AiRecommendationController::class, 'recommend'])->name('ai.recommendations.recommend');

// Cart
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{id}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{id}', [CartController::class, 'remove'])->name('cart.remove');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

// Checkout
Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

// Reservation
Route::get('/reserve', [ReservationController::class, 'create'])->name('reservation.create');
Route::get('/reserve/table', [ReservationController::class, 'table'])->name('reservation.table');
Route::get('/reserve/table-food', [ReservationController::class, 'tableFood'])->name('reservation.table-food');
Route::get('/reservation/availability', [ReservationController::class, 'availability'])->name('reservation.availability');
Route::post('/reservation', [ReservationController::class, 'store'])->name('reservation.store');
Route::get('/reservation/{reservation}/confirmation', [ReservationController::class, 'confirmation'])->name('reservation.confirmation');
Route::get('reservation/{reservation}/cancel', [ReservationController::class, 'cancelForm'])->middleware('signed')->name('reservation.cancel-form');
Route::post('reservation/{reservation}/cancel', [ReservationController::class, 'cancel'])->middleware('signed')->name('reservation.cancel');
Route::get('/reservation/{reservation}/receipt', [ReservationController::class, 'receipt'])->name('reservation.receipt');

// Payment (literal paths before {payment} wildcard)
Route::get('/payment/gateway/{payment}', [PaymentController::class, 'gateway'])->name('payment.gateway');
Route::get('/payment/return', [PaymentController::class, 'return'])->name('payment.return');
Route::post('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
Route::get('/payment/failed', [PaymentController::class, 'failed'])->name('payment.failed');
Route::get('/payment/{payment}', [PaymentController::class, 'create'])->name('payment.create');

// Order confirmation
Route::get('/orders/{order}/confirmation', [OrderController::class, 'confirmation'])->name('orders.confirmation');
Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');

// Feedback (signed links emailed to customers)
Route::middleware('signed')->group(function (): void {
    Route::get('/feedback/{token}', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('/feedback/{token}', [FeedbackController::class, 'store'])->name('feedback.store');
});

// Contact
Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

// Admin (guest-auth on the login screen, auth + ensure-admin on everything else)
Route::middleware('guest')->group(function (): void {
    Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('admin.login.submit');
});

Route::prefix('admin')->middleware(['auth', 'ensure-admin'])->group(function (): void {
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('admin.orders.show');
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('admin.orders.update-status');

    Route::get('/reservations', [AdminReservationController::class, 'index'])->name('admin.reservations.index');
    Route::get('/reservations/{reservation}', [AdminReservationController::class, 'show'])->name('admin.reservations.show');
    Route::patch('/reservations/{reservation}/status', [AdminReservationController::class, 'updateStatus'])->name('admin.reservations.update-status');
    Route::patch('/reservations/{reservation}/table', [AdminReservationController::class, 'updateTable'])->name('admin.reservations.update-table');

    Route::get('/tables', [AdminTableController::class, 'index'])->name('admin.tables.index');
    Route::get('/tables/create', [AdminTableController::class, 'create'])->name('admin.tables.create');
    Route::post('/tables', [AdminTableController::class, 'store'])->name('admin.tables.store');
    Route::get('/tables/{table}/edit', [AdminTableController::class, 'edit'])->name('admin.tables.edit');
    Route::patch('/tables/{table}', [AdminTableController::class, 'update'])->name('admin.tables.update');

    Route::get('/menu/categories', [AdminMenuCategoryController::class, 'index'])->name('admin.menu.categories.index');
    Route::get('/menu/categories/create', [AdminMenuCategoryController::class, 'create'])->name('admin.menu.categories.create');
    Route::post('/menu/categories', [AdminMenuCategoryController::class, 'store'])->name('admin.menu.categories.store');
    Route::get('/menu/categories/{category}/edit', [AdminMenuCategoryController::class, 'edit'])->name('admin.menu.categories.edit');
    Route::patch('/menu/categories/{category}', [AdminMenuCategoryController::class, 'update'])->name('admin.menu.categories.update');
    Route::delete('/menu/categories/{category}', [AdminMenuCategoryController::class, 'destroy'])->name('admin.menu.categories.destroy');

    Route::get('/menu/items', [AdminMenuItemController::class, 'index'])->name('admin.menu.items.index');
    Route::get('/menu/items/create', [AdminMenuItemController::class, 'create'])->name('admin.menu.items.create');
    Route::post('/menu/items', [AdminMenuItemController::class, 'store'])->name('admin.menu.items.store');
    Route::get('/menu/items/{menuItem}/edit', [AdminMenuItemController::class, 'edit'])->name('admin.menu.items.edit');
    Route::patch('/menu/items/{menuItem}', [AdminMenuItemController::class, 'update'])->name('admin.menu.items.update');
    Route::patch('/menu/items/{menuItem}/availability', [AdminMenuItemController::class, 'toggleAvailability'])->name('admin.menu.items.toggle-availability');

    Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('admin.feedback.index');
    Route::get('/feedback/{feedback}', [AdminFeedbackController::class, 'show'])->name('admin.feedback.show');

    Route::get('/reports', [AdminReportController::class, 'index'])->name('admin.reports.index');
    Route::get('/reports/sales/weekly', [AdminReportController::class, 'salesWeekly'])->name('admin.reports.sales-weekly');
    Route::get('/reports/sales/monthly', [AdminReportController::class, 'salesMonthly'])->name('admin.reports.sales-monthly');
    Route::get('/reports/reservations/weekly', [AdminReportController::class, 'reservationsWeekly'])->name('admin.reports.reservations-weekly');
    Route::get('/reports/reservations/monthly', [AdminReportController::class, 'reservationsMonthly'])->name('admin.reports.reservations-monthly');
    Route::get('/reports/feedback/weekly', [AdminReportController::class, 'feedbackWeekly'])->name('admin.reports.feedback-weekly');
    Route::get('/reports/feedback/monthly', [AdminReportController::class, 'feedbackMonthly'])->name('admin.reports.feedback-monthly');
});
