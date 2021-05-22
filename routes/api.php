<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('events/{event}/days', [EventsController::class, 'getAvailableDays']);
Route::get('events/{event}/{day}/slots', [EventsController::class, 'getAvailableSlots']);
Route::post('events/{event}/book', [EventsController::class, 'bookEventSlot']);
