@echo off
cd /d C:\laragon\www\dashboardCarParts
start /min "" C:\laragon\bin\php\php-8.3.29-nts-Win32-vs16-x64\php.exe artisan schedule:work
