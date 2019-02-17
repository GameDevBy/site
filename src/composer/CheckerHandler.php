<?php

declare(strict_types = 1);

namespace DrupalProject\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use DrupalFinder\DrupalFinder;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * Composer check handler.
 */
class CheckerHandler {

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
      ->exclude('common')
      ->exclude('web/core/modules/system/tests/fixtures/HtaccessTest')
      // ->exclude('web/core')
      // ->exclude('web/modules/contrib')
      // ->exclude('web/themes/contrib')
      // ->exclude('web/profiles/contrib')
      ->name('*.json');
    foreach ($finder as $file) {
      $filePath = $file->getRealPath();
      $event->getIO()->write('Jsonlint checking: ' . $filePath);
      $output = [];
      $returnValue = 1;
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
      ->exclude('common')
      // ->exclude('web/core')
      // ->exclude('web/modules/contrib')
      // ->exclude('web/themes/contrib')
      // ->exclude('web/profiles/contrib')
      ->name('/^[^.]*$/')
      ->contains('#!/usr/bin/env bash');
    foreach ($finder as $file) {
      $filePath = $file->getRealPath();
      $event->getIO()->write('Shell checking: ' . $filePath);
      $output = [];
      $returnValue = 1;
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
      ->exclude('common')
      // ->exclude('web/core')
      // ->exclude('web/modules/contrib')
      // ->exclude('web/themes/contrib')
      // ->exclude('web/profiles/contrib')
      ->name('*.ps1');
    foreach ($finder as $file) {
      $filePath = $file->getRealPath();
      $event->getIO()->write('PowerScript checking: ' . $filePath);
      $execStr = 'powershell -Command "Invoke-ScriptAnalyzer -Setting \'' . $drupalFinder->getComposerRoot() . '\windows\PSScriptAnalyzerSettings.psd1\' -Path \'' . $filePath . '\'"';
      $output = [];
      $returnValue = 1;
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
      ->exclude('common')
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
      $output = [];
      $returnValue = 1;
      exec($execStr, $output, $returnValue);
      if ($returnValue != 0) {
        $output = implode(PHP_EOL, $output);
        throw new RuntimeException('Error with style in BatCodeCheck: ' . $filePath . PHP_EOL . $output);
      }
    }
  }

  /**
   * Run check with hadolint.
   *
   * @param \Composer\Script\Event $event
   *   Event.
   *
   * @see https://github.com/hadolint/hadolint
   */
  public static function runDockerCheck(Event $event): void {
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
      ->exclude('common')
      // ->exclude('web/core')
      // ->exclude('web/modules/contrib')
      // ->exclude('web/themes/contrib')
      // ->exclude('web/profiles/contrib')
      ->name('Dockerfile');
    foreach ($finder as $file) {
      $filePath = $file->getRealPath();
      $event->getIO()->write('HadoLint checking: ' . $filePath);
      $execStr = 'hadolint ' . $filePath;
      $output = [];
      $returnValue = 1;
      exec($execStr, $output, $returnValue);
      if ($returnValue != 0) {
        $output = implode(PHP_EOL, $output);
        throw new RuntimeException('Error with style in docker file: ' . $filePath . PHP_EOL . $output);
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
