<?php

namespace PgUp;

use PgUp\Exception\InvalidArgumentException;
use PgUp\Exception\RuntimeException;

class ConfigFactory
{
    /**
     * @var string
     */
    private $projectDir;
    
    
    function __construct($projectDir)
    {
        if (null === $projectDir) {
            throw new InvalidArgumentException('Undefined projectDir');
        }

        if (!is_dir($projectDir)) {
            throw new InvalidArgumentException('Invalid projectDir');
        }

        $this->projectDir = $projectDir;
    }

    /**
     * @return string
     */
    public function getConfigDir()
    {
        return $this->projectDir . '/migrations/config';
    }

    /**
     * @return string
     */
    public function getConfigPath()
    {
        return $this->getConfigDir() . '/global.php';
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function init()
    {
        $configDir = $this->getConfigDir();
        $configPath = $this->getConfigPath();

        if (!mkdir($configDir, 0775, true)) {
            throw new RuntimeException('Cannot create config folder');
        }

        if (!copy(__DIR__ . '/../config/global.php.dist', $configPath)) {
            throw new RuntimeException('Cannot copy sample config file');
        }

        return $this;
    }

    /**
     * @param $projectDir
     * @return ConfigFactory
     */
    public static function fromProjectDir($projectDir)
    {
        return new self($projectDir);
    }

    /**
     * @return Config
     * @throws \Exception
     */
    public function create()
    {
        $data = include $this->getConfigPath();

        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid configuration');
        }

        return (new Config($data))
            ->setProjectDir($this->projectDir);
    }
}
