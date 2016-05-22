<?php

namespace PgUp\SyncAdapter;

use PDO;
use PgUp\Config;

class Database extends AdapterAbstract
{
    /**
     * @var PDO
     */
    private $connection;

    /**
     * @var string
     */
    private $schema;

    /**
     * @var string
     */
    private $table;

    public function __construct(Config $config)
    {
        parent::__construct($config);

        $this->table = $config['sync_table'];
        $this->schema = 'public'; //default

        $dotPosition = strpos($this->table, '.');
        if (false !== $dotPosition) {
            $this->schema = substr($this->table, 0, $dotPosition);
        }
    }

    /**
     * @return \PDO
     * @throws \PgUp\Exception\InvalidArgumentException
     */
    private function getConnection()
    {
        if (null === $this->connection) {
            $database = $this->config->getEnvironmentVar('database');
            $host = $this->config->getEnvironmentVar('host');
            $port = $this->config->getEnvironmentVar('port');
            $user = $this->config->getEnvironmentVar('user');
            $password = $this->config->getEnvironmentVar('password');

            $dsn = sprintf('pgsql:dbname=%s;host=%s;port=%d', $database, $host, $port);
            $this->connection = new PDO($dsn, $user, $password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return $this->connection;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setApplied($path)
    {
        $sqlFile = pathinfo($path, PATHINFO_BASENAME);

        $sql = sprintf("INSERT INTO %s (filename) VALUES(?)", $this->table);

        $this->getConnection()
            ->prepare($sql)
            ->execute([$sqlFile]);

        return $this;
    }


    /**
     * @return array
     */
    public function getAppliedFiles()
    {
        if (!$this->isInitialized()) {
            $this->init();
        }

        $sql = sprintf("SELECT filename FROM %s ORDER BY id", $this->table);

        $rows = $this->getConnection()
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function($row) {
            return $row['filename'];
        }, $rows);
    }

    /**
     * @return $this
     */
    private function createTableIfNotExists()
    {
        if ($this->schema !== 'public') {
            $this->getConnection()
                ->query(
                    sprintf('CREATE SCHEMA IF NOT EXISTS %s', $this->schema)
                );
        }

        $this->getConnection()
            ->query(
                sprintf('CREATE TABLE IF NOT EXISTS %s (' .
                    'id SERIAL NOT NULL PRIMARY KEY, ' .
                    'filename cHARACTER VARYING(250) NOT NULL, ' .
                    'created TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                    'UNIQUE(filename));', $this->table)
            );
        
        return $this;
    }

    /**
     * @return $this
     */
    private function syncWithFileSystem()
    {
        $fs = new FileSystem($this->config);
        $applied = $fs->getAppliedFiles();
        
        $sql = sprintf("INSERT INTO %s (filename) VALUES(?)", $this->table);

        $stmt = $this->getConnection()
            ->prepare($sql);
        
        foreach($applied as $filename) {
            $stmt->execute([$filename]);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function init()
    {
        $this->createTableIfNotExists();

        $stmt = $this->getConnection()
            ->query("SELECT COUNT(*) cnt FROM migration.migration");

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ((int)$row['cnt'] === 0) {
            $this->syncWithFileSystem();
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isInitialized()
    {
        try {
            $this->getConnection()
                ->query(
                    $sql = sprintf("SELECT '%s'::regclass", $this->table)
                );

            return true;

        } catch(\Exception $e) {
            return false;
        }
    }
}
