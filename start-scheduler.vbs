Set WshShell = CreateObject("WScript.Shell")
WshShell.Run "cmd /c cd /d C:\laragon\www\dashboardCarParts && C:\laragon\bin\php\php-8.3.29-nts-Win32-vs16-x64\php.exe artisan schedule:work", 0, False
Set WshShell = Nothing
