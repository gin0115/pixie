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
use Pixie\QueryBuilder;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestQueryBuilder extends WP_UnitTestCase
{

//         /**
//      * Runs the routine before setting up all tests.
//      */
//     public static function setUpBeforeClass()
//     {
//         $wpdb = new Logable_WPDB();
//         $GLOBALS['wpdb'] = $wpdb;

//         $wpdb->suppress_errors = false;
//         $wpdb->show_errors     = true;
//         // $wpdb->db_connect();
//         ini_set('display_errors', '1');
// dump($GLOBALS['wpdb']);
//         // parent::setUpBeforeClass();

//         $class = get_called_class();

//         if (method_exists($class, 'wpSetUpBeforeClass')) {
//             call_user_func(array( $class, 'wpSetUpBeforeClass' ), self::factory());
//         }

//         self::commit_transaction();
//     }

    public function __testCreateBuilder(): void
    {
    // @var /wpdb
        $GLOBALS['wpdb']->insert('wpphpunittests_posts', ['post_title' => 'foo'], ['%s']);



        $connection = new Connection($GLOBALS['wpdb'], ['prefix' => 'wpphpunittests_']);
        $builder = new QueryBuilderHandler($connection, \OBJECT);


        $results =  $builder
            ->table('posts')
            ->where('post_title', '=', 'foo')
            ->get();
        // dump($results);
    }
}
