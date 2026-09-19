<?php

use App\Http\Controllers\Api\AcceptedEmployeesController;
use Illuminate\Support\Facades\Route;

Route::get('/accepted-employees', AcceptedEmployeesController::class)
    ->middleware('accepted-registrations.key');
