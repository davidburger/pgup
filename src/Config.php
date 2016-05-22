<?php

namespace PgUp;

use ArrayObject;
use PgUp\Exception\InvalidArgumentException;
use PgUp\Exception\RuntimeException;
use Exception;

class Config extends ArrayObject
{
    /**
     * @var string
     */
    private $env;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @param $env
     * @return $this
     */
    public function setEnv($env)
    {
        $this->env = $env;
        return $this;
    }

    public function getEnv()
    {
        if (null === $this->env) {
            $defaultEnv = $this->offsetGet('default_environment');

            if (!$defaultEnv) {
                throw new RuntimeException('Configuration error - undefined environment name');
            }

            $this->env = $defaultEnv;
        }

        return $this->env;
    }
    /**
     * @param $name
     * @param bool $required
     * @return null
     * @throws \Exception
     */
    public function getEnvironmentVar($name, $required = true)
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Undefined name');
        }

        if (!$this->env) {
            $this->getEnv();
        }
        $envConfig = $this->offsetGet('environments');

        if (empty($envConfig[$this->env])) {
            throw new InvalidArgumentException('Undefined env config');
        }

        $config = $envConfig[$this->env];

        if (!empty($config[$name])) {
            return $config[$name];
        }

        if ($required) {
            throw new InvalidArgumentException(
                sprintf('Undefined config value `%s` for env `%s`', $name, $this->env)
            );
        }

        return null;
    }

    /**
     * @param string $projectDir
     * @return $this
     */
    public function setProjectDir($projectDir)
    {
        $this->projectDir = $projectDir;
        return $this;
    }

    /**
     * @return string
     */
    public function getProjectDir()
    {
        if (null === $this->projectDir) {
            throw new RuntimeException('Project directory is not defined');
        }
        return $this->projectDir;
    }
}
