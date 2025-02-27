<?php

declare(strict_types=1);

/**
 * Tests to ensure the Query Builder creates valid SQL queries.
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests;

use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\JoinBuilder;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestQueryBuilderSQLGeneration extends WP_UnitTestCase
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

    /** @testdox It should be possible to define the table to use in a select query, with more mysql feeling syntax $db->select('*')->from('table') */
    public function testDefineFromTableNames(): void
    {
        $builder = $this->queryBuilderProvider()
            ->select(['foo.id', 'bar.id'])
            ->from('foo')
            ->from('bar');

        $this->assertEquals('SELECT foo.id, bar.id FROM foo, bar', $builder->getQuery()->getSql());
    }

    /** @testdox It should be possible to create a query for multiple tables. */
    public function testMultiTableQuery(): void
    {
        $builder = $this->queryBuilderProvider()
            ->table(['foo', 'bar']);

        $this->assertEquals('SELECT * FROM foo, bar', $builder->getQuery()->getSql());
    }

    /** @testdox It should be possible to do a quick and simple find using a single key value  */
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

    /** @testdox It should be possible to use aliases with the select fields. */
    public function testSelectWithAliasForColumns(): void
    {
        $builder = $this->queryBuilderProvider()
            ->table('foo')
            ->select(['single' => 'sgl', 'foo' => 'bar']);

        $this->assertEquals('SELECT single AS sgl, foo AS bar FROM foo', $builder->getQuery()->getSql());
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
        $this->assertEquals("SELECT COUNT(*) AS field FROM (SELECT * FROM foo WHERE key = 'value') as count LIMIT 1", $log['query']);
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

    /** @testdox It should be possible to create a query which uses Where In and Where not In (using OR condition) */
    public function testWhereInOrWhereNotIn(): void
    {
        $builderWhere = $this->queryBuilderProvider()
            ->table('foo')
            ->orWhereIn('key', ['v1', 'v2'])
            ->orWhereIn('key2', [2, 12]);
        $this->assertEquals("SELECT * FROM foo WHERE key IN ('v1', 'v2') OR key2 IN (2, 12)", $builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
            ->table('foo')
            ->orWhereNotIn('key', ['v1', 'v2'])
            ->orWhereNotIn('key2', [2, 12]);
        $this->assertEquals("SELECT * FROM foo WHERE key NOT IN ('v1', 'v2') OR key2 NOT IN (2, 12)", $builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
            ->table('foo')
            ->orWhereNotIn('key', ['v1', 'v2'])
            ->orWhereIn('key2', [2, 12]);
        $this->assertEquals("SELECT * FROM foo WHERE key NOT IN ('v1', 'v2') OR key2 IN (2, 12)", $builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses Where Null and Where not Null (using AND condition) */
    public function testWhereNullAndWhereNotNull(): void
    {
        $builderWhere = $this->queryBuilderProvider()
            ->table('foo')
            ->whereNull('key')
            ->whereNull('key2');
        $this->assertEquals("SELECT * FROM foo WHERE key IS NULL AND key2 IS NULL", $builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
            ->table('foo')
            ->whereNotNull('key')
            ->whereNotNull('key2');
        $this->assertEquals("SELECT * FROM foo WHERE key IS NOT NULL AND key2 IS NOT NULL", $builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
            ->table('foo')
            ->whereNotNull('key')
            ->whereNull('key2');
        $this->assertEquals("SELECT * FROM foo WHERE key IS NOT NULL AND key2 IS NULL", $builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses Where Null and Where not Null (using OR condition) */
    public function testWhereNullOrWhereNotNull(): void
    {
        $builderWhere = $this->queryBuilderProvider()
            ->table('foo')
            ->orWhereNull('key')
            ->orWhereNull('key2');
        $this->assertEquals("SELECT * FROM foo WHERE key IS NULL OR key2 IS NULL", $builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
            ->table('foo')
            ->orWhereNotNull('key')
            ->orWhereNotNull('key2');
        $this->assertEquals("SELECT * FROM foo WHERE key IS NOT NULL OR key2 IS NOT NULL", $builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
            ->table('foo')
            ->orWhereNotNull('key')
            ->orWhereNull('key2');
        $this->assertEquals("SELECT * FROM foo WHERE key IS NOT NULL OR key2 IS NULL", $builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a querying using BETWEEN (AND/OR) 2 values. */
    public function testWhereBetween(): void
    {
        $builderWhere = $this->queryBuilderProvider()
            ->table('foo')
            ->whereBetween('key', 'v1', 'v2')
            ->whereBetween('key2', 2, 12);
        $this->assertEquals("SELECT * FROM foo WHERE key BETWEEN 'v1' AND 'v2' AND key2 BETWEEN 2 AND 12", $builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
            ->table('foo')
            ->orWhereBetween('key2', 2, 12)
            ->whereBetween('key', 'v1', 'v2');
        $this->assertEquals("SELECT * FROM foo WHERE key2 BETWEEN 2 AND 12 AND key BETWEEN 'v1' AND 'v2'", $builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
            ->table('foo')
            ->orWhereBetween('key', 'v1', 'v2')
            ->orWhereBetween('key2', 2, 12);
        $this->assertEquals("SELECT * FROM foo WHERE key BETWEEN 'v1' AND 'v2' OR key2 BETWEEN 2 AND 12", $builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to use any where() condition and have the operator assumed as = (equals) */
    public function testWhereAssumedEqualsOperator(): void
    {
        $where = $this->queryBuilderProvider()
            ->table('foo')
            ->where('key', 'value');
        $this->assertEquals("SELECT * FROM foo WHERE key = 'value'", $where->getQuery()->getRawSql());

        $orWhere = $this->queryBuilderProvider()
            ->table('foo')
            ->where('key', 'value')
            ->orWhere('key2', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE key = 'value' OR key2 = 'value2'", $orWhere->getQuery()->getRawSql());

        $whereNot = $this->queryBuilderProvider()
            ->table('foo')
            ->whereNot('key', 'value');
        $this->assertEquals("SELECT * FROM foo WHERE NOT key = 'value'", $whereNot->getQuery()->getRawSql());

        $orWhereNot = $this->queryBuilderProvider()
            ->table('foo')
            ->where('key', 'value')
            ->orWhereNot('key2', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE key = 'value' OR NOT key2 = 'value2'", $orWhereNot->getQuery()->getRawSql());
    }

                                        ################################################
                                        ##   GROUP, ORDER BY, LIMIT/OFFSET & HAVING   ##
                                        ################################################

    /** @testdox It should be possible to create a grouped where condition */
    public function testGroupedWhere(): void
    {
        $builder = $this->queryBuilderProvider()
            ->table('foo')
            ->where('key', '=', 'value')
            ->where(function (QueryBuilderHandler $query) {
                $query->where('key2', '<>', 'value2');
                $query->orWhere('key3', '=', 'value3');
            });

        $this->assertEquals("SELECT * FROM foo WHERE key = 'value' AND (key2 <> 'value2' OR key3 = 'value3')", $builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses group by (SINGLE) */
    public function testSingleGroupBy(): void
    {
        $builder = $this->queryBuilderProvider()
            ->table('foo')->groupBy('bar');

        $this->assertEquals("SELECT * FROM foo GROUP BY bar", $builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses group by (Multiple) */
    public function testMultipleGroupBy(): void
    {
        $builder = $this->queryBuilderProvider()
            ->table('foo')->groupBy(['bar', 'baz']);

        $this->assertEquals("SELECT * FROM foo GROUP BY bar, baz", $builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to order by a single key and specify the direction. */
    public function testOrderBy(): void
    {
        // Assumed ASC (default.)
        $builderDef = $this->queryBuilderProvider()
            ->table('foo')->orderBy('bar');

        $this->assertEquals("SELECT * FROM foo ORDER BY bar ASC", $builderDef->getQuery()->getRawSql());

        // Specified DESC
        $builderDesc = $this->queryBuilderProvider()
            ->table('foo')->orderBy('bar', 'DESC');

        $this->assertEquals("SELECT * FROM foo ORDER BY bar DESC", $builderDesc->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to order by multiple keys and specify the direction. */
    public function testOrderByMultiple(): void
    {
        // Assumed ASC (default.)
        $builderDef = $this->queryBuilderProvider()
            ->table('foo')->orderBy(['bar', 'baz']);

        $this->assertEquals("SELECT * FROM foo ORDER BY bar ASC, baz ASC", $builderDef->getQuery()->getRawSql());

        // Specified DESC
        $builderDesc = $this->queryBuilderProvider()
            ->table('foo')->orderBy(['bar', 'baz'], 'DESC');

        $this->assertEquals("SELECT * FROM foo ORDER BY bar DESC, baz DESC", $builderDesc->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to set HAVING in queries. */
    public function testHaving(): void
    {
        $builderHaving = $this->queryBuilderProvider()
            ->table('foo')
            ->select(['real' => 'alias'])
            ->having('alias', '!=', 'tree');

        $this->assertEquals("SELECT real AS alias FROM foo HAVING alias != 'tree'", $builderHaving->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
            ->table('foo')
            ->select(['real' => 'alias'])
            ->having('alias', '!=', 'tree')
            ->orHaving('bar', '=', 'woop');

        $this->assertEquals("SELECT real AS alias FROM foo HAVING alias != 'tree' OR bar = 'woop'", $builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to limit the query */
    public function testLimit(): void
    {
        $builderLimit = $this->queryBuilderProvider()
            ->table('foo')->limit(12);

        $this->assertEquals("SELECT * FROM foo LIMIT 12", $builderLimit->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to set the offset that a query will start return results from */
    public function testOffset()
    {
        $builderOffset = $this->queryBuilderProvider()
            ->table('foo')->offset(12);

        $this->assertEquals("SELECT * FROM foo OFFSET 12", $builderOffset->getQuery()->getRawSql());
    }

                                        #################################################
                                        ##    JOIN {INNER, LEFT, RIGHT, FULL OUTER}    ##
                                        #################################################

    /** @testdox It should be possible to create a query using (INNER) join for a relationship */
    public function testJoin(): void
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo')
            ->join('bar', 'foo.id', '=', 'bar.id');

        $this->assertEquals("SELECT * FROM prefix_foo INNER JOIN prefix_bar ON prefix_foo.id = prefix_bar.id", $builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query using (OUTER) join for a relationship */
    public function testOuterJoin()
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo')
            ->outerJoin('bar', 'foo.id', '=', 'bar.id');

        $this->assertEquals("SELECT * FROM prefix_foo OUTER JOIN prefix_bar ON prefix_foo.id = prefix_bar.id", $builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query using (RIGHT) join for a relationship */
    public function testRightJoin()
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo')
            ->rightJoin('bar', 'foo.id', '=', 'bar.id');

        $this->assertEquals("SELECT * FROM prefix_foo RIGHT JOIN prefix_bar ON prefix_foo.id = prefix_bar.id", $builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query using (LEFT) join for a relationship */
    public function testLeftJoin()
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo')
            ->leftJoin('bar', 'foo.id', '=', 'bar.id');

        $this->assertEquals("SELECT * FROM prefix_foo LEFT JOIN prefix_bar ON prefix_foo.id = prefix_bar.id", $builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query using (CROSS) join for a relationship */
    public function testCrossJoin()
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo')
            ->crossJoin('bar', 'foo.id', '=', 'bar.id');

        $this->assertEquals("SELECT * FROM prefix_foo CROSS JOIN prefix_bar ON prefix_foo.id = prefix_bar.id", $builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query using (INNER) join for a relationship */
    public function testInnerJoin()
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('in_')
            ->table('foo')
            ->innerJoin('bar', 'foo.id', '=', 'bar.id');

        $this->assertEquals("SELECT * FROM in_foo INNER JOIN in_bar ON in_foo.id = in_bar.id", $builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a conditional join using multiple ON with AND conditions */
    public function testMultipleJoinAndViaClosure()
    {
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo')
            ->join('bar', function (JoinBuilder $builder) {
                $builder->on('bar.id', '!=', 'foo.id');
                $builder->on('bar.baz', '!=', 'foo.baz');
            });
        $this->assertEquals("SELECT * FROM prefix_foo INNER JOIN prefix_bar ON prefix_bar.id != prefix_foo.id AND prefix_bar.baz != prefix_foo.baz", $builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a conditional join using multiple ON with OR conditions */
    public function testMultipleJoinOrViaClosure()
    {
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo')
            ->join('bar', function (JoinBuilder $builder): void {
                $builder->orOn('bar.id', '!=', 'foo.id');
                $builder->orOn('bar.baz', '!=', 'foo.baz');
            });
        $this->assertEquals("SELECT * FROM prefix_foo INNER JOIN prefix_bar ON prefix_bar.id != prefix_foo.id OR prefix_bar.baz != prefix_foo.baz", $builder->getQuery()->getRawSql());
    }

                                        #################################################
                                        ##             SUB AND RAW QUERIES             ##
                                        #################################################

    /** @testdox It should be possible to create a raw query which can be executed with or without binding values. */
    public function testRawQuery(): void
    {
        // Without bindings.
        $builder = $this->queryBuilderProvider(null, 'testRawQuery');
        \testRawQuery::query('SELECT * FROM foo')->get();

        $this->assertEquals('SELECT * FROM foo', $this->wpdb->usage_log['get_results'][0]['query']);

        // Reset the log
        $this->wpdb->usage_log = [];

        // With bindings.
        \testRawQuery::query('SELECT * FROM foo WHERE bar = %s AND baz != %d', ['string', 314])->get();

        $this->assertEquals('SELECT * FROM foo WHERE bar = \'string\' AND baz != 314', $this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertEquals('SELECT * FROM foo WHERE bar = %s AND baz != %d', $this->wpdb->usage_log['prepare'][0]['query']);
        $this->assertCount(2, $this->wpdb->usage_log['prepare'][0]['args']);
        $this->assertEquals('string', $this->wpdb->usage_log['prepare'][0]['args'][0]);
        $this->assertEquals(314, $this->wpdb->usage_log['prepare'][0]['args'][1]);
    }


    /** @testdox It should be possible to create a raw SQL expression that can be used as in a sub query */
    public function testRawExpression(): void
    {
        $builder = $this->queryBuilderProvider(null, 'testRawExpression');

        // With no bindings.
        /** @var Raw */
        $query = \testRawExpression::raw('SELECT * FROM foo');
        $this->assertInstanceOf(Raw::class, $query);
        $this->assertEquals('SELECT * FROM foo', (string) $query);
        $this->assertIsArray($query->getBindings());
        $this->assertEmpty($query->getBindings());

        // With single value (not array) binding value.
        /** @var Raw */
        $query = \testRawExpression::raw('SELECT * FROM foo WHERE %s = \'single\'', 'single');
        $this->assertContains('single', $query->getBindings());
        $this->assertCount(1, $query->getBindings());

        // With multiple binding values.
        $query = \testRawExpression::raw('SELECT * FROM foo WHERE %s = \'single\'', ['a', 'b']);
        $this->assertContains('a', $query->getBindings());
        $this->assertContains('b', $query->getBindings());
        $this->assertCount(2, $query->getBindings());
    }

    /** @testdox It should be possible to use RAW Expressions are parts of a query */
    public function testNestedQueryWithRawExpressions(): void
    {
        $this->queryBuilderProvider(null, 'AB');
        $query = \AB::table('foo')
            ->select(\AB::raw('count(cb_my_table.id) as tot'))
            ->where('value', '=', 'Ifrah')
            ->where(\AB::raw('bar = %s', 'now'));

        $this->assertEquals("SELECT count(cb_my_table.id) as tot FROM foo WHERE value = 'Ifrah' AND bar = 'now'", $query->getQuery()->getRawSql());
    }


                                        #################################################
                                        ##               INSERT & UPDATE               ##
                                        #################################################

    /** @testdox It should be possible to insert a single row of data and get the row id/key returned. */
    public function testInsertSingle(): void
    {
        // Mock success from single insert
        $this->wpdb->rows_affected = 1;
        $this->wpdb->insert_id = 24;

        $newID = $this->queryBuilderProvider()
            ->table('foo')
            ->insert(['name' => 'Sana', 'description' => 'Blah']);

        $this->assertEquals(24, $newID);

        // Check the actual query.
        $this->assertEquals("INSERT INTO foo (name,description) VALUES ('Sana','Blah')", $this->wpdb->usage_log['get_results'][0]['query']);
    }

    /** @testdox It should be possible to insert multiple rows of data and get the row id/key returned as an array. */
    public function testInsertMultiple(): void
    {
        // Mock success from single insert
        $this->wpdb->rows_affected = 1;
        $this->wpdb->insert_id = 7;

        $data = [
            ['name' => 'Sana', 'description' => 'Blah'],
            ['name' => 'Mark', 'description' => 'Woo'],
            ['name' => 'Sam', 'description' => 'Boo'],
        ];

        $newIDs = $this->queryBuilderProvider()
            ->table('foo')
            ->insert($data);

        $this->assertEquals([7,7,7], $newIDs); // Will always return 7 as mocked.

        // Check the actual queries.
        $this->assertEquals("INSERT INTO foo (name,description) VALUES ('Sana','Blah')", $this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertEquals("INSERT INTO foo (name,description) VALUES ('Mark','Woo')", $this->wpdb->usage_log['get_results'][1]['query']);
        $this->assertEquals("INSERT INTO foo (name,description) VALUES ('Sam','Boo')", $this->wpdb->usage_log['get_results'][2]['query']);
    }

    /** @testdox It should be possible to an Insert which ignores all errors generated by MYSQL */
    public function testInsertIgnore(): void
    {
        // Mock success from single insert
        $this->wpdb->rows_affected = 1;
        $this->wpdb->insert_id = 89;

        $data = [
            ['name' => 'Sana', 'description' => 'Blah'],
            ['name' => 'Mark', 'description' => 'Woo'],
            ['name' => 'Sam', 'description' => 'Boo'],
        ];

        $newIDs = $this->queryBuilderProvider()
            ->table('foo')
            ->insertIgnore($data);

        $this->assertEquals([89,89,89], $newIDs);

        // Check the actual queries.
        $this->assertEquals("INSERT IGNORE INTO foo (name,description) VALUES ('Sana','Blah')", $this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertEquals("INSERT IGNORE INTO foo (name,description) VALUES ('Mark','Woo')", $this->wpdb->usage_log['get_results'][1]['query']);
        $this->assertEquals("INSERT IGNORE INTO foo (name,description) VALUES ('Sam','Boo')", $this->wpdb->usage_log['get_results'][2]['query']);
    }

    /** @testdox It should be possible to create a query which will do an update on a duplicate key  */
    public function testInsertOnDuplicateKey(): void
    {
        // Mock success from single insert
        $this->wpdb->rows_affected = 1;
        $this->wpdb->insert_id = 12;

        $ID = $this->queryBuilderProvider()
            ->table('foo')
            ->onDuplicateKeyUpdate(['name' => 'Baza', 'counter' => 1])
            ->insert(['name' => 'Baza', 'counter' => 2]);

        $this->assertEquals(12, $ID);
        $this->assertEquals("INSERT INTO foo (name,counter) VALUES ('Baza',2) ON DUPLICATE KEY UPDATE name='Baza',counter=1", $this->wpdb->usage_log['get_results'][0]['query']);
    }

    /** @testdox It should be possible to create a REPLACE INTO query and have the values added using WPDB::prepare()*/
    public function testReplace()
    {
        $this->queryBuilderProvider()
            ->table('foo')
            ->replace(['id' => 24, 'name' => 'Glynn', 'doubt' => true]);

        $prepared = $this->wpdb->usage_log['prepare'][0];
        $query = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals('REPLACE INTO foo (id,name,doubt) VALUES (%d,%s,%d)', $prepared['query']);
        $this->assertEquals('REPLACE INTO foo (id,name,doubt) VALUES (24,\'Glynn\',1)', $query['query']);
    }

    /** @testdox It should be possible to create a query which will delete all rows that the match the criteria defined. Any values should be passed to WPDB::prepare() before being used. */
    public function testDelete()
    {
        $this->queryBuilderProvider()
            ->table('foo')
            ->where('id', '>', 5)
            ->delete();

        $prepared = $this->wpdb->usage_log['prepare'][0];
        $query = $this->wpdb->usage_log['get_results'][0];

        $this->assertEquals('DELETE FROM foo WHERE id > %d', $prepared['query']);
        $this->assertEquals('DELETE FROM foo WHERE id > 5', $query['query']);
    }
}
