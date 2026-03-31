<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\CompleteFinancialOnboardingController;
use App\Http\Controllers\PendingToolCallController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('chat', [ChatController::class, 'show'])->name('chat');

    Route::post('chat/messages', ChatMessageController::class)
        ->middleware('throttle:30,1')
        ->name('chat.messages');

    Route::post('chat/pending-tool-calls/{pendingId}/confirm', [PendingToolCallController::class, 'confirm'])
        ->name('chat.pending_tools.confirm');
    Route::delete('chat/pending-tool-calls/{pendingId}', [PendingToolCallController::class, 'cancel'])
        ->name('chat.pending_tools.cancel');

    Route::post('chat/onboarding/complete', CompleteFinancialOnboardingController::class)
        ->name('chat.onboarding.complete');

    Route::redirect('dashboard', '/chat');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
