<?php

use App\Http\Controllers\InstallerController;
use Illuminate\Support\Facades\Route;

// Guided Web Installer Wizard for Non-Developers
Route::get('/install', [InstallerController::class, 'index'])->name('installer.index');
Route::post('/install/check-database', [InstallerController::class, 'testDatabase'])->name('installer.check_db');
Route::post('/install/run', [InstallerController::class, 'runInstall'])->name('installer.run');

// Main LABVERSE SPA Application Shell (Auto-redirects to /install if database is not yet initialized)
Route::get('/{any?}', function () {
    if (!InstallerController::isInstalled()) {
        return redirect('/install');
    }
    return view('labverse');
})->where('any', '^(?!api|install|up).*$');
