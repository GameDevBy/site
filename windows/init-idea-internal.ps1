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

$global:IDEA_GLOBAL_PLUGINS_DIR=''
$global:IDEA_LOCAL_PLUGINS_DIR=''
$global:ARIA2_EXE=''

function InstallIdeaPlugin
{
  param([int]$pluginId, [bool]$isLocal = $true)

  $IDEA_PLUGINS_DIR = if ($isLocal) { $global:IDEA_LOCAL_PLUGINS_DIR } else { $global:IDEA_GLOBAL_PLUGINS_DIR }

  $pluginZip = "$IDEA_PLUGINS_DIR\$pluginId.zip"

  if ((test-path "$pluginZip"))
  {
    # Write-Output "", "Already installed plugin: $pluginId"
    return
  }
  if ((test-path "$IDEA_PLUGINS_DIR\$pluginId.jar"))
  {
    # Write-Output "", "Already installed plugin: $pluginId"
    return
  }

  $IDEA_PLUGINS_URL = "https://plugins.jetbrains.com/plugin/getPluginInfo?pluginId=$pluginId"

  $pluginInfo = (Invoke-WebRequest -Uri "$IDEA_PLUGINS_URL" | ConvertFrom-Json)

  $pluginName = $pluginInfo.name
  # $pluginVendor = $pluginInfo.vendor

  $IDEA_PLUGINS_URL = "https://plugins.jetbrains.com/api/plugins/$pluginId/updates?channel="

  $r = Invoke-WebRequest -ContentType "application/json; charset=utf-8" -Uri "$IDEA_PLUGINS_URL"
  # $r.Content now contains the *misinterpreted* JSON string, so we must
  # obtain its byte representation and re-interpret the bytes as UTF-8.
  # Encoding 28591 represents the ISO-8859-1 encoding - see https://docs.microsoft.com/en-us/windows/desktop/Intl/code-page-identifiers
  $jsonCorrected = [Text.Encoding]::UTF8.GetString(
    [Text.Encoding]::GetEncoding(28591).GetBytes($r.Content)
  )
  $pluginInfos = $jsonCorrected | ConvertFrom-Json

  $find = $false
  foreach ($pluginInfo in $pluginInfos)
  {
    $pluginUrl = $pluginInfo.file
    $pluginVersion = $pluginInfo.version
    $pluginDate = $pluginInfo.cdate

    try
    {
        $compatibleVersions = $pluginInfo.compatibleVersions
        $compatibleVersion = $compatibleVersions.PHPSTORM

        $p = $compatibleVersion.IndexOf('+')
        if ($p -ge 0) {
            $cv1 = [System.Version]$compatibleVersion.substring(0, $p)
            # Write-Output $cv1
            $cv2 = $null
        } else {
            $compatibleVersion = $compatibleVersion.split('-')
            # Write-Output $compatibleVersion

            $cv1 = [System.Version]$compatibleVersion[0]
            $cv2 = [System.Version]$compatibleVersion[1]
        }
    }
    catch
    {
        continue
    }

    if($cv1 -le $global:IDEA_VERSION)
    {
        if($null -eq $cv2)
        {
            $find = $true
            break
        }
        if($cv2 -ge $global:IDEA_VERSION)
        {
            $find = $true
            break
        }
    }
  }

  if (!$find)
  {
    Write-Warning "Can not find support version for '$pluginName'"
    return
  }

  Write-Output "", "Installing '$pluginName' [$pluginVersion - $pluginDate]"

  $downloadUrl = "https://plugins.jetbrains.com/files/$pluginUrl"

  Start-Process $global:ARIA2_EXE -NoNewWindow -Wait -ArgumentList "--dir=$IDEA_PLUGINS_DIR", "--out=$pluginId.zip", $downloadUrl

  $IDEA_PLUGINS_TEMP_DIR = get-item $IDEA_PLUGINS_DIR
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
    Unzip "$pluginZip" "$IDEA_PLUGINS_DIR"
  }

  Remove-Item $IDEA_PLUGINS_TEMP_DIR -Recurse -Force
}

################

# Function for copy idea config files

$global:IDEA_VERSION=''

function CopyIdeaConfigFiles
{
  param([string]$srcDir, [string]$destDir)

  # remove dest dir if exist
  If ((test-path "$destDir"))
  {
    Remove-Item "$destDir" -Recurse -Force
  }

  # create dest dir
  New-Item -ItemType Directory -Force -Path "$destDir" *>$null

  Write-Output "", "Copy config files from '$srcDir' to '$destDir'"
  
  $ROOT_DIR = [IO.Path]::GetFullPath("$PSScriptRoot\..")

  # recursive copy files
  Get-ChildItem "$srcDir" -Recurse | ForEach-Object {
    if ($_.PSIsContainer)
    {
        return
    }
    $path = ($_.DirectoryName + "\") -Replace [Regex]::Escape("$srcDir"), "$destDir"
    $fullPath = ($_.FullName) -Replace [Regex]::Escape("$srcDir"), "$destDir"
    Write-Output "$fullPath"

    # Load content
    $configContent = Get-Content $_.FullName -Raw

    If (!(Test-Path "$path"))
    {
        New-Item -ItemType Directory -Force -Path "$path" *>$null
    }

    if ("" -eq "$configContent")
    {
        # Create empy file
        New-Item -ItemType File -Force -Path "$fullPath" *>$null
    }
    else
    {
        # Change content
        $configContent = $configContent.replace("@@PROJECT_DIR@@", $ROOT_DIR)
        
        # Save content
        $configContent | Set-Content "$fullPath" -Force -NoNewline
    }
  }

}

################

# PowerShell must be enabled for your user account

Set-ExecutionPolicy RemoteSigned -scope CurrentUser

# Set envarenment

$ROOT_DIR = [IO.Path]::GetFullPath("$PSScriptRoot\..")

$IDEA_INSTALL_DIR="$PSScriptRoot\app\extern\idea"
$global:IDEA_GLOBAL_PLUGINS_DIR = "$IDEA_INSTALL_DIR\plugins"
$global:ARIA2_EXE = "$PSScriptRoot\app\local\shims\aria2c.exe"

# Install idea

if (!(test-path "$IDEA_INSTALL_DIR\bin\phpstorm.exe"))
{
  If (!(test-path "$IDEA_INSTALL_DIR"))
  {
    New-Item -ItemType Directory -Force -Path "$IDEA_INSTALL_DIR" *>$null
  }

  $IDEA_ZIP_FILE='idea.zip'
  $IDEA_ZIP_PATH="$PSScriptRoot\app\extern\$IDEA_ZIP_FILE"
  If (!(test-path "$IDEA_ZIP_PATH")) {
    $IDEA_URL='https://data.services.jetbrains.com/products/download?code=PS&platform=windows'
    $ideaZipDownloadUrl = [System.Net.HttpWebRequest]::Create($IDEA_URL).GetResponse().ResponseUri.AbsoluteUri.replace('.exe', '.zip')
    Start-Process $global:ARIA2_EXE -NoNewWindow -Wait -ArgumentList "--dir=$PSScriptRoot\app\extern", "--out=$IDEA_ZIP_FILE", $ideaZipDownloadUrl
  }

  Unzip "$IDEA_ZIP_PATH" "$IDEA_INSTALL_DIR"

  # Remove unused default plugins

  $IDEA_PLUGINS_TEMP_DIR = get-item $global:IDEA_GLOBAL_PLUGINS_DIR
  $IDEA_PLUGINS_TEMP_DIR = $IDEA_PLUGINS_TEMP_DIR.parent.FullName
  $IDEA_PLUGINS_TEMP_DIR = "$IDEA_PLUGINS_TEMP_DIR\temp"
  If ((test-path "$IDEA_PLUGINS_TEMP_DIR"))
  {
    Remove-Item $IDEA_PLUGINS_TEMP_DIR -Recurse -Force
  }
  New-Item -ItemType Directory -Force -Path "$IDEA_PLUGINS_TEMP_DIR" *>$null

  $PLUGINS_FOR_SAFE = @(
    "git4idea",
    "github",
    "terminal",
    "xpath",
    "yaml",
    "CSS",
    "php",
    "JSIntentionPowerPack",
    "NodeJS",
    "JavaScriptLanguage",
    "JavaScriptDebugger",
    "DatabaseTools"
    )

  foreach ($pluginName in $PLUGINS_FOR_SAFE)
  {
    Copy-Item "$global:IDEA_GLOBAL_PLUGINS_DIR\$pluginName" $IDEA_PLUGINS_TEMP_DIR -Recurse
  }

  Remove-Item $global:IDEA_GLOBAL_PLUGINS_DIR -Recurse -Force
  New-Item -ItemType Directory -Force -Path "$global:IDEA_GLOBAL_PLUGINS_DIR" *>$null

  foreach ($pluginName in $PLUGINS_FOR_SAFE)
  {
    Copy-Item "$IDEA_PLUGINS_TEMP_DIR\$pluginName" $global:IDEA_GLOBAL_PLUGINS_DIR -Recurse
  }

  Remove-Item $IDEA_PLUGINS_TEMP_DIR -Recurse -Force
}

# Get Idea Version
$global:IDEA_VERSION = (Get-Content "$IDEA_INSTALL_DIR\product-info.json" -Raw | Out-String | ConvertFrom-Json)
$global:IDEA_VERSION = [System.Version]$global:IDEA_VERSION.version
Write-Output 'PhpShtorm Version:', $global:IDEA_VERSION

# Configure local version

$IDEA_PERSIST_DIR = "$PSScriptRoot\app\local\persist\idea"
If (!(test-path "$IDEA_PERSIST_DIR"))
{
    New-Item -ItemType Directory -Force -Path "$IDEA_PERSIST_DIR" *>$null
}

If (!(test-path "$IDEA_INSTALL_DIR\bin\idea.properties.old"))
{
    Rename-Item -Path "$IDEA_INSTALL_DIR\bin\idea.properties" -NewName "idea.properties.old"
}

$ideaIniContent = Get-Content "$IDEA_INSTALL_DIR\bin\idea.properties.old" -Raw
$IDEA_PERSIST_DIR = $IDEA_PERSIST_DIR.replace('\', '/')
$ideaIniContent = $ideaIniContent.replace('# idea.config.path=${user.home}/.PhpStorm/config', "idea.config.path=$IDEA_PERSIST_DIR/config")
$ideaIniContent = $ideaIniContent.replace('# idea.system.path=${user.home}/.PhpStorm/system', "idea.system.path=$IDEA_PERSIST_DIR/system")
$ideaIniContent | Set-Content "$IDEA_INSTALL_DIR\bin\idea.properties" -Force

$global:IDEA_LOCAL_PLUGINS_DIR = "$IDEA_PERSIST_DIR\config\plugins"

# Copy config files

CopyIdeaConfigFiles "$PSScriptRoot\idea-preconf\global" "$IDEA_PERSIST_DIR\config"
CopyIdeaConfigFiles "$PSScriptRoot\idea-preconf\local" "$ROOT_DIR\.idea"

# Install global plugins

InstallIdeaPlugin -isLocal $false -pluginId    7303  # https://plugins.jetbrains.com/plugin/7303-twig-support
InstallIdeaPlugin -isLocal $false -pluginId   10275  # https://plugins.jetbrains.com/plugin/10275-hunspell
InstallIdeaPlugin -isLocal $false -pluginId    9164  # https://plugins.jetbrains.com/plugin/9164-gherkin
InstallIdeaPlugin -isLocal $false -pluginId    7177  # https://plugins.jetbrains.com/plugin/7177-file-watchers
InstallIdeaPlugin -isLocal $false -pluginId    7352  # https://plugins.jetbrains.com/plugin/7352-drupal-support
InstallIdeaPlugin -isLocal $false -pluginId    7724  # https://plugins.jetbrains.com/plugin/7724-docker-integration
InstallIdeaPlugin -isLocal $false -pluginId    6630  # https://plugins.jetbrains.com/plugin/6630-command-line-tool-support
InstallIdeaPlugin -isLocal $false -pluginId    7512  # https://plugins.jetbrains.com/plugin/7512-behat-support
InstallIdeaPlugin -isLocal $false -pluginId    6834  # https://plugins.jetbrains.com/plugin/6834-apache-config--htaccess-support
# InstallIdeaPlugin -isLocal $false -pluginId   264  # https://plugins.jetbrains.com/plugin/264-jsintentionpowerpack
# InstallIdeaPlugin -isLocal $false -pluginId 10925  # https://plugins.jetbrains.com/plugin/10925-database-tools-and-sql
# InstallIdeaPlugin -isLocal $false -pluginId  6610  # https://plugins.jetbrains.com/plugin/6610-php
# InstallIdeaPlugin -isLocal $false -pluginId  6098  # https://plugins.jetbrains.com/plugin/6098-nodejs

# Install local plugins

InstallIdeaPlugin -pluginId  7793  # https://plugins.jetbrains.com/plugin/7793-markdown-support
InstallIdeaPlugin -pluginId  6981  # https://plugins.jetbrains.com/plugin/6981-ini4idea
InstallIdeaPlugin -pluginId  7219  # https://plugins.jetbrains.com/plugin/7219-symfony-plugin
InstallIdeaPlugin -pluginId 10249  # https://plugins.jetbrains.com/plugin/10249-powershell
InstallIdeaPlugin -pluginId  8133  # https://plugins.jetbrains.com/plugin/8133-php-toolbox
InstallIdeaPlugin -pluginId  7622  # https://plugins.jetbrains.com/plugin/7622-php-inspections-ea-extended-
InstallIdeaPlugin -pluginId  7320  # https://plugins.jetbrains.com/plugin/7320-php-annotations
InstallIdeaPlugin -pluginId  7294  # https://plugins.jetbrains.com/plugin/7294-editorconfig
InstallIdeaPlugin -pluginId 11478  # https://plugins.jetbrains.com/plugin/11478-deep-js-completion
InstallIdeaPlugin -pluginId  9927  # https://plugins.jetbrains.com/plugin/9927-deep-assoc-completion
InstallIdeaPlugin -pluginId  5834  # https://plugins.jetbrains.com/plugin/5834-cmd-support
InstallIdeaPlugin -pluginId  4230  # https://plugins.jetbrains.com/plugin/4230-bashsupport

# Create file for run

$IDEA_RUN_SCRIPT_PATH = "$PSScriptRoot\..\run_idea.bat"
If (!(test-path "$IDEA_RUN_SCRIPT_PATH"))
{
  $ideaRunScript = (
    '@echo off',
    'cd %~dp0',
    'call .\init_env.bat',
    'reg Query "HKLM\Hardware\Description\System\CentralProcessor\0" | find /i "x86" > NUL && set OS_BIT=32BIT || set OS_BIT=64BIT',
    'if %OS_BIT%==32BIT start "" "%~dp0\windows\app\extern\idea\bin\phpstorm.exe" %~dp0',
    'if %OS_BIT%==64BIT start "" "%~dp0\windows\app\extern\idea\bin\phpstorm64.exe" %~dp0',
    ''
  )
  $ideaRunScript -join "`n" | Set-Content "$IDEA_RUN_SCRIPT_PATH" -Force -NoNewline
}
