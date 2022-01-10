<?php

declare(strict_types=1);

/**
 * Unit tests for the Query Builder Handler class.
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests;

use Exception;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestQueryBuilderHandler extends WP_UnitTestCase
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

    /**
     * @testdox It should not be possible to create a handler without either passing a connection of one already existing stored as a static to the class.
     * @runInSeparateProcess Run in own process due to static property.
     * @preserveGlobalState disabled
     */
    public function testCantInitialiseWithoutConnection()
    {
        $this->expectExceptionMessage('No initial instance of Connection created');
        $this->expectException(Exception::class);
        new QueryBuilderHandler();
    }

    /** @testdox It should be possible to change the connection being used and access not only the connection, but the underlying MYSQL connection (wpdb) */
    public function testSetGetConnection(): void
    {
        $builder = $this->queryBuilderProvider('prefix_');
        $initialConnection = $builder->getConnection();
        $this->assertSame($this->wpdb, $builder->dbInstance());

        $connection = new Connection($this->createMock('wpdb'), []);
        $builder->setConnection($connection);
        $this->assertSame($connection, $builder->getConnection());
        $this->assertNotSame($connection, $initialConnection);
    }

    /** @testdox It should be possible to create a new query builder instance, using either the current connection or a custom one. */
    public function testCreateNewQuery(): void
    {
        $builder = $this->queryBuilderProvider('prefix_');

        // Using the same connection.
        $copyBuilder = $builder->newQuery();
        $this->assertSame($builder->getConnection(), $copyBuilder->getConnection());

        // Using custom connection.
        $connection = new Connection($this->createMock(\wpdb::class), []);
        $customBuilder = $builder->newQuery($connection);
        $this->assertSame($connection, $customBuilder->getConnection());
    }


}
