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
use Pixie\QueryBuilder\JoinBuilder;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestQueryBuilderBehavioural extends WP_UnitTestCase
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
            ->orWhereBetween('key', 'v1', 'v2')
            ->orWhereBetween('key2', 2, 12);
        $this->assertEquals("SELECT * FROM foo WHERE key BETWEEN 'v1' AND 'v2' OR key2 BETWEEN 2 AND 12", $builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
            ->table('foo')
            ->orWhereBetween('key', 'v1', 'v2')
            ->orWhereBetween('key2', 2, 12);
        $this->assertEquals("SELECT * FROM foo WHERE key BETWEEN 'v1' AND 'v2' OR key2 BETWEEN 2 AND 12", $builderNot->getQuery()->getRawSql());

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

    /** @testdox It should be possible to order by a single key and specify the direction. */
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
    public function testInnerJoin(): void
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
}
