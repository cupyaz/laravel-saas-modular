<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Controllers\Api\UserController;

Route::prefix('api/v1')->group(function () {
    Route::apiResource('users', UserController::class);
});