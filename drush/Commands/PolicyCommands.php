<?php

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Utils\StringUtils;

/**
 * Edit this file to reflect your organization's needs.
 */
class PolicyCommands extends DrushCommands {

  /**
   * Prevent catastrophic braino when syncing databases.
   *
   * This file has to be local to the machine
   * that initiates the sql:sync command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   Command data.
   *
   * @hook validate sql:sync
   */
  public function sqlSyncValidate(CommandData $commandData): void {
    if ($commandData->input()->getArgument('target') === '@prod') {
      throw new \RuntimeException(StringUtils::interpolate('Per !file, you may never overwrite the production database.', ['!file' => __FILE__]));
    }
  }

  /**
   * Limit rsync operations to production site.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   Command data.
   *
   * @hook validate core:rsync
   */
  public function rsyncValidate(CommandData $commandData): void {
    if (preg_match('/^@prod/', $commandData->input()->getArgument('target')) === 1) {
      throw new \RuntimeException(StringUtils::interpolate('Per !file, you may never rsync to the production site.', ['!file' => __FILE__]));
    }
  }

}
