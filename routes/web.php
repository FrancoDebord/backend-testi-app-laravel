<?php

use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BibleController;
use App\Http\Controllers\Web\ExploreController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\ModerationController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\TestimonyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — TestiApp
|--------------------------------------------------------------------------
*/

// ── Auth ─────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',            [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',           [AuthController::class, 'login']);
    Route::get('/register',         [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',        [AuthController::class, 'register']);
    Route::get('/forgot-password',  [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Public ────────────────────────────────────────────────────────────────
Route::get('/',                     [HomeController::class, 'index'])->name('home');
Route::get('/explore',              [ExploreController::class, 'index'])->name('explore');
Route::get('/bible',                [BibleController::class, 'reader'])->name('bible.reader');
Route::get('/testimonies/{id}',     [TestimonyController::class, 'show'])->name('testimonies.show');
Route::get('/profiles/{id}',        [ProfileController::class, 'show'])->name('profiles.show');

// ── Authenticated ─────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Testimonies
    Route::get('/publish',               [TestimonyController::class, 'create'])->name('publish');
    Route::post('/testimonies',          [TestimonyController::class, 'store'])->name('testimonies.store');
    Route::get('/mes-temoignages',       [TestimonyController::class, 'myTestimonies'])->name('testimonies.mine');
    Route::post('/testimonies/{id}/report',    [TestimonyController::class, 'report'])->name('testimonies.report');
    Route::post('/testimonies/{id}/reactions', [TestimonyController::class, 'storeReaction'])->name('testimonies.reactions.store');
    Route::put('/testimonies/{id}/save',       [TestimonyController::class, 'saveTestimony'])->name('testimonies.save');
    Route::post('/testimonies/{id}/comments',  [TestimonyController::class, 'storeComment'])->name('testimonies.comments.store');

    // Profile
    Route::get('/profile/edit',          [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile',               [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/saved',         [ProfileController::class, 'savedTestimonies'])->name('profile.saved');
    Route::get('/profile/settings',      [ProfileController::class, 'settings'])->name('profile.settings');
    Route::put('/profile/settings',      [ProfileController::class, 'updateSettings'])->name('profile.settings.update');

    // Notifications
    Route::get('/notifications',         [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});

// ── Moderation ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:moderateur,administrateur'])->prefix('moderation')->name('moderation.')->group(function () {
    Route::get('/',                  [ModerationController::class, 'index'])->name('index');
    Route::get('/{id}',              [ModerationController::class, 'show'])->name('show');
    Route::post('/{id}/approve',     [ModerationController::class, 'approve'])->name('approve');
    Route::post('/{id}/reject',      [ModerationController::class, 'reject'])->name('reject');
});

// ── Admin ──────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:administrateur'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                          [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users',                     [AdminController::class, 'users'])->name('users.index');
    Route::get('/users/{id}',                [AdminController::class, 'showUser'])->name('users.show');
    Route::put('/users/{id}',                [AdminController::class, 'updateUser'])->name('users.update');
    Route::get('/content',                   [AdminController::class, 'content'])->name('content.index');
    Route::get('/categories',                [AdminController::class, 'categories'])->name('categories.index');
    Route::post('/categories',               [AdminController::class, 'storeCategory'])->name('categories.store');
    Route::put('/categories/{id}',           [AdminController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{id}',        [AdminController::class, 'deleteCategory'])->name('categories.destroy');
    Route::get('/settings',                  [AdminController::class, 'settings'])->name('settings');
    Route::put('/settings',                  [AdminController::class, 'updateSettings'])->name('settings.update');
});
