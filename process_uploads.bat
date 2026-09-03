@echo off
REM ============================================================
REM  Archiven - worker d'upload local (simule le cron)
REM  Lance le traitement de la file toutes les 30 secondes.
REM  A utiliser UNIQUEMENT en local (XAMPP). En production,
REM  c'est le cron O2Switch qui s'en charge.
REM
REM  Double-cliquez sur ce fichier pendant que vous uploadez.
REM  Fermez la fenetre pour arreter.
REM ============================================================

set PHP="C:\xampp_\php\php.exe"
set APP="%~dp0index.php"

echo Worker Archiven demarre. Ctrl+C ou fermez la fenetre pour arreter.
echo.

:loop
%PHP% %APP% cron process_uploads
timeout /t 30 /nobreak >nul
goto loop
