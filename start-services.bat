@echo off
REM ============================================================
REM  Realtime / Web Push services for the "documents" app.
REM  Run this after every reboot (or auto-start it, see below).
REM  Requires XAMPP MySQL + Apache to be running.
REM ============================================================

cd /d C:\xampp\htdocs\documents
set OPENSSL_CONF=C:\xampp\php\extras\ssl\openssl.cnf

REM Reverb — websocket server for in-page toasts.
start "Reverb (websockets)" C:\xampp\php\php.exe artisan reverb:start --host=0.0.0.0 --port=8080

REM Queue worker — sends emails and Web Push notifications.
start "Queue Worker" C:\xampp\php\php.exe artisan queue:work --queue=default,notifications --tries=3

echo.
echo Reverb and the queue worker started in separate windows.
echo Keep those two windows open. Closing them stops notifications.
echo.
pause
