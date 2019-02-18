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

# Unsip Function

Add-Type -AssemblyName System.IO.Compression.FileSystem
function Unzip
{
  param([string]$zipfile, [string]$outpath)
  [System.IO.Compression.ZipFile]::ExtractToDirectory($zipfile, $outpath)
}

################

# Function for idea plugin setup

$global:IDEA_PLUGINS_DIR=''
$global:ARIA2_EXE=''

function InstallIdeaPlugin
{
  param([int]$pluginId)

  $pluginZip = "$global:IDEA_PLUGINS_DIR\$pluginId.zip"

  if ((test-path "$pluginZip"))
  {
    Write-Output "Already installed plugin: $pluginId"
    return
  }
  if ((test-path "$global:IDEA_PLUGINS_DIR\$pluginId.jar"))
  {
    Write-Output "Already installed plugin: $pluginId"
    return
  }

  $IDEA_PLUGINS_URL = 'https://plugins.jetbrains.com/plugin/updates?channel=&start=0&size=1&pluginId='

  $pluginInfo = (Invoke-WebRequest -Uri "$IDEA_PLUGINS_URL$pluginId" | ConvertFrom-Json)

  $pluginName = $pluginInfo.pluginName
  $pluginVendor = $pluginInfo.vendor
  $pluginVersion = $pluginInfo.updates[0].version
  $pluginDate = $pluginInfo.updates[0].cdate
  $pluginUrl = $pluginInfo.updates[0].file

  Write-Output "Installing '$pluginName' (by '$pluginVendor') [$pluginVersion - $pluginDate]"

  $downloadUrl = "https://plugins.jetbrains.com/files/$pluginUrl"

  Start-Process $global:ARIA2_EXE -NoNewWindow -Wait -ArgumentList "--dir=$global:IDEA_PLUGINS_DIR", "--out=$pluginId.zip", $downloadUrl

  $IDEA_PLUGINS_TEMP_DIR = get-item $global:IDEA_PLUGINS_DIR
  $IDEA_PLUGINS_TEMP_DIR = $IDEA_PLUGINS_TEMP_DIR.parent.FullName
  $IDEA_PLUGINS_TEMP_DIR = "$IDEA_PLUGINS_TEMP_DIR\temp"
  If ((test-path "$IDEA_PLUGINS_TEMP_DIR"))
  {
    Remove-Item $IDEA_PLUGINS_TEMP_DIR -Recurse -Force
  }
  New-Item -ItemType Directory -Force -Path "$IDEA_PLUGINS_TEMP_DIR" *>$null

  Unzip "$pluginZip" "$IDEA_PLUGINS_TEMP_DIR"

  if ((test-path "$IDEA_PLUGINS_TEMP_DIR\META-INF"))
  {
    Rename-Item -Path "$pluginZip" -NewName "$pluginId.jar"
  } else {
    Unzip "$pluginZip" "$global:IDEA_PLUGINS_DIR"
  }

  Remove-Item $IDEA_PLUGINS_TEMP_DIR -Recurse -Force
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

$SCOOP_EXE = "$SCRIPT_DIR_LOCAL\apps\scoop\current\bin\scoop.ps1"

$global:IDEA_PLUGINS_DIR = "$SCRIPT_DIR_LOCAL\apps\idea\current\plugins"
$global:ARIA2_EXE = "$SCRIPT_DIR_LOCAL\shims\aria2c.exe"

# Config envarenment

[environment]::setEnvironmentVariable('SCOOP', $SCRIPT_DIR_LOCAL, 'User')
$env:SCOOP = $SCRIPT_DIR_LOCAL
[environment]::setEnvironmentVariable('SCOOP_GLOBAL', $SCRIPT_DIR_GLOBAL, 'Machine')
[environment]::setEnvironmentVariable($COMPOSER_HOME_DIR, 'User')
[environment]::setEnvironmentVariable('PHP_INI_SCAN_DIR', $PHP_INI_SCAN_DIR, 'Process')

# Check scoop install

if (!(test-path "$SCOOP_EXE"))
{
  Write-Output "Environment not initialized. Run init.bat before."
  exit 1
}
Invoke-Expression "&'$SCOOP_EXE' checkup"

# Install idea

if (!(test-path "$SCRIPT_DIR_LOCAL\apps\idea\current"))
{
  Invoke-Expression "&'$SCOOP_EXE' install idea"
  Invoke-Expression "&'$SCOOP_EXE' virustotal *"
  Invoke-Expression "&'$SCOOP_EXE' cleanup *"
  Invoke-Expression "&'$SCOOP_EXE' cache rm *"

  # Remove unused default plugins

  $IDEA_PLUGINS_TEMP_DIR = get-item $global:IDEA_PLUGINS_DIR
  $IDEA_PLUGINS_TEMP_DIR = $IDEA_PLUGINS_TEMP_DIR.parent.FullName
  $IDEA_PLUGINS_TEMP_DIR = "$IDEA_PLUGINS_TEMP_DIR\temp"
  If ((test-path "$IDEA_PLUGINS_TEMP_DIR"))
  {
    Remove-Item $IDEA_PLUGINS_TEMP_DIR -Recurse -Force
  }
  New-Item -ItemType Directory -Force -Path "$IDEA_PLUGINS_TEMP_DIR" *>$null

  $PLUGINS_FOR_SAFE = @("git4idea", "github", "terminal", "xpath", "yaml")

  foreach ($pluginName in $PLUGINS_FOR_SAFE)
  {
    Copy-Item "$global:IDEA_PLUGINS_DIR\$pluginName" $IDEA_PLUGINS_TEMP_DIR -Recurse
  }

  Remove-Item $global:IDEA_PLUGINS_DIR -Recurse -Force
  New-Item -ItemType Directory -Force -Path "$global:IDEA_PLUGINS_DIR" *>$null

  foreach ($pluginName in $PLUGINS_FOR_SAFE)
  {
    Copy-Item "$IDEA_PLUGINS_TEMP_DIR\$pluginName" $global:IDEA_PLUGINS_DIR -Recurse
  }

  Remove-Item $IDEA_PLUGINS_TEMP_DIR -Recurse -Force
}

# Install plugins

InstallIdeaPlugin 7303  # https://plugins.jetbrains.com/plugin/7303-twig-support
InstallIdeaPlugin 7793  # https://plugins.jetbrains.com/plugin/7793-markdown-support
InstallIdeaPlugin 264   # https://plugins.jetbrains.com/plugin/264-jsintentionpowerpack
InstallIdeaPlugin 6981  # https://plugins.jetbrains.com/plugin/6981-ini4idea
InstallIdeaPlugin 10275 # https://plugins.jetbrains.com/plugin/10275-hunspell
InstallIdeaPlugin 9164  # https://plugins.jetbrains.com/plugin/9164-gherkin
InstallIdeaPlugin 7177  # https://plugins.jetbrains.com/plugin/7177-file-watchers
InstallIdeaPlugin 7352  # https://plugins.jetbrains.com/plugin/7352-drupal-support
InstallIdeaPlugin 7724  # https://plugins.jetbrains.com/plugin/7724-docker-integration
InstallIdeaPlugin 10925 # https://plugins.jetbrains.com/plugin/10925-database-tools-and-sql
InstallIdeaPlugin 6630  # https://plugins.jetbrains.com/plugin/6630-command-line-tool-support
InstallIdeaPlugin 7512  # https://plugins.jetbrains.com/plugin/7512-behat-support
InstallIdeaPlugin 6834  # https://plugins.jetbrains.com/plugin/6834-apache-config--htaccess-support
InstallIdeaPlugin 7219  # https://plugins.jetbrains.com/plugin/7219-symfony-plugin
InstallIdeaPlugin 10249 # https://plugins.jetbrains.com/plugin/10249-powershell
InstallIdeaPlugin 8133  # https://plugins.jetbrains.com/plugin/8133-php-toolbox
InstallIdeaPlugin 7622  # https://plugins.jetbrains.com/plugin/7622-php-inspections-ea-extended-
InstallIdeaPlugin 7320  # https://plugins.jetbrains.com/plugin/7320-php-annotations
InstallIdeaPlugin 7294  # https://plugins.jetbrains.com/plugin/7294-editorconfig
InstallIdeaPlugin 11478 # https://plugins.jetbrains.com/plugin/11478-deep-js-completion
InstallIdeaPlugin 9927  # https://plugins.jetbrains.com/plugin/9927-deep-assoc-completion
InstallIdeaPlugin 5834  # https://plugins.jetbrains.com/plugin/5834-cmd-support
InstallIdeaPlugin 4230  # https://plugins.jetbrains.com/plugin/4230-bashsupport

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
