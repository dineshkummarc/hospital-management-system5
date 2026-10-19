@echo off
title Launching Hospital Management System (HMS)
echo ===================================================
echo     Hospital Management System - Demo Launcher
echo ===================================================
echo.

echo Checking MySQL (MariaDB)...
netstat -o -n -a | findstr "3306" | findstr "LISTENING" >nul
if %ERRORLEVEL% equ 0 (
    echo [OK] MySQL is already running on port 3306.
) else (
    echo Starting MySQL...
    start /b "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini"
    timeout /t 2 /nobreak >nul
)

echo Checking Apache Web Server...
netstat -o -n -a | findstr ":80 " | findstr "LISTENING" >nul
if %ERRORLEVEL% equ 0 (
    echo [OK] Apache Web Server is already running.
) else (
    echo Starting Apache Web Server...
    start /b "" "C:\xampp\apache\bin\httpd.exe"
    timeout /t 2 /nobreak >nul
)

echo.
echo Opening HMS in your default browser...
start http://localhost/hms/

echo.
echo ===================================================
echo HMS is running at: http://localhost/hms/
echo Demo Credentials:
echo   - Admin:   admin@hms.com / Admin@123
echo   - Doctor:  ananya.sharma@hms.com / Doctor@123
echo   - Patient: aarav.mehta@example.com / Patient@123
echo ===================================================
echo.
pause
