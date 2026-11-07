@echo off
title LABVERSE v2.0 Enterprise LIMS — Local Server Launcher
echo ============================================================================
echo   LABVERSE v2.0 — Enterprise Laboratory Information Management System
echo   Automated Windows Clinic Server Launcher
echo ============================================================================
echo.

REM 1. Check if PHP is installed
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] PHP was not found on this computer.
    echo Please install PHP 8.2+ or XAMPP/Laragon, then double-click this file again.
    pause
    exit /b 1
)

REM 2. Ensure .env file exists
if not exist ".env" (
    echo [1/4] Creating environment configuration file ^(.env^)...
    copy ".env.example" ".env" >nul
    php artisan key:generate --ansi
)

REM 3. Ensure SQLite database file exists
if not exist "database\database.sqlite" (
    echo [2/4] Initializing embedded SQLite database...
    type nul > "database\database.sqlite"
    php artisan migrate:fresh --seed --force
) else (
    echo [2/4] Running any pending database updates...
    php artisan migrate --force
)

echo [3/4] Opening LABVERSE in your web browser ^(http://127.0.0.1:8000^)...
echo       ^(Tip: Visit http://127.0.0.1:8000/install anytime for the Visual Setup Wizard^)
echo.
start http://127.0.0.1:8000

echo [4/4] LABVERSE Server is running! Keep this window open while using the app.
echo       Press Ctrl+C to stop the server.
echo ============================================================================
php artisan serve --host=127.0.0.1 --port=8000
pause

