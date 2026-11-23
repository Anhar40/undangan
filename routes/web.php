<?php

use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InvitationController as AdminInvitationController;
use App\Http\Controllers\Admin\MusicController as AdminMusicController;
use App\Http\Controllers\Admin\TemplateController as AdminTemplateController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customer\InvitationController as CustomerInvitationController;
use App\Http\Controllers\Customer\PaymentController as CustomerPaymentController;
use App\Http\Controllers\Customer\TemplateController as CustomerTemplateController;
use App\Http\Controllers\Customer\TransactionController as CustomerTransactionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Payment\WebhookController;
use App\Http\Controllers\Public\GuestResponseController;
use App\Http\Controllers\Public\InvitationController as PublicInvitationController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\ShareController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [GoogleAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', LoginController::class)->middleware('throttle:6,1')->name('login.post');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});

Route::post('/logout', [GoogleAuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Sample previews contain dummy data only and are safe to expose publicly.
Route::get('/templates/{template}/sample', [CustomerTemplateController::class, 'sample'])
    ->name('templates.sample');

// Visitors may browse the catalog; choosing a template requires login.
// "Lihat Demo" goes straight to the rendered sample website (templates.sample).
Route::get('/templates', [CustomerTemplateController::class, 'index'])->name('templates.index');

// Midtrans posts this directly; signature verification happens in the controller.
Route::post('/payment/notification', WebhookController::class)
    ->middleware('throttle:60,1')
    ->name('payment.notification');

// Dedicated share helper. It never redirects; it only builds and copies a
// personalized message for a public invitation URL.
Route::get('/share', [ShareController::class, 'index'])->name('share.index');

// Public invitations are reachable by slug only. Non-active invitations are
// private to their owner and show an activation popup. Old slugs are resolved
// through invitation_slug_redirects and 301'd.
Route::get('/u/{slug}', [PublicInvitationController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('invitation.show');

Route::middleware('throttle:20,1')->group(function () {
    Route::post('/u/{invitation:slug}/greetings', [GuestResponseController::class, 'greeting'])
        ->where('invitation', '[a-z0-9-]+')
        ->name('public.greetings.store');

    Route::post('/u/{invitation:slug}/rsvp', [GuestResponseController::class, 'rsvp'])
        ->where('invitation', '[a-z0-9-]+')
        ->name('public.rsvp.store');
});

Route::middleware(['auth', 'active', 'preview.expiry'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/transactions', [CustomerTransactionController::class, 'index'])->name('transactions.index');

    Route::prefix('templates')->name('templates.')->group(function () {
        Route::post('/{template}/select', [CustomerTemplateController::class, 'select'])
            ->middleware('role:customer')
            ->name('select');
    });

    Route::prefix('invitations')->name('invitations.')->group(function () {
        Route::get('/{invitation}/edit', [CustomerInvitationController::class, 'edit'])->name('edit');
        Route::get('/{invitation}/templates', [CustomerInvitationController::class, 'templates'])->name('templates.index');
        Route::post('/{invitation}/templates', [CustomerInvitationController::class, 'switchTemplate'])->name('templates.switch');
        Route::get('/{invitation}/slug/check', [CustomerInvitationController::class, 'checkSlug'])->name('slug.check');
        Route::patch('/{invitation}', [CustomerInvitationController::class, 'update'])->name('update');
        Route::delete('/{invitation}', [CustomerInvitationController::class, 'destroy'])->name('destroy');
        Route::get('/{invitation}/activate', [CustomerPaymentController::class, 'activation'])->name('activation');
        Route::post('/{invitation}/payment', [CustomerPaymentController::class, 'start'])->name('payment.start');
        Route::get('/{invitation}/payment', [CustomerPaymentController::class, 'status'])->name('payment.status');
        Route::post('/{invitation}/payment/check', [CustomerPaymentController::class, 'check'])->name('payment.check');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:super_admin')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::resource('templates', AdminTemplateController::class)->except(['show']);
        Route::post('templates/{template}/toggle', [AdminTemplateController::class, 'toggleStatus'])
            ->name('templates.toggle');

        Route::resource('musics', AdminMusicController::class)->except(['show']);
        Route::post('musics/{music}/toggle', [AdminMusicController::class, 'toggleStatus'])
            ->name('musics.toggle');

        Route::resource('vouchers', AdminVoucherController::class)->except(['show']);
        Route::post('vouchers/{voucher}/toggle', [AdminVoucherController::class, 'toggleStatus'])
            ->name('vouchers.toggle');

        Route::get('customers', [AdminCustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{user}', [AdminCustomerController::class, 'show'])->name('customers.show');
        Route::post('customers/{user}/status', [AdminCustomerController::class, 'updateStatus'])
            ->name('customers.status');

        Route::get('invitations', [AdminInvitationController::class, 'index'])->name('invitations.index');
        Route::get('invitations/{invitation}', [AdminInvitationController::class, 'show'])->name('invitations.show');
        Route::post('invitations/{invitation}/status', [AdminInvitationController::class, 'updateStatus'])
            ->name('invitations.status');

        Route::get('transactions', [AdminTransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/{transaction}', [AdminTransactionController::class, 'show'])->name('transactions.show');
        Route::post('transactions/{transaction}/check', [AdminTransactionController::class, 'check'])
            ->name('transactions.check');
    });
});
