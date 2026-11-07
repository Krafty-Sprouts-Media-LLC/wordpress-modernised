<?php

/**
 * The base class WordPress's own tests extend, cut down to what the areas
 * we have rebuilt need. Members are added as more WordPress test areas join
 * the checker.
 *
 * Contains code from WordPress 7.1.4: tests/phpunit/includes/abstract-testcase.php.
 */

use PHPUnit\Util\Test;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Saves and restores hook state around every test, and checks the
 * `@expectedDeprecated` and `@expectedIncorrectUsage` annotations the way
 * WordPress's own base test case does.
 */
abstract class WP_UnitTestCase extends TestCase
{
    /** @var list<string> */
    protected $expected_deprecated = [];

    /** @var array<string, string> */
    protected $caught_deprecated = [];

    /** @var list<string> */
    protected $expected_doing_it_wrong = [];

    /** @var array<string, string> */
    protected $caught_doing_it_wrong = [];

    /** @var array<string, mixed> */
    protected static $hooks_saved = [];

    public function set_up()
    {
        static $waiting = null, $diverged = null;
        $waiting ??= require dirname(__DIR__).'/waiting.php';
        $diverged ??= require dirname(__DIR__).'/diverged.php';

        $key = static::class.'::'.$this->getName(false);

        if ($reason = self::listedReason($waiting, $key)) {
            $this->markTestSkipped('Waiting: '.$reason);
        }

        if ($reason = self::listedReason($diverged, $key)) {
            $this->markTestSkipped('Diverged on purpose: '.$reason);
        }

        if (! self::$hooks_saved) {
            $this->_backup_hooks();
        }

        $this->clean_up_global_scope();

        // WordPress's set_up() registers the default post types here, which reads
        // an option and so loads every autoloaded one. Until the posts step, load
        // them directly, so tests counting queries start from the same place.
        wp_load_alloptions();

        $this->start_transaction();
        $this->expectDeprecated();
        add_filter('wp_die_handler', [$this, 'get_wp_die_handler']);
    }

    /**
     * Resets the request globals and the object cache before each test.
     */
    public function clean_up_global_scope()
    {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        self::flush_cache();
    }

    /**
     * Flushes the object cache and sets WordPress's global and
     * non-persistent cache groups again, as WordPress's bootstrap does.
     */
    public static function flush_cache()
    {
        global $wp_object_cache;

        wp_cache_flush_runtime();

        if (is_object($wp_object_cache) && method_exists($wp_object_cache, '__remoteset')) {
            $wp_object_cache->__remoteset();
        }

        wp_cache_flush();

        wp_cache_add_global_groups(
            [
                'blog-details',
                'blog-id-cache',
                'blog-lookup',
                'blog_meta',
                'global-posts',
                'networks',
                'network-queries',
                'sites',
                'site-details',
                'site-options',
                'site-queries',
                'site-transient',
                'theme_files',
                'rss',
                'users',
                'user-queries',
                'user_meta',
                'useremail',
                'userlogins',
                'userslugs',
            ]
        );

        wp_cache_add_non_persistent_groups(['counts', 'plugins', 'theme_json']);
    }

    /**
     * Flushes the object cache after each test class, as WordPress does.
     */
    public static function tear_down_after_class()
    {
        self::flush_cache();

        parent::tear_down_after_class();
    }

    /**
     * Returns the wp_die() handler used in tests.
     */
    public function get_wp_die_handler($handler)
    {
        return [$this, 'wp_die_handler'];
    }

    /**
     * Throws WPDieException instead of stopping, so tests can catch wp_die().
     */
    public function wp_die_handler($message, $title, $args)
    {
        if (is_wp_error($message)) {
            $message = $message->get_error_message();
        }

        if (! is_scalar($message)) {
            $message = '0';
        }

        $code = 0;
        if (isset($args['response'])) {
            $code = $args['response'];
        }

        throw new WPDieException($message, $code);
    }

    /**
     * The reason a test is listed in waiting.php or diverged.php, if it is.
     * Entries apply to every database, or to one database under its driver
     * name ('pgsql' => [...]); "TestClass::*" covers a whole class.
     *
     * @param  array<string, string|array<string, string>>  $list
     */
    private static function listedReason(array $list, string $key): ?string
    {
        $entries = array_merge(
            array_filter($list, 'is_string'),
            $list[DB::connection()->getDriverName()] ?? [],
        );
        $class = strstr($key, '::', true).'::*';

        return $entries[$key] ?? $entries[$class] ?? null;
    }

    public function tear_down()
    {
        // Roll back everything, even if a test committed on its own (Laravel
        // only rolls back the PDO when it is still in a transaction).
        DB::rollBack(0);

        $this->expected_deprecated = [];
        $this->caught_deprecated = [];
        $this->expected_doing_it_wrong = [];
        $this->caught_doing_it_wrong = [];

        $this->_restore_hooks();
    }

    /**
     * Starts the transaction every test runs in, and makes tables created
     * during a test temporary, so they vanish with the rollback.
     */
    public function start_transaction()
    {
        // WM: Laravel's transaction (WordPress runs SET autocommit = 0 and
        // START TRANSACTION), so house code using DB::transaction() nests
        // inside it as a savepoint.
        DB::beginTransaction();

        add_filter('query', [$this, '_create_temporary_tables']);
        add_filter('query', [$this, '_drop_temporary_tables']);
    }

    /**
     * Commits the current transaction, and stays inside a new one, as
     * WordPress's autocommit = 0 session does.
     */
    public static function commit_transaction()
    {
        if (DB::transactionLevel() > 0) {
            DB::commit();
            DB::beginTransaction();

            return;
        }

        static::run_sql('COMMIT;');
    }

    /**
     * Turns CREATE TABLE into CREATE TEMPORARY TABLE during tests.
     */
    public function _create_temporary_tables($query)
    {
        if (str_starts_with(trim($query), 'CREATE TABLE')) {
            return substr_replace(trim($query), 'CREATE TEMPORARY TABLE', 0, 12);
        }

        return $query;
    }

    /**
     * Turns DROP TABLE into DROP TEMPORARY TABLE during tests.
     */
    public function _drop_temporary_tables($query)
    {
        if (str_starts_with(trim($query), 'DROP TABLE')) {
            return substr_replace(trim($query), 'DROP TEMPORARY TABLE', 0, 10);
        }

        return $query;
    }

    /**
     * Runs one SQL statement through $wpdb, as WordPress's own test case does.
     */
    protected static function run_sql(string $sql): void
    {
        global $wpdb;

        $wpdb->query($sql);
    }

    protected function assert_post_conditions()
    {
        $this->expectedDeprecated();
    }

    /**
     * Saves the hook globals so each test starts from the same state.
     */
    protected function _backup_hooks()
    {
        self::$hooks_saved['wp_filter'] = [];

        foreach ($GLOBALS['wp_filter'] as $hook_name => $hook_object) {
            self::$hooks_saved['wp_filter'][$hook_name] = clone $hook_object;
        }

        foreach (['wp_actions', 'wp_filters', 'wp_current_filter'] as $key) {
            self::$hooks_saved[$key] = $GLOBALS[$key];
        }
    }

    /**
     * Puts the hook globals back as they were saved.
     */
    protected function _restore_hooks()
    {
        if (isset(self::$hooks_saved['wp_filter'])) {
            $GLOBALS['wp_filter'] = [];

            foreach (self::$hooks_saved['wp_filter'] as $hook_name => $hook_object) {
                $GLOBALS['wp_filter'][$hook_name] = clone $hook_object;
            }
        }

        foreach (['wp_actions', 'wp_filters', 'wp_current_filter'] as $key) {
            if (isset(self::$hooks_saved[$key])) {
                $GLOBALS[$key] = self::$hooks_saved[$key];
            }
        }
    }

    /**
     * Reads the test's expected notices and starts catching notices.
     */
    public function expectDeprecated()
    {
        $annotations = Test::parseTestMethodAnnotations(static::class, $this->getName(false));

        foreach (['class', 'method'] as $depth) {
            if (! empty($annotations[$depth]['expectedDeprecated'])) {
                $this->expected_deprecated = array_merge($this->expected_deprecated, $annotations[$depth]['expectedDeprecated']);
            }

            if (! empty($annotations[$depth]['expectedIncorrectUsage'])) {
                $this->expected_doing_it_wrong = array_merge($this->expected_doing_it_wrong, $annotations[$depth]['expectedIncorrectUsage']);
            }
        }

        add_action('deprecated_function_run', [$this, 'deprecated_function_run'], 10, 3);
        add_action('deprecated_argument_run', [$this, 'deprecated_function_run'], 10, 3);
        add_action('deprecated_class_run', [$this, 'deprecated_function_run'], 10, 3);
        add_action('deprecated_file_included', [$this, 'deprecated_function_run'], 10, 4);
        add_action('deprecated_hook_run', [$this, 'deprecated_function_run'], 10, 4);
        add_action('doing_it_wrong_run', [$this, 'doing_it_wrong_run'], 10, 3);

        add_action('deprecated_function_trigger_error', '__return_false');
        add_action('deprecated_argument_trigger_error', '__return_false');
        add_action('deprecated_class_trigger_error', '__return_false');
        add_action('deprecated_file_trigger_error', '__return_false');
        add_action('deprecated_hook_trigger_error', '__return_false');
        add_action('doing_it_wrong_trigger_error', '__return_false');
    }

    /**
     * Fails the test if an expected notice was not raised, or an unexpected one was.
     */
    public function expectedDeprecated()
    {
        $errors = [];

        foreach (array_diff($this->expected_deprecated, array_keys($this->caught_deprecated)) as $not_caught) {
            $errors[] = "Failed to assert that $not_caught triggered a deprecation notice.";
        }

        foreach (array_diff(array_keys($this->caught_deprecated), $this->expected_deprecated) as $unexpected) {
            $errors[] = "Unexpected deprecation notice for $unexpected.";
            $errors[] = $this->caught_deprecated[$unexpected];
        }

        foreach (array_diff($this->expected_doing_it_wrong, array_keys($this->caught_doing_it_wrong)) as $not_caught) {
            $errors[] = "Failed to assert that $not_caught triggered an incorrect usage notice.";
        }

        foreach (array_diff(array_keys($this->caught_doing_it_wrong), $this->expected_doing_it_wrong) as $unexpected) {
            $errors[] = "Unexpected incorrect usage notice for $unexpected.";
            $errors[] = $this->caught_doing_it_wrong[$unexpected];
        }

        if ($this->expected_deprecated || $this->expected_doing_it_wrong || $this->caught_deprecated || $this->caught_doing_it_wrong) {
            $this->assertEmpty($errors, implode("\n", $errors));
        }
    }

    /**
     * Records a deprecation notice raised during the test.
     */
    public function deprecated_function_run($function_name, $replacement, $version, $message = '')
    {
        if (isset($this->caught_deprecated[$function_name])) {
            return;
        }

        $kind = [
            'deprecated_function_run' => 'Function %1$s is deprecated',
            'deprecated_argument_run' => 'Function %1$s was called with an argument that is deprecated',
            'deprecated_class_run' => 'Class %1$s is deprecated',
            'deprecated_file_included' => 'File %1$s is deprecated',
            'deprecated_hook_run' => 'Hook %1$s is deprecated',
        ][current_action()] ?? '%1$s is deprecated';

        $text = $replacement
            ? sprintf($kind.' since version %2$s! Use %3$s instead.', $function_name, $version, $replacement)
            : sprintf($kind.' since version %2$s with no alternative available.', $function_name, $version);

        if (in_array(current_action(), ['deprecated_file_included', 'deprecated_hook_run'], true)) {
            $text .= ' '.$message;
        }

        $this->caught_deprecated[$function_name] = $text;
    }

    /**
     * Records an incorrect-usage notice raised during the test.
     */
    public function doing_it_wrong_run($function_name, $message, $version)
    {
        if (isset($this->caught_doing_it_wrong[$function_name])) {
            return;
        }

        if ($version) {
            $message .= ' '.sprintf('(This message was added in version %s.)', $version);
        }

        $this->caught_doing_it_wrong[$function_name] = $message;
    }

    public function setExpectedDeprecated($deprecated)
    {
        $this->expected_deprecated[] = $deprecated;
    }

    public function setExpectedIncorrectUsage($doing_it_wrong)
    {
        $this->expected_doing_it_wrong[] = $doing_it_wrong;
    }

    /**
     * Asserts two arrays hold equal values (loose comparison), in any order.
     */
    public function assertEqualSets($expected, $actual, $message = '')
    {
        $this->assertIsArray($expected, $message.' Expected value must be an array.');
        $this->assertIsArray($actual, $message.' Value under test is not an array.');

        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * Asserts two arrays hold equal keys and values (loose comparison), in any order.
     */
    public function assertEqualSetsWithIndex($expected, $actual, $message = '')
    {
        $this->assertIsArray($expected, $message.' Expected value must be an array.');
        $this->assertIsArray($actual, $message.' Value under test is not an array.');

        ksort($expected);
        ksort($actual);
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * Asserts two arrays hold the same values, in any order.
     */
    public function assertSameSets($expected, $actual, $message = '')
    {
        $this->assertIsArray($expected, $message.' Expected value must be an array.');
        $this->assertIsArray($actual, $message.' Value under test is not an array.');

        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual, $message);
    }

    /**
     * Asserts two arrays hold the same keys and values, in any order.
     */
    public function assertSameSetsWithIndex($expected, $actual, $message = '')
    {
        $this->assertIsArray($expected, $message.' Expected value must be an array.');
        $this->assertIsArray($actual, $message.' Value under test is not an array.');

        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual, $message);
    }
}
