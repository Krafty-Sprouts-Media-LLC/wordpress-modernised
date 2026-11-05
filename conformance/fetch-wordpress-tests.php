<?php

declare(strict_types=1);

/**
 * Downloads WordPress 7.1.4's tests (and its wp-includes, for reference) into
 * conformance/wordpress-develop, pinned to the exact commit, and refuses any
 * checkout that is not at that commit.
 */
const TAG = '7.1.4';
const SHA = '0afb74de8c3fe4c1869e44b62b0418d67b11aa4c';
const REPOSITORY = 'https://github.com/WordPress/wordpress-develop.git';

$target = __DIR__.'/wordpress-develop';

/**
 * Runs a git command and returns its trimmed output, or exits on failure.
 *
 * @param  list<string>  $arguments
 */
function git(array $arguments): string
{
    $command = 'git '.implode(' ', array_map('escapeshellarg', $arguments)).' 2>&1';
    exec($command, $output, $status);

    if ($status !== 0) {
        fwrite(STDERR, "git failed: {$command}\n".implode("\n", $output)."\n");
        exit(1);
    }

    return trim(implode("\n", $output));
}

if (is_dir($target)) {
    $head = git(['-C', $target, 'rev-parse', 'HEAD']);

    if ($head === SHA) {
        echo 'already at '.TAG.PHP_EOL;
        exit(0);
    }

    fwrite(STDERR, 'wordpress-develop is not at '.TAG.'; delete conformance/wordpress-develop and re-run'.PHP_EOL);
    exit(1);
}

git(['clone', '--depth', '1', '--branch', TAG, '--filter=blob:none', '--sparse', REPOSITORY, $target]);
git(['-C', $target, 'sparse-checkout', 'set', 'tests/phpunit/includes', 'tests/phpunit/tests', 'src/wp-includes']);

$head = git(['-C', $target, 'rev-parse', 'HEAD']);

if ($head !== SHA) {
    fwrite(STDERR, "Fetched {$head}, expected ".SHA.'. Refusing to use it.'.PHP_EOL);
    exit(1);
}

echo 'fetched WordPress '.TAG.' ('.SHA.')'.PHP_EOL;
