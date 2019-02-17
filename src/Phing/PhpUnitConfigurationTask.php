<?php

declare(strict_types=1);

namespace DrupalProject\Phing;

use RuntimeException;
use Task;

/**
 * A Phing task to generate a configuration file for PHPUnit.
 */
class PhpUnitConfigurationTask extends Task {

  /**
   * The path to the template that is used as a basis for the generated file.
   *
   * @var string
   */
  private $distFile = '';

  /**
   * The path to the configuration file to generate.
   *
   * @var string
   */
  private $configFile = '';

  /**
   * Directories containing tests to run.
   *
   * @var array
   */
  private $directories = [];

  /**
   * Test files to run.
   *
   * @var array
   */
  private $files = [];

  /**
   * The name to give to the test suite.
   *
   * @var string
   */
  private $testSuiteName = 'project';

  /**
   * The base URL to use in functional tests.
   *
   * @var string
   */
  private $baseUrl = 'http://localhost';

  /**
   * The database URL to use in kernel tests and functional tests.
   *
   * @var string
   */
  private $dbUrl = 'mysql://root@localhost/db';

  /**
   * The path to the directory where HTML output from browsertests is stored.
   *
   * @var string
   */
  private $browserTestOutputDirectory = '';

  /**
   * The path to the file that lists HTML output from browsertests.
   *
   * @var string
   */
  private $browserTestOutputFile = '';

  /**
   * Configures PHPUnit.
   */
  public function main(): void {
    // Check if all required data is present.
    $this->checkRequirements();

    // Load the template file.
    $document = new \DOMDocument('1.0', 'UTF-8');
    $document->preserveWhiteSpace = FALSE;
    $document->formatOutput = TRUE;
    $document->load($this->distFile);

    // Set the base URL.
    $this->setEnvironmentVariable('SIMPLETEST_BASE_URL', $this->baseUrl, $document);

    // Set the database URL.
    $this->setEnvironmentVariable('SIMPLETEST_DB', $this->dbUrl, $document);

    // Set the path to the browser test output directory.
    $this->setEnvironmentVariable('BROWSERTEST_OUTPUT_DIRECTORY', $this->browserTestOutputDirectory, $document);

    // Set the path to the browser test output file.
    $this->setEnvironmentVariable('BROWSERTEST_OUTPUT_FILE', $this->browserTestOutputFile, $document);

    // Add a test suite for the Drupal project.
    $testSuite = $document->createElement('testsuite');
    $testSuite->setAttribute('name', $this->testSuiteName);

    // Append the list of test files.
    foreach ($this->files as $file) {
      $element = $document->createElement('file', $file);
      $testSuite->appendChild($element);
    }

    // Append the list of test directories.
    foreach ($this->directories as $directory) {
      $element = $document->createElement('directory', $directory);
      $testSuite->appendChild($element);
    }

    // Insert the test suite in the list of test suites.
    $testSuites = $document->getElementsByTagName('testsuites')->item(0);
    if ($testSuites === NULL) {
      throw new RuntimeException('Test suites cant find.');
    }
    $testSuites->appendChild($testSuite);

    // Save the file.
    file_put_contents($this->configFile, $document->saveXML());
  }

  /**
   * Sets the value of a pre-existing environment variable.
   *
   * @param string $variableName
   *   The name of the environment variable for which to set the value.
   * @param string $value
   *   The value to set.
   * @param \DOMDocument $document
   *   The document in which the change should take place.
   */
  protected function setEnvironmentVariable($variableName, $value, \DOMDocument $document): void {
    /** @var \DOMElement $element */
    foreach ($document->getElementsByTagName('env') as $element) {
      if ($element->getAttribute('name') === $variableName) {
        $element->setAttribute('value', $value);
        break;
      }
    }
  }

  /**
   * Checks if all properties required for generating the config are present.
   *
   * @throws \BuildException
   *   Thrown when a required property is not present.
   */
  protected function checkRequirements(): void {
    $requiredProperties = ['configFile', 'distFile'];
    foreach ($requiredProperties as $requiredProperty) {
      if (empty($this->$requiredProperty)) {
        throw new \BuildException("Missing required property '$requiredProperty'.");
      }
    }
  }

  /**
   * Sets the path to the template of the configuration file.
   *
   * @param string $distFile
   *   The path to the template of the configuration file.
   */
  public function setDistFile(string $distFile): void {
    $this->distFile = $distFile;
  }

  /**
   * Sets the path to the configuration file to generate.
   *
   * @param string $configFile
   *   The path to the configuration file to generate.
   */
  public function setConfigFile($configFile): void {
    $this->configFile = $configFile;
  }

  /**
   * Sets the list of directories containing test files to execute.
   *
   * @param string $directories
   *   A list of directory paths, delimited by spaces, commas or semicolons.
   */
  public function setDirectories($directories): void {
    $this->directories = [];
    $token = ' ,;';
    $directory = strtok($directories, $token);
    while ($directory !== FALSE) {
      $this->directories[] = $directory;
      $directory = strtok($token);
    }
  }

  /**
   * Sets the list of test files to execute.
   *
   * @param string $files
   *   A list of file paths, delimited by spaces, commas or semicolons.
   */
  public function setFiles($files): void {
    $this->files = [];
    $token = ' ,;';
    $file = strtok($files, $token);
    while ($file !== FALSE) {
      $this->files[] = $file;
      $file = strtok($token);
    }
  }

  /**
   * Sets the name of the test suite.
   *
   * @param string $testSuiteName
   *   The name of the test suite.
   */
  public function setTestSuiteName($testSuiteName): void {
    $this->testSuiteName = $testSuiteName;
  }

  /**
   * Sets the base URL.
   *
   * @param string $baseUrl
   *   The base URL.
   */
  public function setBaseUrl($baseUrl): void {
    $this->baseUrl = $baseUrl;
  }

  /**
   * Sets the database URL.
   *
   * @param string $dbUrl
   *   The database URL.
   */
  public function setDbUrl($dbUrl): void {
    $this->dbUrl = $dbUrl;
  }

  /**
   * Sets the path to the browser test output directory.
   *
   * @param string $outputDirectory
   *   The path to the directory.
   */
  public function setBrowserTestOutputDirectory($outputDirectory): void {
    $this->browserTestOutputDirectory = $outputDirectory;
  }

  /**
   * Sets the path to the browser test output file.
   *
   * @param string $outputFile
   *   The path to the file.
   */
  public function setBrowserTestOutputFile($outputFile): void {
    $this->browserTestOutputFile = $outputFile;
  }

}
