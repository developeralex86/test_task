<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadNoteController;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/leads',                [LeadController::class, 'index']);
    Route::get('/leads/{lead}',         [LeadController::class, 'show']);
    Route::patch('/leads/{lead}/assign',[LeadController::class, 'assign']);
    // New note routes
    Route::get('/leads/{lead}/notes',         [LeadNoteController::class, 'index']);
    Route::post('/leads/{lead}/notes',        [LeadNoteController::class, 'store']);
});

// Fix for RouteNotFoundException: Route [login] not defined.
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');