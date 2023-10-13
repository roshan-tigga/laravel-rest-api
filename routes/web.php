<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    //return view('welcome');
});


Route::get('/migrate', function(){
    Artisan::call('migrate');
    dd('migrated!');
});

Route::get('migrate-rollback',function(){
    Artisan::call('migrate:rollback');
    dd('migrate rollback!');
 });

 Route::get('cache-clear',function(){
    Artisan::call('view:clear');
    Artisan::call('route:clear');
    //Artisan::call('config:clear');
    Artisan::call('cache:clear');
    //Artisan::call('key:generate');
  });


Route::view('forgot_password', 'auth.reset_password')->name('password.reset');