<?php

declare(strict_types=1);

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines generic step definitions.
 *
 * TODO Use --snippets-for CLI option instead.
 *
 * @noinspection PhpUndefinedClassInspection
 */
class FeatureContext extends RawDrupalContext {

  /**
   * Checks that a 403 Access Denied error occurred.
   *
   * @Then I should get an access denied error
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function assertAccessDenied(): void {
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Checks that a given image is present in the page.
   *
   * @param string $filename
   *   File name.
   *
   * @Then I (should )see the image :filename
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function assertImagePresent(string $filename): void {
    // Drupal appends an underscore and a number to the filename when duplicate
    // files are uploaded, for example when a test is run more than once.
    // We split up the filename and extension and match for both.
    $parts = pathinfo($filename);
    $extension = $parts['extension'];
    $filename = $parts['filename'];
    $this->assertSession()->elementExists('css', "img[src$='.$extension'][src*='$filename']");
  }

  /**
   * Checks that a given image is not present in the page.
   *
   * @param string $filename
   *   File name.
   *
   * @Then I should not see the image :filename
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function assertImageNotPresent(string $filename): void {
    // Drupal appends an underscore and a number to the filename when duplicate
    // files are uploaded, for example when a test is run more than once.
    // We split up the filename and extension and match for both.
    $parts = pathinfo($filename);
    $extension = $parts['extension'];
    $filename = $parts['filename'];
    $this->assertSession()->elementNotExists('css', "img[src$='.$extension'][src*='$filename']");
  }

}
