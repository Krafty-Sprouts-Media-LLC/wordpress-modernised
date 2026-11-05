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

/** Folders of the checkout we use: WordPress's tests, and its source for reference. */
const PATHS = ['tests/phpunit/includes', 'tests/phpunit/tests', 'src/wp-includes', 'src/wp-admin/includes'];

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
        if (git(['-C', $target, 'status', '--porcelain']) !== '') {
            fwrite(STDERR, 'wordpress-develop has local changes; restore it with: git -C conformance/wordpress-develop checkout -- .'.PHP_EOL);
            exit(1);
        }

        // Bring an older checkout up to the current list of folders.
        $have = explode("\n", git(['-C', $target, 'sparse-checkout', 'list']));
        $missing = array_values(array_diff(PATHS, $have));

        if ($missing) {
            git(array_merge(['-C', $target, 'sparse-checkout', 'add'], $missing));
            echo 'added '.implode(', ', $missing).PHP_EOL;
        }

        echo 'already at '.TAG.PHP_EOL;
        exit(0);
    }

    fwrite(STDERR, 'wordpress-develop is not at '.TAG.'; delete conformance/wordpress-develop and re-run'.PHP_EOL);
    exit(1);
}

git(['clone', '--depth', '1', '--branch', TAG, '--filter=blob:none', '--sparse', REPOSITORY, $target]);
git(array_merge(['-C', $target, 'sparse-checkout', 'set'], PATHS));

$head = git(['-C', $target, 'rev-parse', 'HEAD']);

if ($head !== SHA) {
    fwrite(STDERR, "Fetched {$head}, expected ".SHA.'. Refusing to use it.'.PHP_EOL);
    exit(1);
}

echo 'fetched WordPress '.TAG.' ('.SHA.')'.PHP_EOL;
