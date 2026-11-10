<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('kontak', 'contacts.index')->name('contacts.index');
    Route::livewire('kunjungan', 'visits.index')->name('visits.index');
});

require __DIR__ . '/settings.php';
