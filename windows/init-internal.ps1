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

# PowerShell must be enabled for your user account

Set-ExecutionPolicy RemoteSigned -scope CurrentUser

# Set root path

$ROOT_DIR = [IO.Path]::GetFullPath("$PSScriptRoot\..")

# Create directory for scoop

$SCRIPT_DIR = "$PSScriptRoot\app"
If (!(test-path $SCRIPT_DIR))
{
  New-Item -ItemType Directory -Force -Path $SCRIPT_DIR *>$null
}

$SCRIPT_DIR_LOCAL = "$SCRIPT_DIR\local"
If (!(test-path $SCRIPT_DIR_LOCAL))
{
  New-Item -ItemType Directory -Force -Path $SCRIPT_DIR_LOCAL *>$null
}

$SCRIPT_DIR_GLOBAL = "$SCRIPT_DIR\global"
If (!(test-path $SCRIPT_DIR_GLOBAL))
{
  New-Item -ItemType Directory -Force -Path $SCRIPT_DIR_GLOBAL *>$null
}

# Save old context

$OLD_PATH = [environment]::getEnvironmentVariable('PATH_USER', 'User')
$OLD_PHP_INI_SCAN_DIR = [environment]::getEnvironmentVariable('PHP_INI_SCAN_DIR', 'User')
$OLD_COMPOSER_HOME_DIR = [environment]::getEnvironmentVariable('COMPOSER_HOME', 'User')
$OLD_SCOOP = [environment]::getEnvironmentVariable('SCOOP', 'User')
$OLD_SCOOP_GLOBAL = [environment]::getEnvironmentVariable('SCOOP_GLOBAL', 'Machine')

# Set exe pathes

$SCOOP_EXE = "$SCRIPT_DIR_LOCAL\apps\scoop\current\bin\scoop.ps1"
$ARIA2_EXE = "$SCRIPT_DIR_LOCAL\shims\aria2c.exe"
$COMPOSER_EXE = "$SCRIPT_DIR_LOCAL\apps\composer\current\composer.ps1"

# Install scoop

[environment]::setEnvironmentVariable('SCOOP', $SCRIPT_DIR_LOCAL, 'User')
$env:SCOOP = $SCRIPT_DIR_LOCAL
If (!(test-path $SCOOP_EXE))
{
  Invoke-Expression (new-object net.webclient).DownloadString('https://get.scoop.sh')
  Invoke-Expression "&'$SCOOP_EXE' bucket add extras"
}
[environment]::setEnvironmentVariable('SCOOP_GLOBAL', $SCRIPT_DIR_GLOBAL, 'Machine')

# Check

Invoke-Expression "&'$SCOOP_EXE' checkup"

# Use multi-connection downloadstring

if (!(test-path "$SCRIPT_DIR_LOCAL\apps\aria2\current"))
{
  Invoke-Expression "&'$SCOOP_EXE' install aria2"
}

# Install php

$PHP_INI_SCAN_DIR = "$SCRIPT_DIR_LOCAL\apps\php-nts\current\cli"
[environment]::setEnvironmentVariable('PHP_INI_SCAN_DIR', $PHP_INI_SCAN_DIR, 'Process')
$PHP_INI = "$PHP_INI_SCAN_DIR\php.ini"
$PHP_DEF_EXT_DIR = "$SCRIPT_DIR_LOCAL\apps\php-nts\current\ext"
if (!(test-path "$SCRIPT_DIR_LOCAL\apps\php-nts\current"))
{
  Invoke-Expression "&'$SCOOP_EXE' install php-nts"

  Copy-Item "$SCRIPT_DIR_LOCAL\apps\php-nts\current\php.ini-development" $PHP_INI

  Add-Content $PHP_INI @"
extension_dir=$PHP_DEF_EXT_DIR
"@

  $GD_INI = @"
extension=gd2
"@
  #Write-Output $GD_INI | Out-File -FilePath "$PHP_INI_SCAN_DIR\gd.ini"
  Add-Content $PHP_INI $GD_INI

  $CURL_INI = @"
extension=curl
"@
  #Write-Output $CURL_INI | Out-File -FilePath "$PHP_INI_SCAN_DIR\curl.ini"
  Add-Content $PHP_INI $CURL_INI

  $MBSTRING_INI = @"
extension=mbstring
"@
  #Write-Output $MBSTRING_INI | Out-File -FilePath "$PHP_INI_SCAN_DIR\mbstring.ini"
  Add-Content $PHP_INI $MBSTRING_INI

  $OPENSSL_INI = @"
extension=openssl
"@
  #Write-Output $OPENSSL_INI | Out-File -FilePath "$PHP_INI_SCAN_DIR\openssl.ini"
  Add-Content $PHP_INI $OPENSSL_INI

  If (!(test-path "$SCRIPT_DIR_LOCAL\persist\php-nts\file_cache"))
  {
    New-Item -ItemType Directory -Force -Path "$SCRIPT_DIR_LOCAL\persist\php-nts\file_cache" *>$null
  }

  # Fix fatal Error Base address marks unusable memory ( https://secure.php.net/manual/en/opcache.configuration.php#120544 )
  $OPCACHE_INI = @"
zend_extension=opcache
opcache.enable=On
opcache.enable_cli=On
opcache.mmap_base=0x20000000
opcache.file_cache=$SCRIPT_DIR_LOCAL\persist\php-nts\file_cache
"@
  #Write-Output $OPCACHE_INI | Out-File -FilePath "$PHP_INI_SCAN_DIR\opcache.ini"
  Add-Content $PHP_INI $OPCACHE_INI
}

# Install php xdebug extension

if (!(test-path "$PHP_DEF_EXT_DIR\php_xdebug.dll"))
{
  $XDEBUG_URL_PREFIX = "https://xdebug.org/files"
  $XDEBUG_VERSION = "2.7.0RC1"
  If ([intptr]::size -eq 8)
  {
    $BIT = '-x86_64'
  }
  else
  {
    $BIT = ''
  }
  $XDEBUG_URL = "$XDEBUG_URL_PREFIX/php_xdebug-$XDEBUG_VERSION-7.3-vc15-nts$BIT.dll"
  Start-Process $ARIA2_EXE -NoNewWindow -Wait -ArgumentList "--dir=$PHP_DEF_EXT_DIR", "--out=php_xdebug.dll", $XDEBUG_URL

  $XDEBUG_INI = @"
zend_extension=xdebug
xdebug.remote_enable=on
;xdebug.remote_autostart=on
xdebug.remote_connect_back=on
xdebug.remote_handler=dbgp
xdebug.remote_mode=req
xdebug.remote_host=127.0.0.1
xdebug.remote_port=9000
xdebug.max_nesting_level=1000
;xdebug.idekey=<idekey>
"@
  #Write-Output $XDEBUG_INI | Out-File -FilePath "$PHP_INI_SCAN_DIR\xdebug.ini"
  Add-Content $PHP_INI $XDEBUG_INI
}

# Install php ast extension

if (!(test-path "$PHP_DEF_EXT_DIR\php_ast.dll"))
{
  $AST_URL_PREFIX = "https://windows.php.net/downloads/pecl/releases/ast"
  $AST_VERSION = "1.0.1"
  If ([intptr]::size -eq 8)
  {
    $BIT = 'x64'
  }
  else
  {
    $BIT = 'x86'
  }
  $AST_URL = "$AST_URL_PREFIX/$AST_VERSION/php_ast-$AST_VERSION-7.3-nts-vc15-$BIT.zip"
  $AST_DIR = "$SCRIPT_DIR\extern\php-ast"
  If (!(test-path "$AST_DIR"))
  {
    New-Item -ItemType Directory -Force -Path "$AST_DIR" *>$null
  }
  $AST_ZIP = "php_ast.zip"
  Start-Process $ARIA2_EXE -NoNewWindow -Wait -ArgumentList "--dir=$AST_DIR", "--out=$AST_ZIP", $AST_URL

  Unzip "$AST_DIR\$AST_ZIP" "$AST_DIR"

  Copy-Item "$AST_DIR\php_ast.dll" "$PHP_DEF_EXT_DIR\php_ast.dll"

  Remove-Item $AST_DIR -Recurse -Force

  $AST_INI = @"
extension=ast
"@
  #Write-Output $AST_INI | Out-File -FilePath "$PHP_INI_SCAN_DIR\ast.ini"
  Add-Content $PHP_INI $AST_INI
}

# Install cacert

if (!(test-path "$SCRIPT_DIR_LOCAL\apps\cacert\current"))
{
  Invoke-Expression "&'$SCOOP_EXE' install cacert"

  $CACERT_PATH = "$SCRIPT_DIR_LOCAL\apps\cacert\current\cacert.pem"

  $CACERT_INI = @"
curl.cainfo=$CACERT_PATH
openssl.cafile=$CACERT_PATH
"@
  #Write-Output $CACERT_INI | Out-File -FilePath "$PHP_INI_SCAN_DIR\cacert.ini"
  Add-Content $PHP_INI $CACERT_INI
}

# Install composer

if (!(test-path "$SCRIPT_DIR_LOCAL\apps\composer\current"))
{
  Invoke-Expression "&'$SCOOP_EXE' install composer"
}
$COMPOSER_HOME_DIR = "$SCRIPT_DIR_LOCAL\persist\composer\home"
[environment]::setEnvironmentVariable($COMPOSER_HOME_DIR, 'User')

# Install shellcheck (https://github.com/koalaman/shellcheck)

if (!(test-path "$SCRIPT_DIR_LOCAL\apps\shellcheck\current"))
{
  Invoke-Expression "&'$SCOOP_EXE' install shellcheck"
}

# Install hadolint (https://github.com/hadolint/hadolint)

if (!(test-path "$SCRIPT_DIR_LOCAL\apps\hadolint\current"))
{
  Invoke-Expression "&'$SCOOP_EXE' install hadolint"
}

# Install BatCodeCheck (https://www.robvanderwoude.com/battech_batcodecheck.php)

if (!(test-path "$SCRIPT_DIR_LOCAL\shims\BatCodeCheck.exe"))
{
  $BAD_CODE_CHECK_URL = "https://www.robvanderwoude.com/files/batcodecheck.zip"
  $BAD_CODE_CHECK_DIR = "$SCRIPT_DIR\extern\batcodecheck"
  If (!(test-path "$BAD_CODE_CHECK_DIR"))
  {
    New-Item -ItemType Directory -Force -Path "$BAD_CODE_CHECK_DIR" *>$null
  }
  $BAD_CODE_CHECK_ZIP = "batcodecheck.zip"
  Start-Process $ARIA2_EXE -NoNewWindow -Wait -ArgumentList "--dir=$BAD_CODE_CHECK_DIR", "--out=$BAD_CODE_CHECK_ZIP", $BAD_CODE_CHECK_URL

  Unzip "$BAD_CODE_CHECK_DIR\$BAD_CODE_CHECK_ZIP" "$BAD_CODE_CHECK_DIR"

  Copy-Item "$BAD_CODE_CHECK_DIR\BatCodeCheck.exe" "$SCRIPT_DIR_LOCAL\shims\BatCodeCheck.exe"

  Remove-Item $BAD_CODE_CHECK_DIR -Recurse -Force
}

# Check all istalled application by virus

Invoke-Expression "&'$SCOOP_EXE' virustotal *"

# Clean-up

Invoke-Expression "&'$SCOOP_EXE' cleanup *"
Invoke-Expression "&'$SCOOP_EXE' cache rm *"

# Install composer parallel install plugin (https://github.com/hirak/prestissimo)

if (!(test-path "$COMPOSER_HOME_DIR\vendor\hirak\prestissimo"))
{
  Write-Output "Installing composer plugin for parallel install..."
  Invoke-Expression "&'$COMPOSER_EXE' -q global require hirak/prestissimo"
}

# Change current directory to root

Set-Location $ROOT_DIR

# Install composer envarenment

Invoke-Expression "&'$COMPOSER_EXE' install"

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
