@echo off

if exist "%~dp0\app\extern\idea" (
    choice /C YN /M "Remove 'HKEY_CURRENT_USER\Software\JavaSoft\Prefs\jetbrains\phpstorm' from the registry?"
    if %errorlevel% == 1 (
        reg delete "HKEY_CURRENT_USER\Software\JavaSoft\Prefs\jetbrains\phpstorm" /f >nul 2>&1
    )
)

if exist %~dp0\app (
    rmdir /S /Q %~dp0\app
)

echo All was removed.
