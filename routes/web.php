<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/home', [UserController::class, 'index']);
Route::post('/home/find', [UserController::class, 'findByName']);