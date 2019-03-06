@echo off

if exist %~dp0\app\extern\idea (
    choice /C YN /M "Remove 'HKEY_CURRENT_USER\Software\JavaSoft\Prefs\jetbrains\phpstorm' from the registry? (Press 'Y' for Yes, 'N' for No)"
    if %errorlevel% == Y (
        reg delete "HKEY_CURRENT_USER\Software\JavaSoft\Prefs\jetbrains\phpstorm" /f
    )
)

rmdir /S /Q %~dp0\app
