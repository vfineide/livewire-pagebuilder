<?php

use Illuminate\Support\Facades\Route;

//Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/pagebuilder/{slug}', function($slug) {
        return view('livewire-pagebuilder::volt.pagebuilder', ['slug' => $slug]);
    })->name('pagebuilder.edit');
//}); 