@echo off
title Nepal Hospital App - Server Launcher
echo ====================================================
echo   Nepal Hospital Management System (HMS)
echo ====================================================
echo.
echo Database Location: E:\HM DATA\hms.db
echo Storage Directory: E:\HM DATA\
echo.

netstat -o -n -a | findstr ":9000" >nul
if %ERRORLEVEL% equ 0 (
    echo [INFO] Server is already active on http://localhost:9000
) else (
    echo [INFO] Starting PHP Local Server...
    start /min "Nepal Hospital PHP Server" "C:\Users\rsah0\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -S localhost:9000 -t "E:\hospital real one"
    timeout /t 2 /nobreak >nul
)

echo [INFO] Opening Nepal Hospital App in Default Browser...
start http://localhost:9000/auth/login.php

exit
