<?php

declare(strict_types=1);

/**
 * Integration test using WP PHPUnit MYSQL instance.
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests;

use stdClass;
use Exception;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestIntegrationWithWPDB extends WP_UnitTestCase
{
     /** Test WPDB instance.
     * @var \wpdb
    */
    private $wpdb;

    protected static $createdTables = false;

    public function setUp(): void
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        parent::setUp();

        if (! static::$createdTables) {
            $this->createTables();
        }
    }

    public function createTables(): void
    {
        $wpdb_collate = $this->wpdb->collate;
        $sqlFoo =
         "CREATE TABLE mock_foo (
         id mediumint(8) unsigned NOT NULL auto_increment ,
         string varchar(255) NULL,
         number int NULL,
         PRIMARY KEY  (id)
         )
         COLLATE {$this->wpdb->collate}";

        $sqlBar =
         "CREATE TABLE mock_bar (
         id mediumint(8) unsigned NOT NULL auto_increment ,
         string varchar(255) NULL,
         number int NULL,
         PRIMARY KEY  (id)
         )
         COLLATE {$this->wpdb->collate}";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlFoo);
        dbDelta($sqlBar);

        static::$createdTables = true;
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

    /** @testdox [WPDB] It should be possible to do various simple SELECT queries using WHERE conditions using a live instance of WPDB (WHERE, WHERE NOT, WHERE AND, WHERE IN, WHERE BETWEEN) */
    public function testWhere()
    {
        $this->wpdb->insert('mock_foo', ['string' => 'a', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'a', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'b', 'number' => 3], ['%s', '%d']);

        // Test Where
        $allAs = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('string', 'a')
            ->get();

        $this->assertCount(2, $allAs);
        $this->assertEquals('a', $allAs[0]->string);
        $this->assertEquals('a', $allAs[1]->string);

        // Test Where And
        $and = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('string', 'a')
            ->where('number', 1)
            ->get();

        $this->assertCount(1, $and);
        $this->assertEquals('a', $and[0]->string);
        $this->assertEquals('1', $and[0]->number);

        // NotWhere
        $not = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->whereNot('string', 'a')
            ->get();

        $this->assertCount(1, $not);
        $this->assertEquals('b', $not[0]->string);
        $this->assertEquals('3', $not[0]->number);

        // In
        $in = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->whereIn('number', [1,3])
            ->get();

        $this->assertCount(2, $in);
        $this->assertEquals('a', $in[0]->string);
        $this->assertEquals('b', $in[1]->string);

        // Between
        $between = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->whereBetween('number', 2, 3)
            ->get();

        $this->assertCount(2, $between);
        $this->assertEquals('a', $between[0]->string);
        $this->assertEquals('2', $between[0]->number);
        $this->assertEquals('b', $between[1]->string);
        $this->assertEquals('3', $between[1]->number);
    }

    /** @testdox [WPDB] It should be possible to do various Aggregation (COUNT, MIN, MAX, SUM, AVERAGE) for results, using WPDB*/
    public function testAggregation(): void
    {
        $this->wpdb->insert('mock_foo', ['string' => 'a', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'a', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'b', 'number' => 3], ['%s', '%d']);

        // Count results
        $count = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('string', 'a')
            ->count();

        $this->assertEquals(2, $count);

        // Get a sum of all numbers.
        $sum = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('string', 'a')
            ->sum('number');

        $this->assertEquals(3.0, $sum);

        // Get the avg
        $avg = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('string', 'a')
            ->average('number');

        $this->assertEquals(1.5, $avg);

        // Get the max
        $max = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->max('number');

        $this->assertEquals(3, $max);

        // Get the min
        $min = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->min('number');

        $this->assertEquals(1, $min);
    }

    /** @testdox [WPDB] It should be possible to use a custom alias for static calls, prefix for table names and have the results in an array not object. */
    public function testUsingTablePrefixWithAlias(): void
    {
        $this->wpdb->insert('mock_foo', ['string' => 'a', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'a', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'b', 'number' => 3], ['%s', '%d']);

        $this->queryBuilderProvider('mock_', 'BA');

        $results = \BA::select(['number' => 'num']) // Only fetch number as num
            ->from('foo') // Should use the mock_ prefix
            ->setFetchMode(\ARRAY_A) // Return as an array
            ->get();

        // Expected [['num' => '1'],['num' => '2'],['num' => '3']]

        // Check we only have number as num and results are arrays.
        foreach ($results as $key => $result) {
            $this->assertIsArray($result);
            $this->assertCount(1, $result);
            $this->assertEquals(\strval($key + 1), $result['num']);
        }
    }

    /** @testdox [WPDB] It should be possible to join basic joins (INNER, LEFT & RIGHT) and have the query executed using WPDB. */
    public function testJoins(): void
    {
        $this->wpdb->insert('mock_foo', ['string' => 'Primary', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Secondary', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Third', 'number' => 3], ['%s', '%d']);

        $this->wpdb->insert('mock_bar', ['string' => 'Apple', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_bar', ['string' => 'Apple', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_bar', ['string' => 'Banana', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_bar', ['string' => 'Banana', 'number' => 3], ['%s', '%d']);
        $this->wpdb->insert('mock_bar', ['string' => 'Strawberry', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_bar', ['string' => 'Raspberry', 'number' => 4], ['%s', '%d']);

        // Get all FRUITS (from mock_bar) with the TYPE (from mock_foo)
        $fruitsInner = $this->queryBuilderProvider('mock_')
            ->select(['bar.string' => 'name', 'foo.string' => 'type'])
            ->from('bar')
            ->join('foo', 'bar.number', '=', 'foo.number')
            ->setFetchMode(\ARRAY_A)
            ->get();

        // Expected 5
        $expected = [
            ['name' => 'Apple',  'type' => 'Primary'],
            ['name' => 'Apple',  'type' => 'Secondary'],
            ['name' => 'Banana',  'type' => 'Primary'],
            ['name' => 'Banana',  'type' => 'Third'],
            ['name' => 'Strawberry',  'type' => 'Primary'],
        ];

        $this->assertCount(5, $fruitsInner);
        $this->assertSame($expected, $fruitsInner);

        // Left Join
        $fruitsLeft = $this->queryBuilderProvider('mock_')
            ->select(['bar.string' => 'name', 'foo.string' => 'type'])
            ->from('bar')
            ->leftJoin('foo', 'bar.number', '=', 'foo.number')
            ->setFetchMode(\ARRAY_A)
            ->get();

        // Expected 6
        $expected = [
            ['name' => 'Apple',  'type' => 'Primary'],
            ['name' => 'Banana',  'type' => 'Primary'],
            ['name' => 'Strawberry',  'type' => 'Primary'],
            ['name' => 'Apple',  'type' => 'Secondary'],
            ['name' => 'Banana',  'type' => 'Third'],
            ['name' => 'Raspberry',  'type' => null],
        ];

        $this->assertCount(6, $fruitsLeft);
        $this->assertSame($expected, $fruitsLeft);

        // Right Join
        $fruitsRight = $this->queryBuilderProvider('mock_')
            ->select(['bar.string' => 'name', 'foo.string' => 'type'])
            ->from('bar')
            ->rightJoin('foo', 'bar.number', '=', 'foo.number')
            ->setFetchMode(\ARRAY_A)
            ->get();

        // Expected 5
        $expected = [
            ['name' => 'Apple',  'type' => 'Primary'],
            ['name' => 'Apple',  'type' => 'Secondary'],
            ['name' => 'Banana',  'type' => 'Primary'],
            ['name' => 'Banana',  'type' => 'Third'],
            ['name' => 'Strawberry',  'type' => 'Primary'],
        ];

        $this->assertCount(5, $fruitsRight);
        $this->assertSame($expected, $fruitsRight);
    }

    /** @testdox It should be possible to INSERT a single or multiple tables and return the primary key. It should then possible to get the same values back using find() */
    public function testInsertAndFind(): void
    {
        $this->queryBuilderProvider('mock_', 'BB');

        // Insert Single
        $new =  \BB::table('foo')
            ->insert([
                'string' => "testInsertAndFind",
                'number' => 12
            ]);

        $result = \BB::table('foo')->find($new);
        $this->assertEquals($new, $result->id);
        $this->assertEquals("testInsertAndFind", $result->string);
        $this->assertEquals('12', $result->number);

        // Insert Multiple
        $new =  \BB::table('foo')
            ->insert([
                ['string' => "a", 'number' => 1],
                ['string' => "b", 'number' => 2],
            ]);

        // a
        $a = \BB::table('foo')->find($new[0]);
        $this->assertEquals($new[0], $a->id);
        $this->assertEquals("a", $a->string);
        $this->assertEquals('1', $a->number);

        // b
        $b = \BB::table('foo')->find($new[1]);
        $this->assertEquals($new[1], $b->id);
        $this->assertEquals("b", $b->string);
        $this->assertEquals('2', $b->number);
    }

    /** @testdox [WPDB] It should be possible to update a row using a where clause and have it indicated with boolean value if the update was successful (false if not updated) */
    public function testUpdate()
    {
        $this->wpdb->insert('mock_foo', ['string' => 'Primary', 'number' => 1], ['%s', '%d']);

        $updated = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('number', 1)
            ->update(['string' => 'updated']);
        // As this has been updated, we should get a 1 to denote 1 row updated.
        $this->assertEquals(1, $updated);

        // Check the value was updated.
        $this->assertEquals(
            'updated',
            $this->queryBuilderProvider()->table('mock_foo')->find(1, 'number')->string
        );

        // Update with the same value, should return 0 as not changed.
        $updated = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('number', 1)
            ->update(['string' => 'updated']);
        $this->assertEquals(0, $updated);
    }

    /** @testdox [WPDB] It should be possible to use updateOrInsert/upsert to either add a unique dataset or update an existing one. */
    public function testUpsert()
    {
        $builder = $this->queryBuilderProvider();

        // INSERT
        $id = $builder->table('mock_foo')->updateOrInsert(['string' => 'first', 'number' => 24]);

        // Check we actually inserted the row.
        $this->assertTrue($id >= 1);
        $this->assertEquals('first', $builder->table('mock_foo')->find(24, 'number')->string);

        // UPDATE
        $updated = $builder->table('mock_foo')->updateOrInsert(['string' => 'second', 'number' => 24]);

        // Check we updated.
        $this->assertEquals(1, $updated);
        $this->assertEquals('second', $builder->table('mock_foo')->find(24, 'number')->string);
    }
}
