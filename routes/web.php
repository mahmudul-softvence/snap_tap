<?php

use App\Http\Controllers\Update\SystemUpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'page' => 'home'
    ]);
});


Route::get('/terms-conditions', function () {
    return view('frontend.terms-conditions');
})->name('terms.conditions');

Route::get('/privacy-policy', function () {
    return view('frontend.privacy-policy');
})->name('privacy.policy');





    Route::get('update', [SystemUpdateController::class, 'index'])->name('admin.update.index');
    Route::post('update/upload', [SystemUpdateController::class, 'upload'])->name('admin.update.upload');
    Route::post('update/backup', [SystemUpdateController::class, 'backup'])->name('admin.update.backup');
    Route::get('update/backups/{file}', [SystemUpdateController::class, 'downloadBackup'])->name('admin.update.backup.download');
    Route::post('update/run', [SystemUpdateController::class, 'run'])->name('admin.update.run');
    ///delete old backup
    Route::delete('update/backups/{file}', [SystemUpdateController::class, 'deleteBackup'])->name('admin.update.backup.delete');
