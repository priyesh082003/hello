<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home');

Route::get('/home', [UserController::class, 'index']);
Route::post('/register_customer', [UserController::class, 'register_customer']);
Route::post('/home/find', [UserController::class, 'findByName']);
Route::get('/home-category', [UserController::class, 'getCategory']);
Route::post('/open_otp', [UserController::class, 'open_otp']);


Route::post('/register_as_seller', [UserController::class, 'register_as_seller']);
Route::post('/register_as_influencer', [UserController::class, 'register_as_influencer']);
Route::post('/updateProfile', [UserController::class, 'updateProfile']);
Route::post('/mobile_otp_user', [UserController::class, 'mobile_otp_user']);
Route::post('/reset_user_password', [UserController::class, 'reset_user_password']);
Route::post('/password_send_otp', [UserController::class, 'password_send_otp']);
Route::post('/user_login', [UserController::class, 'user_login']);

