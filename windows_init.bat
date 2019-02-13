@echo off

%SystemRoot%\sysnative\WindowsPowerShell\v1.0\powershell.exe -f %~dp0/windows/init-internal.ps1

composer global require hirak/prestissimo
composer install
