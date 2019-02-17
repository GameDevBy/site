<?php

declare(strict_types=1);

namespace DrupalProject\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use DrupalFinder\DrupalFinder;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;
use Webmozart\PathUtil\Path;

/**
 * Composer classmap.
 */
class ScriptHandler {

  /**
   * Local function for suppress warning.
   *
   * @param string $drupalRoot
   *   Path to drupal root.
   * @param string $syncPath
   *   Path to sync folder.
   *
   * @psalm-suppress UnresolvableInclude
   * @psalm-suppress UndefinedConstant
   * @suppress PhanUndeclaredVariableDim
   */
  private static function createSettings(string $drupalRoot, string $syncPath): void {
    /* @noinspection PhpIncludeInspection */
    require_once $drupalRoot . '/core/includes/bootstrap.inc';
    /* @noinspection PhpIncludeInspection */
    require_once './web/core/includes/install.inc';

    $settings['config_directories'] = [
      CONFIG_SYNC_DIRECTORY => (object) [
        'value' => Path::makeRelative($syncPath, $drupalRoot),
        'required' => TRUE,
      ],
    ];

    try {
      drupal_rewrite_settings($settings, $drupalRoot . '/sites/default/settings.php');
    }
    catch (\Exception $ex) {
      throw new RuntimeException($ex);
    }
  }

  /**
   * Create required files.
   *
   * @param \Composer\Script\Event $event
   *   Event.
   */
  public static function createRequiredFiles(Event $event): void {
    $fileSystem = new Filesystem();
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $drupalRoot = $drupalFinder->getDrupalRoot();

    $dirs = [
      'modules',
      'profiles',
      'themes',
    ];

    // Required for unit testing.
    foreach ($dirs as $dir) {
      if (!$fileSystem->exists($drupalRoot . '/' . $dir)) {
        $fileSystem->mkdir($drupalRoot . '/' . $dir);
        $fileSystem->touch($drupalRoot . '/' . $dir . '/.gitkeep');
      }
    }

    // Create the config/sync directory (with chmod 0777)
    // ref CONFIG_SYNC_DIRECTORY above.
    $syncPath = $drupalFinder->getComposerRoot() . '/config/sync';
    if (!$fileSystem->exists($syncPath)) {
      $fileSystem->mkdir($syncPath);
      $fileSystem->chmod($syncPath, 0777);
      $event->getIO()->write('Create a "' . $syncPath . '" directory with chmod 0777');
    }

    // Prepare the settings file for installation.
    $settingsPath = $drupalRoot . '/sites/default/default.settings.php';
    $defaultSettingsPath = $drupalRoot . '/sites/default/default.settings.php';
    if (!$fileSystem->exists($settingsPath) && $fileSystem->exists($defaultSettingsPath)) {
      $fileSystem->copy($defaultSettingsPath, $settingsPath);
      self::createSettings($drupalRoot, $syncPath);
      $fileSystem->chmod($settingsPath, 0666);
      $event->getIO()->write('Create a "' . $settingsPath . '" file with chmod 0666');
    }

    // Create the files directory with chmod 0777.
    $filesPath = $drupalRoot . '/sites/default/files';
    if (!$fileSystem->exists($filesPath)) {
      $fileSystem->mkdir($filesPath);
      $fileSystem->chmod($filesPath, 0777);
      $event->getIO()->write('Create a "' . $filesPath . '" directory with chmod 0777');
    }

    $globalCodeSnifferConfigPath = $drupalFinder->getComposerRoot() . '/vendor/squizlabs/php_codesniffer/CodeSniffer.conf';
    if (!$fileSystem->exists($globalCodeSnifferConfigPath)) {
      $configFile = $drupalFinder->getComposerRoot() . '/phpcs.xml';
      $configFile = str_replace('\\', '/', $configFile);
      $globalConfig = '<?php' . PHP_EOL . '$phpCodeSnifferConfig=[\'default_standard\'=>\'' . $configFile . '\'];' . PHP_EOL;
      $fileSystem->dumpFile($globalCodeSnifferConfigPath, $globalConfig);
      $event->getIO()->write('Create a codesniffer global config file: "' . $globalCodeSnifferConfigPath . '"');
    }

    self::createInitEnvFile($fileSystem, $drupalFinder, $event);
  }

  /**
   * Create init environment file.
   *
   * @param \Symfony\Component\Filesystem\Filesystem $fileSystem
   *   File system.
   * @param \DrupalFinder\DrupalFinder $drupalFinder
   *   Drupal finder.
   * @param \Composer\Script\Event $event
   *   Event.
   */
  private static function createInitEnvFile(Filesystem $fileSystem, DrupalFinder $drupalFinder, Event $event): void {
    $initEnvPath = $drupalFinder->getComposerRoot() . '/init_env';
    $initEnvPath = str_replace('\\', '/', $initEnvPath);
    $isWindows = (stripos(PHP_OS, 'WIN') === 0);
    if ($isWindows) {
      $initEnvPath .= '.bat';
    }
    if ($fileSystem->exists($initEnvPath)) {
      return;
    }

    if (($phpPath = (new PhpExecutableFinder())->find()) === FALSE) {
      throw new \RuntimeException('The php executable could not be found, add it to your PATH environment variable and try again');
    }
    $phpPath = dirname($phpPath);

    $initEnvContent = $isWindows ?
      'set COMPOSER_HOME=' . $drupalFinder->getComposerRoot() . '\windows\app\local\persist\composer\home' . PHP_EOL
      . 'set PHP_INI_SCAN_DIR=' . $drupalFinder->getComposerRoot() . '\windows\app\local\apps\php-nts\current\cli' . PHP_EOL
      . 'set SCOOP=' . $drupalFinder->getComposerRoot() . '\windows\app\local' . PHP_EOL
      . 'set SCOOP_GLOBAL=' . $drupalFinder->getComposerRoot() . '\windows\app\global' . PHP_EOL
      . 'set PHAN_DISABLE_XDEBUG_WARN=1' . PHP_EOL
      . 'set PATH='
      . $drupalFinder->getComposerRoot() . '\vendor\bin;'
      . $drupalFinder->getComposerRoot() . '\node_modules\.bin;'
      . $drupalFinder->getComposerRoot() . '\windows\app\local\shims;%PATH%'
      . PHP_EOL :
      'export PHAN_DISABLE_XDEBUG_WARN=1' . PHP_EOL
      . 'PATH="'
      . $drupalFinder->getComposerRoot() . '/vendor/bin:'
      . $drupalFinder->getComposerRoot() . '/node_modules/.bin:'
      . $phpPath . ':$PATH"' . PHP_EOL
      . 'export PATH' . PHP_EOL;

    $fileSystem->dumpFile($initEnvPath, $initEnvContent);

    if (!$isWindows) {
      $fileSystem->chmod($initEnvPath, 0755);
    }

    $event->getIO()->write('Create init environment file');
  }

  /**
   * Run check with json lint.
   *
   * @param \Composer\Script\Event $event
   *   Event.
   *
   * @see https://www.npmjs.com/package/jsonlint
   */
  public static function runJsonLint(Event $event): void {
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $finder = new Finder();
    $finder->files()
      ->in($drupalFinder->getComposerRoot())
      ->exclude('.git')
      ->exclude('.idea')
      ->exclude('node_modules')
      ->exclude('vendor')
      ->exclude('windows/app')
      ->exclude('web/core/modules/system/tests/fixtures/HtaccessTest')
      // ->exclude('web/core')
      // ->exclude('web/modules/contrib')
      // ->exclude('web/themes/contrib')
      // ->exclude('web/profiles/contrib')
      ->name('*.json');
    foreach ($finder as $file) {
      $filePath = $file->getRealPath();
      $event->getIO()->write('Jsonlint checking: ' . $filePath);
      $output=[];
      $returnValue=1;
      exec('jsonlint ' . $filePath . ' 2>&1', $output, $returnValue);
      if ($returnValue != 0) {
        throw new RuntimeException('Error with style in json: ' . $filePath . PHP_EOL . implode(PHP_EOL, $output));
      }
    }
  }

  /**
   * Check shell scripts.
   *
   * @param \Composer\Script\Event $event
   *   Event.
   *
   * @see https://github.com/koalaman/shellcheck
   */
  public static function runShellCheck(Event $event): void {
    $isWindows = (stripos(PHP_OS, 'WIN') === 0);
    $color = ' --color=always ';
    if ($isWindows) {
      $color .= ' --color=never ';
    }

    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $finder = new Finder();
    $finder->files()
      ->in($drupalFinder->getComposerRoot())
      ->exclude('.git')
      ->exclude('.idea')
      ->exclude('node_modules')
      ->exclude('vendor')
      ->exclude('windows/app')
      // ->exclude('web/core')
      // ->exclude('web/modules/contrib')
      // ->exclude('web/themes/contrib')
      // ->exclude('web/profiles/contrib')
      ->name('/^[^.]*$/')
      ->contains('#!/usr/bin/env bash');
    foreach ($finder as $file) {
      $filePath = $file->getRealPath();
      $event->getIO()->write('Shell checking: ' . $filePath);
      $output=[];
      $returnValue=1;
      exec('shellcheck --exclude=SC1017,SC1091 --check-sourced' . $color . '--shell=bash --severity=style ' . $filePath . ' 2>&1', $output, $returnValue);
      if ($returnValue != 0) {
        throw new RuntimeException('Error with style in shell script: ' . $filePath . PHP_EOL . implode(PHP_EOL, $output));
      }
    }
  }

  /**
   * Run check with power shell checker.
   *
   * @param \Composer\Script\Event $event
   *   Event.
   *
   * @see https://github.com/PowerShell/PSScriptAnalyzer
   */
  public static function runPowerScriptCheck(Event $event): void {
    $isWindows = (stripos(PHP_OS, 'WIN') === 0);

    if (!$isWindows) {
      return;
    }

    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $finder = new Finder();
    $finder->files()
      ->in($drupalFinder->getComposerRoot())
      ->exclude('.git')
      ->exclude('.idea')
      ->exclude('node_modules')
      ->exclude('vendor')
      ->exclude('windows/app')
      // ->exclude('web/core')
      // ->exclude('web/modules/contrib')
      // ->exclude('web/themes/contrib')
      // ->exclude('web/profiles/contrib')
      ->name('*.ps1');
    foreach ($finder as $file) {
      $filePath = $file->getRealPath();
      $event->getIO()->write('PowerScript checking: ' . $filePath);
      $execStr = 'powershell -Command "Invoke-ScriptAnalyzer -Setting \'' . $drupalFinder->getComposerRoot() . '\windows\PSScriptAnalyzerSettings.psd1\' -Path \'' . $filePath . '\'"';
      $output=[];
      $returnValue=1;
      exec($execStr . ' 2>&1', $output, $returnValue);
      $output = implode(PHP_EOL, $output);
      if ($returnValue != 0 || strpos($output, 'RuleName') !== FALSE) {
        throw new RuntimeException('Error with style in PowerScript: ' . $filePath . PHP_EOL . $output);
      }
    }
  }

  /**
   * Run check with bat checker.
   *
   * @param \Composer\Script\Event $event
   *   Event.
   *
   * @see https://www.robvanderwoude.com/battech_batcodecheck.php
   */
  public static function runBatCheck(Event $event): void {
    $isWindows = (stripos(PHP_OS, 'WIN') === 0);

    if (!$isWindows) {
      return;
    }

    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $finder = new Finder();

    $rootDir = $drupalFinder->getComposerRoot();

    $finder->files()
      ->in($rootDir)
      ->exclude('.git')
      ->exclude('.idea')
      ->exclude('node_modules')
      ->exclude('vendor')
      ->exclude('windows/app')
      // ->exclude('web/core')
      // ->exclude('web/modules/contrib')
      // ->exclude('web/themes/contrib')
      // ->exclude('web/profiles/contrib')
      ->name('*.bat')
      ->name('*.cmd')
      ->notName('init_env.bat');
    foreach ($finder as $file) {
      $filePath = $file->getRealPath();
      $event->getIO()->write('BatCodeCheck checking: ' . $filePath);
      $execStr = 'call ' . $rootDir . '/windows/app/local/shims/BatCodeCheck.exe ' . $filePath;
      $output=[];
      $returnValue=1;
      exec($execStr, $output, $returnValue);
      if ($returnValue != 0) {
        $output = implode(PHP_EOL, $output);
        throw new RuntimeException('Error with style in BatCodeCheck: ' . $filePath . PHP_EOL . $output);
      }
    }
  }

  /**
   * Run check with phan.
   */
  public static function runPhan(): void {
    $isWindows = (stripos(PHP_OS, 'WIN') === 0);

    if ($isWindows) {
      exec('SET PHAN_DISABLE_XDEBUG_WARN=1 && phan -p --require-config-exists 2>&1', $output, $returnValue);
    }
    else {
      exec('export PHAN_DISABLE_XDEBUG_WARN=1 && phan -p --color --require-config-exists 2>&1', $output, $returnValue);
    }

    if ($returnValue != 0) {
      throw new RuntimeException(implode(PHP_EOL, $output));
    }
  }

  /**
   * Run check with eslint.
   */
  public static function runEslint(): void {
    $isWindows = (stripos(PHP_OS, 'WIN') === 0);

    if ($isWindows) {
      exec('eslint --no-color --cache -c ./web/core/.eslintrc.json . 2>&1', $output, $returnValue);
    }
    else {
      exec('eslint --color --cache -c ./web/core/.eslintrc.json . 2>&1', $output, $returnValue);
    }

    if ($returnValue != 0) {
      throw new RuntimeException(implode(PHP_EOL, $output));
    }
  }

  /**
   * Run check with stylelint.
   */
  public static function runStylelint(): void {
    $isWindows = (stripos(PHP_OS, 'WIN') === 0);

    if ($isWindows) {
      exec('stylelint --no-color --cache --config ./web/core/.stylelintrc.json "**/*.css" "**/*.scss" "**/*.sass"  "**/*.less" "**/*.sss 2>&1', $output, $returnValue);
    }
    else {
      exec('stylelint --color --cache --config ./web/core/.stylelintrc.json "**/*.css" "**/*.scss" "**/*.sass"  "**/*.less" "**/*.sss 2>&1', $output, $returnValue);
    }

    if ($returnValue != 0) {
      throw new RuntimeException(implode(PHP_EOL, $output));
    }
  }

  /**
   * Run check with phpcs.
   */
  public static function runPhpcs(): void {
    $isWindows = (stripos(PHP_OS, 'WIN') === 0);

    if ($isWindows) {
      exec('phpcs -s -p --no-colors 2>&1', $output, $returnValue);
    }
    else {
      exec('phpcs -s -p --colors 2>&1', $output, $returnValue);
    }

    if ($returnValue != 0) {
      throw new RuntimeException(implode(PHP_EOL, $output));
    }
  }

  /**
   * Run nmp install.
   *
   * @param \Composer\Script\Event $event
   *   Event.
   */
  public static function npmInstall(Event $event): void {
    $devMode = $event->isDevMode();

    $fileSystem = new Filesystem();
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());

    $isWindows = (stripos(PHP_OS, 'WIN') === 0);

    $npmPath = $drupalFinder->getComposerRoot() . '/vendor/bin/npm';
    if ($isWindows) {
      $npmPath .= '.bat';
    }
    $npmPath = str_replace('\\', '/', $npmPath);
    if (!$fileSystem->exists($npmPath)) {
      $event->getIO()->write('Do nothing.');
      return;
    }

    $event->getIO()->write('NPM find by: ' . $npmPath);
    if ($devMode) {
      $event->getIO()->write('Call npm install (with dev dependencies)');
      exec('npm install');
      return;
    }

    $event->getIO()->write('Call npm install (without dev dependencies)');
    exec('npm install --only=prod');
  }

  /**
   * Checks if the installed version of Composer is compatible.
   *
   * Composer 1.0.0 and higher consider a `composer install` without having a
   * lock file present as equal to `composer update`. We do not ship with a lock
   * file to avoid merge conflicts downstream, meaning that if a project is
   * installed with an older version of Composer the scaffolding of Drupal will
   * not be triggered. We check this here instead of in drupal-scaffold to be
   * able to give immediate feedback to the end user, rather than failing the
   * installation after going through the lengthy process of compiling and
   * downloading the Composer dependencies.
   *
   * @param \Composer\Script\Event $event
   *   Event.
   *
   * @see https://github.com/composer/composer/pull/5035
   */
  public static function checkComposerVersion(Event $event): void {
    $composer = $event->getComposer();
    $output = $event->getIO();

    $version = $composer::VERSION;

    // The dev-channel of composer uses the git revision as version number,
    // try to the branch alias instead.
    if (preg_match('/^[0-9a-f]{40}$/i', $version) === 1) {
      $version = $composer::BRANCH_ALIAS_VERSION;
    }

    // If Composer is installed through git we have no easy way to determine if
    // it is new enough, just display a warning.
    if ($version === '@package_version@' || $version === '@package_branch_alias_version@') {
      $output->writeError('<warning>You are running a development version of Composer. If you experience problems, please update Composer to the latest stable version.</warning>');
      return;
    }

    if (Comparator::lessThan($version, '1.0.0')) {
      $msg = '<error>Drupal-project requires Composer version 1.0.0 or higher. Please update your Composer before continuing</error>.';
      $output->writeError($msg);
      throw new \RuntimeException($msg);
    }
  }

}
