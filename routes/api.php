<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\beatsApiController;
use App\Http\Controllers\PayPalPayment;

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

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });


Route::get('/newestbeats', [beatsApiController::class, 'index']);
Route::get('/allbeats', [beatsApiController::class, 'all_beats']);
Route::get('/moods', [beatsApiController::class, 'moods']);
Route::get('/genre', [beatsApiController::class, 'genre']);
Route::get('/keys', [beatsApiController::class, 'keys']);
Route::get('/search_engine/{engine_object}', [beatsApiController::class, 'search_engine']);
Route::get('/select_depending_on_genre/{genre}', [beatsApiController::class, 'select_depending_on_genre']);
Route::get('/tags/{id}', [beatsApiController::class, 'tags']);
Route::get('/Alltags', [beatsApiController::class, 'Alltags']); //
Route::get('/beat_desc/{id}', [beatsApiController::class, 'beat_desc']);
Route::get('/register/{email}', [beatsApiController::class, 'register']);
Route::get('/login/{email}/{password}', [beatsApiController::class, 'login']);
Route::get('/forgotPassword/{email}/', [beatsApiController::class, 'new_password']);
Route::get('/token_verify/{token?}/', [beatsApiController::class, 'check_session_token'])->where('token', '(.*)');
Route::get('/favoris/{token}/{foreign_id}', [beatsApiController::class, 'favoris'])->where(['token' => '(.*)', "foreign_id" => '[0-9]+']);
Route::get('/get_favoris/{token}/', [beatsApiController::class, 'get_favoris'])->where(['token' => '(.*)']);
Route::get('/favoris_show/{token}/', [beatsApiController::class, 'favoris_show'])->where(['token' => '(.*)']);
Route::get('/favoris_delete/{token}/{foreign_id}', [beatsApiController::class, 'favoris_delete'])->where(['token' => '(.*)', "foreign_id" => '[0-9]+']);
Route::get('/update_password/{token}/{password}', [beatsApiController::class, 'update_password'])->where(['token' => '(.*)', 'password' => '(.*)']);
Route::get('/get_desc_siblings/{genre}', [beatsApiController::class, 'get_desc_siblings']);
Route::get('/add_comment/{token}/{foreign_id}/{comment}', [beatsApiController::class, 'add_comment'])->where(['token' => '(.*)', 'comment' => '(.*)', "foreign_id" => '[0-9]+']);
Route::get('/show_comment/{foreign_id}', [beatsApiController::class, 'show_comment'])->where(["foreign_id" => '[0-9]+']);
Route::get('/free_download/{id}', [beatsApiController::class, 'free_download'])->where(["id" => '[0-9]+']);
Route::get('/free_download_send_mail/{id}/{email}', [beatsApiController::class, 'free_download_send_mail'])->where(["id" => '[0-9]+']);
Route::get('/email_me/{name}/{email}/{subject}/{message}', [beatsApiController::class, 'email_me']);
Route::get('/paypal_create_payment', [beatsApiController::class, 'paypal_create_payment']);
Route::get('/paypal_execute_payment/{paymentID}/{payerID}', [beatsApiController::class, 'paypal_execute_payment']);
