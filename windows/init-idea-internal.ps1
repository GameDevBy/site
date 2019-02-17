#requires -v 3

#############################################################################
# If Powershell is running the 32-bit version on a 64-bit machine,
# we need to force powershell to run in 64-bit mode.
# http://cosmonautdreams.com/2013/09/03/Getting-Powershell-to-run-in-64-bit.html
#############################################################################

if ($env:PROCESSOR_ARCHITEW6432 -eq "AMD64") {
  if ($myInvocation.Line) {
    &"$env:WINDIR\sysnative\windowspowershell\v1.0\powershell.exe" -NonInteractive -NoProfile $myInvocation.Line
  } else {
    &"$env:WINDIR\sysnative\windowspowershell\v1.0\powershell.exe" -NonInteractive -NoProfile -file "$($myInvocation.InvocationName)" $args
  }
  exit $lastexitcode
}

################

# PowerShell must be enabled for your user account

Set-ExecutionPolicy RemoteSigned -scope CurrentUser

# Save old context

$OLD_PATH = [environment]::getEnvironmentVariable('PATH_USER', 'User')
$OLD_PHP_INI_SCAN_DIR = [environment]::getEnvironmentVariable('PHP_INI_SCAN_DIR', 'User')
$OLD_COMPOSER_HOME_DIR = [environment]::getEnvironmentVariable('COMPOSER_HOME', 'User')
$OLD_SCOOP = [environment]::getEnvironmentVariable('SCOOP', 'User')
$OLD_SCOOP_GLOBAL = [environment]::getEnvironmentVariable('SCOOP_GLOBAL', 'Machine')

# Set envarenment

$SCRIPT_DIR = "$PSScriptRoot\app"
$SCRIPT_DIR_LOCAL = "$SCRIPT_DIR\local"
$SCRIPT_DIR_GLOBAL = "$SCRIPT_DIR\global"
$COMPOSER_HOME_DIR = "$SCRIPT_DIR_LOCAL\persist\composer\home"
$PHP_INI_SCAN_DIR = "$SCRIPT_DIR_LOCAL\apps\php-nts\current\cli"

# Set exe pathes

$SCOOP_EXE = "$SCRIPT_DIR_LOCAL\apps\scoop\current\bin\scoop.ps1"

# Config envarenment

[environment]::setEnvironmentVariable('SCOOP', $SCRIPT_DIR_LOCAL, 'User')
$env:SCOOP = $SCRIPT_DIR_LOCAL
[environment]::setEnvironmentVariable('SCOOP_GLOBAL', $SCRIPT_DIR_GLOBAL, 'Machine')
[environment]::setEnvironmentVariable($COMPOSER_HOME_DIR, 'User')
[environment]::setEnvironmentVariable('PHP_INI_SCAN_DIR', $PHP_INI_SCAN_DIR, 'Process')

# Check

Invoke-Expression "&'$SCOOP_EXE' checkup"

# Install idea

if (!(test-path "$SCRIPT_DIR_LOCAL\apps\idea\current"))
{
  Invoke-Expression "&'$SCOOP_EXE' install idea"
}



# Restory old context

[environment]::setEnvironmentVariable('PATH_USER', $null, 'User')
[environment]::setEnvironmentVariable('PHP_INI_SCAN_DIR', $null, 'User')
[environment]::setEnvironmentVariable('COMPOSER_HOME', $null, 'User')
[environment]::setEnvironmentVariable('SCOOP', $null, 'User')
[environment]::setEnvironmentVariable('SCOOP_GLOBAL', $null, 'Machine')

[environment]::setEnvironmentVariable('PATH_USER', $OLD_PATH, 'User')
[environment]::setEnvironmentVariable('PHP_INI_SCAN_DIR', $OLD_PHP_INI_SCAN_DIR, 'User')
[environment]::setEnvironmentVariable('COMPOSER_HOME', $OLD_COMPOSER_HOME_DIR, 'User')
[environment]::setEnvironmentVariable('SCOOP', $OLD_SCOOP, 'User')
[environment]::setEnvironmentVariable('SCOOP_GLOBAL', $OLD_SCOOP_GLOBAL, 'Machine')
