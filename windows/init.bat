@echo off

SET POWER_SHELL_EXE="%SystemRoot%\Sysnative\WindowsPowerShell\v1.0\powershell.exe"

rem Check PowerShell version

for /f "delims=" %%a in ('%%POWER_SHELL_EXE%% -command "(Get-Variable PSVersionTable -ValueOnly).PSVersion.Major"') do @set POWER_SHELL_VERSION_MAJOR=%%a
set POWER_SHELL_VERSION_MAJOR=%POWER_SHELL_VERSION_MAJOR%
for /f "delims=" %%a in ('%%POWER_SHELL_EXE%% -command "(Get-Variable PSVersionTable -ValueOnly).PSVersion.Minor"') do @set POWER_SHELL_VERSION_MINOR=%%a
set POWER_SHELL_VERSION_MINOR=%POWER_SHELL_VERSION_MINOR%
IF %POWER_SHELL_VERSION_MAJOR% LSS 5 (
  echo Founded PowerShell version: %POWER_SHELL_VERSION_MAJOR%.%POWER_SHELL_VERSION_MINOR%
  echo ERROR: Need PowerShell version >=5.1. Please update power shell script by link https://www.microsoft.com/en-us/download/details.aspx?id=54616
  exit /b 1
) else (
    IF %POWER_SHELL_VERSION_MAJOR% EQU 5 (
        IF %POWER_SHELL_VERSION_MINOR% LSS 1 (
          echo Founded PowerShell version: %POWER_SHELL_VERSION_MAJOR%.%POWER_SHELL_VERSION_MINOR%
          echo ERROR: Need PowerShell version >=5.1. Please update power shell script by link https://www.microsoft.com/en-us/download/details.aspx?id=54616
          exit /b 1
        )
    )
)
echo Founded PowerShell version: %POWER_SHELL_VERSION_MAJOR%.%POWER_SHELL_VERSION_MINOR%

rem Check .NET Framework version (https://docs.microsoft.com/en-us/dotnet/framework/migration-guide/how-to-determine-which-versions-are-installed)

for /f "tokens=3" %%a in ('reg query "HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\NET Framework Setup\NDP\v4\Full" /v "Release" ^| find /i "Release"') do set /a NET_VERSION=%%a + 0
set NET_VERSION=%NET_VERSION%
IF %NET_VERSION% LSS 378389 (
  echo Founded .Net Framework:
  wmic product get description | findstr /C:.NET
  echo ERROR: Need version >=4.5. Please update .NET Framework by link https://www.microsoft.com/net/download
  exit /b 1
)
echo Founded .Net Framework version: %NET_VERSION%

rem Before installing PSScriptAnalyzer, need install the Nuget provider

%POWER_SHELL_EXE% -Command "if ((Get-PackageProvider -Name NuGet).version -lt 2.8.5.201 ) { Install-PackageProvider Nuget -MinimumVersion 2.8.5.201 -Force } else { Write-Host 'Version of NuGet installed: ' (Get-PackageProvider -Name NuGet).version}"

rem Install PSScriptAnalyzer

%POWER_SHELL_EXE% -Command "if (Get-Module -ListAvailable -Name PSScriptAnalyzer) { Write-Host 'PSScriptAnalyzer already installed.' } else { Set-PSRepository -Name 'PSGallery' -InstallationPolicy Trusted; Install-Module -Name PSScriptAnalyzer -Force }"

rem Run main init script

%POWER_SHELL_EXE% -f %~dp0\init-internal.ps1
