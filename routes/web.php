<?php

use App\Http\Controllers\AttendanceSelfieController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::middleware('auth')->group(function (): void {
    Route::get('/attendance-selfies/{attendanceSelfie}', [AttendanceSelfieController::class, 'show'])
        ->name('attendance-selfies.show')
        ->middleware('signed');
});
