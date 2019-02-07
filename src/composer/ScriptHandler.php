<?php

namespace DrupalProject\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use DrupalFinder\DrupalFinder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;
use Webmozart\PathUtil\Path;

/**
 * Composer classmap.
 */
class ScriptHandler {

  /**
   * Create required files.
   *
   * @param \Composer\Script\Event $event
   *   Event.
   *
   * @throws \Exception
   *
   * @psalm-suppress UnresolvableInclude
   * @psalm-suppress UndefinedConstant
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

    // Prepare the settings file for installation.
    if (!$fileSystem->exists($drupalRoot . '/sites/default/settings.php') && $fileSystem->exists($drupalRoot . '/sites/default/default.settings.php')) {
      $fileSystem->copy($drupalRoot . '/sites/default/default.settings.php', $drupalRoot . '/sites/default/settings.php');
      /* @noinspection PhpIncludeInspection */
      require_once $drupalRoot . '/core/includes/bootstrap.inc';
      /* @noinspection PhpIncludeInspection */
      require_once $drupalRoot . '/core/includes/install.inc';
      $settings['config_directories'] = [
        CONFIG_SYNC_DIRECTORY => (object) [
          'value' => Path::makeRelative($drupalFinder->getComposerRoot() . '/config/sync', $drupalRoot),
          'required' => TRUE,
        ],
      ];
      drupal_rewrite_settings($settings, $drupalRoot . '/sites/default/settings.php');
      $fileSystem->chmod($drupalRoot . '/sites/default/settings.php', 0666);
      $event->getIO()->write('Create a sites/default/settings.php file with chmod 0666');
    }

    // Create the files directory with chmod 0777.
    if (!$fileSystem->exists($drupalRoot . '/sites/default/files')) {
      $oldMask = umask(0);
      $fileSystem->mkdir($drupalRoot . '/sites/default/files');
      umask($oldMask);
      $event->getIO()->write('Create a sites/default/files directory with chmod 0777');
    }

    // Create the config/sync directory (with chmod 0777)
    // ref CONFIG_SYNC_DIRECTORY above.
    if (!$fileSystem->exists($drupalFinder->getComposerRoot() . '/config/sync')) {
      $oldMask = umask(0);
      $fileSystem->mkdir($drupalFinder->getComposerRoot() . '/config/sync');
      umask($oldMask);
      $event->getIO()->write('Create a ../config/sync directory with chmod 0777');
    }

    $globalCodeSnifferConfigPath = $drupalFinder->getComposerRoot() . '/vendor/squizlabs/php_codesniffer/CodeSniffer.conf';
    if (!$fileSystem->exists($globalCodeSnifferConfigPath)) {
      $configFile = $drupalFinder->getComposerRoot() . '/phpcs.xml';
      $configFile = str_replace('\\', '/', $configFile);
      $globalConfig = '<?php' . PHP_EOL . '$phpCodeSnifferConfig=[\'default_standard\'=>\'' . $configFile . '\'];' . PHP_EOL;
      $fileSystem->dumpFile($globalCodeSnifferConfigPath, $globalConfig);
      $event->getIO()->write('Create a codesniffer global config file');
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
    if (!$fileSystem->exists($initEnvPath)) {
      return;
    }

    if (!($phpPath = (new PhpExecutableFinder())->find())) {
      throw new \RuntimeException('The php executable could not be found, add it to your PATH environment variable and try again');
    }
    $phpPath = dirname($phpPath);

    $initEnvContent = $isWindows ?
      'set PATH='
        . $drupalFinder->getComposerRoot() . '\vendor\bin;'
        . $drupalFinder->getComposerRoot() . '\node_modules\.bin;'
        . $phpPath . ';%PATH%' . PHP_EOL :
      'export PATH="'
        . $drupalFinder->getComposerRoot() . '/vendor/bin:'
        . $drupalFinder->getComposerRoot() . '/node_modules/.bin:'
        . $phpPath . '$PATH"' . PHP_EOL;

    $fileSystem->dumpFile($initEnvPath, $initEnvContent);

    if (!$isWindows) {
      chmod($initEnvPath, 755);
    }

    $event->getIO()->write('Create init environment file');
  }

  /**
   * Run check with json lint.
   *
   * @see https://www.npmjs.com/package/jsonlint
   */
  public static function runJsonLint(): void {
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $finder = new Finder();
    $finder->files()
      ->in($drupalFinder->getComposerRoot())
      ->exclude('.git')
      ->exclude('.idea')
      ->exclude('node_modules')
      ->exclude('vendor')
      ->exclude('web/core/modules/system/tests/fixtures/HtaccessTest')
      // ->exclude('web/core')
      // ->exclude('web/modules/contrib')
      // ->exclude('web/themes/contrib')
      // ->exclude('web/profiles/contrib')
      ->name('*.json');
    foreach ($finder as $file) {
      exec('jsonlint ' . $file->getRealPath());
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

    $npmPath = $drupalFinder->getComposerRoot() . '/vendor/bin/npm';
    if (stripos(PHP_OS, 'WIN') === 0) {
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
      exec('npm install --no-progress');
      return;
    }

    $event->getIO()->write('Call npm install (without dev dependencies)');
    exec('npm install --only=prod --no-progress');
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
    if (preg_match('/^[0-9a-f]{40}$/i', $version)) {
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
