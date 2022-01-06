<?php

declare(strict_types=1);

/**
 * Unit tests for QueryBuilderHandler
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit;

use Pixie\Connection;
use Pixie\Tests\Logable_WPDB;
use PHPUnit\Framework\TestCase;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestQueryBuilderHandler extends TestCase
{
    /** Mocked WPDB instance. */
    private $wpdb;

    public function setUp(): void
    {
        $this->wpdb = new Logable_WPDB();
        parent::setUp();
    }

    /**
     * Generates a query builder helper.
     *
     * @param string|null $prefix
     * @return \Pixie\QueryBuilder\QueryBuilderHandler
     */
    public function queryBuilderProvider(?string $prefix = null): QueryBuilderHandler
    {
        $config = $prefix ? ['prefix' => $prefix] : [];
        $connection = new Connection($this->wpdb, $config);
        return new QueryBuilderHandler($connection);
    }

    /** @testdox It should be possible to do a simple get/select for a specified table. */
    public function testSimpleGet(): void
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->get();
        $this->assertEquals('SELECT * FROM foo', $this->wpdb->usage_log['get_results'][0]['query']);
    }

    /** @testdox It should be possible to create a get call with single (string) condition and have this generated and run through WPDB::prepare() */
    public function testGetWithSingleConditionStringValue(): void
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->where('key', '=', 'value')->get();

        // Query and values passed to prepare();
        $prepared = $this->wpdb->usage_log['prepare'][0];

        // Check that the query is passed to prepare first with the value as a string.
        $this->assertEquals('SELECT * FROM foo WHERE key = %s', $prepared['query']);

        // Check values are used in order passed
        $this->assertEquals('value', $prepared['args'][0]);
    }

    /** @testdox It should be possible to create a get call with value (float) condition and have this generated and run through WPDB::prepare() */
    public function testGetWithSingleConditionFloatValue(): void
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->where('key', '=', 2.2)->get();

        // Query and values passed to prepare();
        $prepared = $this->wpdb->usage_log['prepare'][0];

        // Check that the query is passed to prepare first with the value as a float.
        $this->assertEquals('SELECT * FROM foo WHERE key = %f', $prepared['query']);

        // Check values are used in order passed
        $this->assertEquals(2.2, $prepared['args'][0]);
    }

    /** @testdox It should be possible to create a get call with value (integer) condition and have this generated and run through WPDB::prepare() */
    public function testGetWithSingleConditionIntValue(): void
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->where('key', '=', 2)->get();

        // Query and values passed to prepare();
        $prepared = $this->wpdb->usage_log['prepare'][0];

        // Check that the query is passed to prepare.
        $this->assertEquals('SELECT * FROM foo WHERE key = %d', $prepared['query']);

        // Check values are used in order passed
        $this->assertEquals(2, $prepared['args'][0]);
    }

    /** @testdox It should be possible to create a get call with value (in array) condition and have this generated and run through WPDB::prepare() */
    public function testGetWithSingleConditionArrayInValue(): void
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->where('key', 'in', [2, 2.5, 'string'])->get();

        // Query and values passed to prepare();
        $prepared = $this->wpdb->usage_log['prepare'][0];

        // Check that the query is passed to prepare.
        $this->assertEquals('SELECT * FROM foo WHERE key in (%d, %f, %s)', $prepared['query']);

        // Check values are used in order passed
        $this->assertEquals(2, $prepared['args'][0]);
        $this->assertEquals(2.5, $prepared['args'][1]);
        $this->assertEquals('string', $prepared['args'][2]);
    }

     /** @testdox It should be possible to create a get call with value (BETWEEN) condition and have this generated and run through WPDB::prepare() */
    public function testGetWithSingleConditionBetweenValue(): void
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->where('key', 'between', [2, 2.5])->get();

        // Query and values passed to prepare();
        $prepared = $this->wpdb->usage_log['prepare'][0];

        // Check that the query is passed to prepare.
        $this->assertEquals('SELECT * FROM foo WHERE key between (%d, %f)', $prepared['query']);

        // Check values are used in order passed
        $this->assertEquals(2, $prepared['args'][0]);
        $this->assertEquals(2.5, $prepared['args'][1]);
    }
}
