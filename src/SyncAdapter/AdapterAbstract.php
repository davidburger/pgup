<?php

namespace PgUp\SyncAdapter;

use PgUp\Config;

abstract class AdapterAbstract
{
    const PG_PASS = '~/.pgpass';
    
    /**
     * @var Config
     */
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    abstract public function getAppliedFiles();

    abstract public function setApplied($path);

    abstract public function init();
}
