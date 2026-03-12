<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\TouchUserPresence;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/activity', [DashboardController::class, 'activity'])
    ->middleware(['auth'])
    ->name('activity.index');

Route::middleware(['auth', TouchUserPresence::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::get('/chat/contacts', [ChatController::class, 'contacts'])->name('chat.contacts');
    Route::get('/contacts', [ChatController::class, 'directory'])->name('contacts.index');
    Route::get('/media', [ChatController::class, 'media'])->name('media.index');

    Route::post('/messages/typing', [MessageController::class, 'typing'])->name('messages.typing');
    Route::patch('/messages/{message}/status', [MessageController::class, 'updateStatus'])
        ->whereNumber('message')
        ->name('messages.status');

    Route::get('/messages/{user}', [MessageController::class, 'index'])
        ->whereNumber('user')
        ->name('messages.index');

    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/messages/send', [MessageController::class, 'storeLegacy'])->name('messages.send');
});

require __DIR__.'/auth.php';
