@echo off
TITLE MediCare HMS - Local MySQL Secure Tunnel Setup
COLOR 0A
cls

echo ===================================================================
echo     MediCare HMS - Local Laptop MySQL Secure Tunnel Launcher
echo ===================================================================
echo.
echo This tool exposes your laptop's local MySQL database (port 3306) 
echo to your Render cloud backend via an encrypted SSL/TLS tunnel.
echo.
echo Select your preferred Secure Tunnel Provider:
echo   [1] Cloudflare Tunnel (Recommended - Free, High Security, Fixed Subdomain)
echo   [2] Ngrok TCP Tunnel (Free / Fast Setup)
echo   [3] Exit
echo.

set /p choice="Enter option (1, 2, or 3): "

if "%choice%"=="1" goto CLOUDFLARE
if "%choice%"=="2" goto NGROK
if "%choice%"=="3" goto END

:CLOUDFLARE
echo.
echo [!] Starting Cloudflare Tunnel for MySQL Port 3306...
echo.

set "CF_EXE=cloudflared"
if exist "C:\Program Files (x86)\cloudflared\cloudflared.exe" set "CF_EXE=C:\Program Files (x86)\cloudflared\cloudflared.exe"
if exist "C:\Program Files\cloudflared\cloudflared.exe" set "CF_EXE=C:\Program Files\cloudflared\cloudflared.exe"

"%CF_EXE%" tunnel --url tcp://localhost:3306
goto END

:NGROK
echo.
echo [!] Starting Ngrok TCP Tunnel for MySQL Port 3306...
echo.
where ngrok >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] ngrok is not installed or not in PATH!
    echo Download ngrok from: https://ngrok.com/download
    echo Or install via winget: winget install ngrok.ngrok
    pause
    goto END
)

ngrok tcp 3306
goto END

:END
echo.
echo Thank you for using MediCare HMS. Press any key to exit...
pause >nul
