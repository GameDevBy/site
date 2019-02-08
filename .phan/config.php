<?php
// phpcs:ignoreFile

/**
 * Phan config file.
 *
 * @see https://github.com/phan/phan/wiki/Phan-Config-Settings
 * @see https://github.com/phan/phan/blob/master/.phan/config.php
 */

return [
  'target_php_version' => '7.3',

  'processes' => 1, /* @see https://github.com/phan/phan/wiki/Different-Issue-Sets-On-Different-Numbers-of-CPUs */

  'unused_variable_detection' => true,
  'dead_code_detection' => true,

  'directory_list' => [
    'src',
    '.phan',
    'drush',
    'tests',
    'web/modules/custom',
    'web/themes/custom',
    'web/profiles/custom',
    'vendor/drupal/drupal-extension/src',
    'vendor/behat/mink/src',
    'vendor/behat/mink-extension/src',
    'vendor/phing/phing/classes',
    'vendor/phpunit/phpunit/src',
    'vendor/composer/composer/src',
    'vendor/composer/semver/src',
    'vendor/symfony/filesystem',
    'vendor/symfony/process',
    'vendor/symfony/finder',
    'vendor/webflo/drupal-finder/src',
    'vendor/webmozart/path-util/src',
    'vendor/drush/drush/src',
    'vendor/consolidation/annotated-command/src',
  ],

  'file_list' => [
    'web/core/includes/install.inc',
    'web/core/includes/bootstrap.inc'
  ],

  'exclude_file_regex' => '@^vendor/.*/(tests?|Tests?)/@',

  'exclude_analysis_directory_list' => [
    'vendor/',
    'web/core',
  ],

  'exception_classes_with_optional_throws_phpdoc' => [
    'LogicException',
    'RuntimeException',
    'InvalidArgumentException',
    'AssertionError',
    'TypeError',
  ],

  'suppress_issue_types' => [
    'PhanPluginNoCommentOnProtectedMethod',
    'PhanPluginDescriptionlessCommentOnProtectedMethod',
    'PhanPluginNoCommentOnPrivateMethod',
    'PhanPluginDescriptionlessCommentOnPrivateMethod',
    'PhanUnreferencedPublicMethod',
    'PhanUnreferencedClass',
  ],

  'autoload_internal_extension_signatures' => [
    'ast'         => 'vendor/phan/phan/.phan/internal_stubs/ast.phan_php',
    'ctype'       => 'vendor/phan/phan/.phan/internal_stubs/ctype.phan_php',
    'pcntl'       => 'vendor/phan/phan/.phan/internal_stubs/pcntl.phan_php',
    'posix'       => 'vendor/phan/phan/.phan/internal_stubs/posix.phan_php',
    'readline'    => 'vendor/phan/phan/.phan/internal_stubs/readline.phan_php',
    'sysvmsg'     => 'vendor/phan/phan/.phan/internal_stubs/sysvmsg.phan_php',
    'sysvsem'     => 'vendor/phan/phan/.phan/internal_stubs/sysvsem.phan_php',
    'sysvshm'     => 'vendor/phan/phan/.phan/internal_stubs/sysvshm.phan_php',
    'xdebug'      => 'vendor/phan/phan/.phan/internal_stubs/xdebug.phan_php',
  ],

  // A list of plugin files to execute
  /* @see https://github.com/phan/phan/tree/master/.phan/plugins */
  'plugins' => [

    //  Plugins Affecting Phan Analysis

    'UnusedSuppressionPlugin', // NOTE: This plugin only produces correct results when Phan is run on a single core (-j1).

    // General-Use Plugins

    'AlwaysReturnPlugin',
    'DuplicateArrayKeyPlugin',
    'PregRegexCheckerPlugin',
    'PrintfCheckerPlugin',
    'UnreachableCodePlugin',
    'InvokePHPNativeSyntaxCheckPlugin',
    'UseReturnValuePlugin',
    'PHPUnitAssertionPlugin',

    // Plugins Specific to Code Styles

    'NonBoolBranchPlugin',
    'NonBoolInLogicalArithPlugin',
    'HasPHPDocPlugin',
    'InvalidVariableIssetPlugin',
    'NoAssertPlugin',
    'NumericalComparisonPlugin',
    'PHPUnitNotDeadCodePlugin',
    'SleepCheckerPlugin',
    'UnknownElementTypePlugin',
    'DuplicateExpressionPlugin',

    // Demo plugins

    'DemoPlugin',
    'DollarDollarPlugin',

  ],

  'plugin_config' => [
    'php_native_syntax_check_max_processes' => 4,

    /* @see https://github.com/phan/phan/blob/master/.phan/plugins/UseReturnValuePlugin.php */
    // 'use_return_value_dynamic_checks' => true,

  ],

];
