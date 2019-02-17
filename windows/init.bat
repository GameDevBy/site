@echo off

SET POWER_SHELL_EXE="%SystemRoot%\Sysnative\WindowsPowerShell\v1.0\powershell.exe"

rem For PowerShell version 5.1.14393.206 or newer, before installing PSScriptAnalyzer, need install the latest Nuget provider

%POWER_SHELL_EXE% -Command "Install-PackageProvider Nuget -MinimumVersion 2.8.5.201 -Force"

rem Set PSGallery trusted repository

%POWER_SHELL_EXE% -Command "Set-PSRepository -Name 'PSGallery' -InstallationPolicy Trusted"

rem Install PSScriptAnalyzer

%POWER_SHELL_EXE% -Command "Install-Module -Name PSScriptAnalyzer"

rem Run main init script

%POWER_SHELL_EXE% -f %~dp0\init-internal.ps1
