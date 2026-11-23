<?php

use Illuminate\Support\Facades\Route;

// Every non-API URL returns the same HTML shell; Vue Router decides what to show.
Route::view('/{any?}', 'app')->where('any', '^(?!api|up).*$');
