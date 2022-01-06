<?php

namespace Pixie;

use Viocon\Container;
use Pixie\QueryBuilder\Raw;

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
    protected $wpdbInstance;

    /**
     * @var Connection
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
        $this->wpdbInstance = $wpdb;

        $this->setAdapterConfig($adapterConfig);

        $this->container = $container ?? new Container();

        // Create event dependency
        $this->eventHandler = $this->container->build('\\Pixie\\EventHandler');

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
        class_alias('Pixie\\AliasFacade', $alias);
        $builder = $this->container->build('\\Pixie\\QueryBuilder\\QueryBuilderHandler', array( $this ));
        AliasFacade::setQueryBuilderInstance($builder);
    }

    /**
     * Returns an instance of Query Builder
     */
    public function getQueryBuilder()
    {
        return $this->container->build('\\Pixie\\QueryBuilder\\QueryBuilderHandler', array( $this ));
    }


    /**
     * Create the connection adapter
     */
    protected function connect()
    {
    //     // Build a database connection if we don't have one connected

    //     $adapter = '\\Pixie\\ConnectionAdapters\\' . ucfirst(strtolower($this->adapter));

    //     $adapterInstance = $this->container->build($adapter, array( $this->container ));

    //     $wpdb = $this->wpdbInstance;
    //     $this->setDbInstance($wpdb);

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
        $this->wpdbInstance = $wpdb;
        return $this;
    }

    /**
     * @return \wpdb
     */
    public function getDbInstance()
    {
        return $this->wpdbInstance;
    }

    /**
     * @param $adapter
     *
     * @return $this
     */
    public function setAdapter($adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * @return string
     */
    public function getAdapter()
    {
        return $this->adapter;
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
        return static::$storedConnection;
    }
}
