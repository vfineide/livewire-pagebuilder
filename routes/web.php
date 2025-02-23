<?php

use Illuminate\Support\Facades\Route;

//Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/pagebuilder/{id}', function($id) {
        return view('livewire-pagebuilder::volt.pagebuilder', ['id' => $id]);
    })->name('pagebuilder.edit');
//}); 