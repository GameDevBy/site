<?php

declare(strict_types = 1);

namespace DrupalProject\composer;

use Composer\Script\Event;
use DrupalFinder\DrupalFinder;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
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
      throw new RuntimeException($ex->getMessage(), $ex->getCode(), $ex);
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

}
