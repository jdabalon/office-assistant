<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::livewire('/users', 'users.index')
        ->name('users.index');

     Route::view('/dashboard', 'dashboard')
        ->name('dashboard');

    Route::livewire('/chat', 'chat.index')
        ->name('chat.index');
});

require __DIR__.'/settings.php';