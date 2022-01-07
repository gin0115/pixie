<?php

declare(strict_types=1);

/**
 * Unit tests for QueryBuilder
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit;

use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestQueryBuilder extends WP_UnitTestCase
{

    /** Mocked WPDB instance.
     * @var Logable_WPDB
    */
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
    public function queryBuilderProvider(?string $prefix = null, ?string $alias = null): QueryBuilderHandler
    {
        $config = $prefix ? ['prefix' => $prefix] : [];
        $connection = new Connection($this->wpdb, $config, $alias);
        return new QueryBuilderHandler($connection);
    }

    /** @testdox It should be possible to create a query for multiple tables. */
    public function testMultiTableQuery(): void
    {
        $builder = $this->queryBuilderProvider()
            ->table(['foo', 'bar']);

        $this->assertEquals('SELECT * FROM foo, bar', $builder->getQuery()->getSql());
    }

    /** @testdox It should be possile to do a quick and simple find using a single key value  */
    public function testSimpleFind(): void
    {
        // Using the assumed `id` as key
        $builder = $this->queryBuilderProvider()
            ->table('foo')->find(1);
        // Check the passed query to prepare.
        $log = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals('SELECT * FROM foo WHERE id = 1 LIMIT 1', $log['query']);

        // With custom key
        $builder = $this->queryBuilderProvider()
            ->table('foo')->find(2, 'custom');

        $log = $this->wpdb->usage_log['get_results'][1];
        $this->assertEquals('SELECT * FROM foo WHERE custom = 2 LIMIT 1', $log['query']);
    }

    /** @testdox It should be possible to create a select query for specified fields. */
    public function testSelectFields(): void
    {
        // Singe column
        $builder = $this->queryBuilderProvider()
            ->table('foo')
            ->select('single');

        $this->assertEquals('SELECT single FROM foo', $builder->getQuery()->getSql());

        // Multiple
        $builderMulti = $this->queryBuilderProvider()
            ->table('foo')
            ->select(['double', 'dual']);

        $this->assertEquals('SELECT double, dual FROM foo', $builderMulti->getQuery()->getSql());
    }

    /** @testdox It should be possible to select distinct values, either individually or multiple columns. */
    public function testSelectDistinct(): void
    {
        // Singe column
        $builder = $this->queryBuilderProvider()
            ->table('foo')
            ->selectDistinct('single');

        $this->assertEquals('SELECT DISTINCT single FROM foo', $builder->getQuery()->getSql());

        // Multiple
        $builderMulti = $this->queryBuilderProvider()
            ->table('foo')
            ->selectDistinct(['double', 'dual']);

        $this->assertEquals('SELECT DISTINCT double, dual FROM foo', $builderMulti->getQuery()->getSql());
    }

    /** @testdox It should be possible to call findAll() and have the values prepared using WPDB::prepare() */
    public function testFindAll(): void
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('my_table')->findAll('name', 'Sana');

        $log = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals('SELECT * FROM my_table WHERE name = \'Sana\'', $log['query']);
    }

    /** @testdox It should be possible to create a where condition but only return the first value and have this generated and run through WPDB::prepare() */
    public function testFirstWithWhereCondition(): void
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->where('key', '=', 'value')->first();

        $log = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals('SELECT * FROM foo WHERE key = \'value\' LIMIT 1', $log['query']);
    }

    /** @testdox It should be possible to do a query which gets a count of all rows using sql `count()` */
    public function testSelectCount(): void
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->select('*')->where('key', '=', 'value')->count();

        $log = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals("SELECT count(*) as field FROM foo WHERE key = 'value'", $log['query']);
    }

                                        ################################################
                                        ##              WHERE CONDITIONS              ##
                                        ################################################

    /** @testdox It should be possible to create a query which uses Where and Where not (using AND condition) */
    public function testWhereAndWhereNot(): void
    {
        $builderWhere = $this->queryBuilderProvider()
            ->table('foo')
            ->where('key', '=', 'value')
            ->where('key2', '=', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE key = 'value' AND key2 = 'value2'", $builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
            ->table('foo')
            ->whereNot('key', '<', 'value')
            ->whereNot('key2', '>', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE NOT key < 'value' AND NOT key2 > 'value2'", $builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
            ->table('foo')
            ->where('key', '=', 'value')
            ->whereNot('key2', '>', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE key = 'value' AND NOT key2 > 'value2'", $builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses Where and Where not (using OR condition) */
    public function testWhereOrWhereNot(): void
    {
        $builderWhere = $this->queryBuilderProvider()
            ->table('foo')
            ->orWhere('key', '=', 'value')
            ->orWhere('key2', '=', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE key = 'value' OR key2 = 'value2'", $builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
            ->table('foo')
            ->orWhereNot('key', '<', 'value')
            ->orWhereNot('key2', '>', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE NOT key < 'value' OR NOT key2 > 'value2'", $builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
            ->table('foo')
            ->orWhere('key', '=', 'value')
            ->orWhereNot('key2', '>', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE key = 'value' OR NOT key2 > 'value2'", $builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses Where In and Where not In (using AND condition) */
    public function testWhereInAndWhereNotIn(): void
    {
        $builderWhere = $this->queryBuilderProvider()
            ->table('foo')
            ->whereIn('key', ['v1', 'v2'])
            ->whereIn('key2', [2, 12]);
        $this->assertEquals("SELECT * FROM foo WHERE key IN ('v1', 'v2') AND key2 IN (2, 12)", $builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
            ->table('foo')
            ->whereNotIn('key', ['v1', 'v2'])
            ->whereNotIn('key2', [2, 12]);
        $this->assertEquals("SELECT * FROM foo WHERE key NOT IN ('v1', 'v2') AND key2 NOT IN (2, 12)", $builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
            ->table('foo')
            ->whereNotIn('key', ['v1', 'v2'])
            ->whereIn('key2', [2, 12]);
        $this->assertEquals("SELECT * FROM foo WHERE key NOT IN ('v1', 'v2') AND key2 IN (2, 12)", $builderMixed->getQuery()->getRawSql());
    }
}
