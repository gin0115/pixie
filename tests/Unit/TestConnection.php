<?php

declare(strict_types=1);

/**
 * Unit tests custom WPDB base Pixie connection
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit;

use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\Tests\Logable_WPDB;

class TestConnection extends WP_UnitTestCase
{
    /** @testdox It should be possible to create a connection using WPDB */
    public function testWPDBConnection(): void
    {
        $wpdb = new Logable_WPDB();
        $connection = new Connection($wpdb);
        $this->assertSame($wpdb, $connection->getDbInstance());
    }

    /** @testdox It should be possible to set the wpdb instance. */
    public function testSetDbInstance(): void
    {
        // Create with global wpdb
        $connection = new Connection($GLOBALS['wpdb']);
        // Set with custom
        $wpdb = new Logable_WPDB();
        $connection->setDbInstance($wpdb);
        $this->assertSame($wpdb, $connection->getDbInstance());
    }
}
