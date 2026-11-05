<?php

declare(strict_types=1);

/**
 * Runs the checker: refreshes its packages (so the path link reflects the
 * app's current composer.json), then runs PHPUnit 9.6 with any options given,
 * for example `composer conformance -- --testsuite harness`.
 */
$composer = getenv('COMPOSER_BINARY') ?: 'composer';
$composerCommand = (str_ends_with($composer, '.phar') ? escapeshellarg(PHP_BINARY).' ' : '').escapeshellarg($composer);

passthru($composerCommand.' --working-dir='.escapeshellarg(__DIR__).' update --no-interaction --quiet', $status);

if ($status !== 0) {
    exit($status);
}

$arguments = array_map('escapeshellarg', array_slice($argv, 1));
$phpunit = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/vendor/bin/phpunit').' -c '.escapeshellarg(__DIR__.'/phpunit.xml.dist');

passthru($phpunit.($arguments ? ' '.implode(' ', $arguments) : ''), $status);

exit($status);
