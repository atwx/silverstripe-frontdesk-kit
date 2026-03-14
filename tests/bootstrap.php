<?php

/**
 * Test bootstrap for atwx/silverstripe-frontdesk-kit.
 *
 * When the module is installed inside a consuming project (the normal case),
 * this file is four directory levels below the project root:
 *
 *   <project>/vendor/atwx/silverstripe-frontdesk-kit/tests/bootstrap.php
 *
 * In that case we delegate to the project's own Silverstripe test bootstrap,
 * which already has the correct .env / DB configuration.
 *
 * When running standalone (the module checked out on its own with its own
 * `composer install`), we fall back to the module-local bootstrap instead.
 */

$projectBootstrap = __DIR__ . '/../../../../vendor/silverstripe/framework/tests/bootstrap.php';
$moduleBootstrap  = __DIR__ . '/../vendor/silverstripe/framework/tests/bootstrap.php';

if (file_exists($projectBootstrap)) {
    require $projectBootstrap;
} elseif (file_exists($moduleBootstrap)) {
    require $moduleBootstrap;
} else {
    throw new RuntimeException(
        'No Silverstripe test bootstrap found. ' .
        'Run `composer install` in the project or module root.'
    );
}
