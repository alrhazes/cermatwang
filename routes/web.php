<?php

use App\Http\Controllers\ChatMessageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('chat', function () {
        return Inertia::render('chat');
    })->name('chat');

    Route::post('chat/messages', ChatMessageController::class)
        ->middleware('throttle:30,1')
        ->name('chat.messages');

    Route::redirect('dashboard', '/chat');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
