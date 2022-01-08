<?php

namespace Pixie;

use Viocon\Container;
use Pixie\AliasFacade;
use Pixie\EventHandler;
use Pixie\QueryBuilder\Raw;
use Pixie\QueryBuilder\QueryBuilderHandler;

class Connection
{

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var string
     */
    protected $adapter;

    /**
     * @var array
     */
    protected $adapterConfig;

    /**
     * @var \wpdb
     */
    protected $dbInstance;

    /**
     * @var Connection|null
     */
    protected static $storedConnection;

    /**
     * @var EventHandler
     */
    protected $eventHandler;

    /**
     * @param               $adapter
     * @param array         $adapterConfig
     * @param null|string   $alias
     * @param Container     $container
     */
    public function __construct(\wpdb $wpdb, array $adapterConfig = [], $alias = null, Container $container = null)
    {
        $this->dbInstance = $wpdb;

        $this->setAdapterConfig($adapterConfig);

        $this->container = $container ?? new Container();

        // Create event dependency
        $this->eventHandler = $this->container->build(EventHandler::class);

        if ($alias) {
            $this->createAlias($alias);
        }
    }

    /**
     * Create an easily accessible query builder alias
     *
     * @param $alias
     */
    public function createAlias($alias)
    {
        class_alias(AliasFacade::class, $alias);
        $builder = $this->container->build(QueryBuilderHandler::class, array( $this ));
        AliasFacade::setQueryBuilderInstance($builder);
    }

    /**
     * Returns an instance of Query Builder
     */
    public function getQueryBuilder()
    {
        return $this->container->build(QueryBuilderHandler::class, array( $this ));
    }


    /**
     * Create the connection adapter
     */
    protected function connect()
    {
        // Preserve the first database connection with a static property
        if (! static::$storedConnection) {
            static::$storedConnection = $this;
        }
    }

    /**
     * @param \wpdb $wpdb
     *
     * @return $this
     */
    public function setDbInstance(\wpdb $wpdb)
    {
        $this->dbInstance = $wpdb;
        return $this;
    }

    /**
     * @return \wpdb
     */
    public function getDbInstance()
    {
        return $this->dbInstance;
    }

    /**
     * @param array $adapterConfig
     *
     * @return $this
     */
    public function setAdapterConfig(array $adapterConfig)
    {
        $this->adapterConfig = $adapterConfig;
        return $this;
    }

    /**
     * @return array
     */
    public function getAdapterConfig()
    {
        return $this->adapterConfig;
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @return EventHandler
     */
    public function getEventHandler()
    {
        return $this->eventHandler;
    }

    /**
     * @return Connection
     */
    public static function getStoredConnection()
    {
        if (null === static::$storedConnection) {
            throw new Exception("No initial instance of Connection created");
        }
        return static::$storedConnection;
    }
}
