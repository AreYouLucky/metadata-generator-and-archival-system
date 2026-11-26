<?php

/**
 * Routes shared by every signed-in role.
 */

use App\Http\Controllers\All\ChatController;
use App\Http\Controllers\All\ChatConversationController;
use App\Http\Controllers\All\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // "Ask me" (Phase 7): the floating assistant on every page. Answers come
    // only from approved records this account may see, through the function tools.
    // Each account keeps a history of its own conversations.
    Route::get('chat', [ChatConversationController::class, 'index'])->name('chat.index');
    Route::get('chat/{conversation}', [ChatConversationController::class, 'show'])->whereNumber('conversation')->name('chat.show');
    Route::delete('chat/{conversation}', [ChatConversationController::class, 'destroy'])->whereNumber('conversation')->middleware('throttle:30,1')->name('chat.destroy');
    Route::post('chat', [ChatController::class, 'store'])->middleware('throttle:chat')->name('chat.store');
    Route::delete('chat', [ChatController::class, 'destroy'])->middleware('throttle:30,1')->name('chat.clear');
});
