<?php

namespace PgUp;
use PgUp\Exception\InvalidArgumentException;
use PgUp\Exception\RuntimeException;
use PgUp\SyncAdapter\FileSystem;

/**
 * Class Migrator
 * @package DbUp
 * @author David Burger
 */
class Migrator
{
    const PSQL_CMD = 'psql -v ON_ERROR_STOP=1';

    const SYNC_MODE_FS = 'filesystem';
    const SYNC_MODE_DB = 'database';

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var array
     */
    private $arguments;

    /**
     * @var string
     */
    private $env;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var string
     */
    private $logDir;

    /**
     * @var bool
     */
    private $createFromTemplate = false;

    /**
     * @var
     */
    private $adapter;

    /**
     * @var bool
     */
    private $pgPassChecked = false;


    function __construct(array $arguments)
    {
        $this->projectDir = realpath(__DIR__ . '/../../../../'); //supposed composer usage

        $this->arguments = $this->checkArgs($arguments);

        $this->configFactory = ConfigFactory::fromProjectDir($this->projectDir);
        if (!$this->isInitialized()) {
            $this->initMigrations();
        }

        $this->config = $this->configFactory->create();
    }

    /**
     * @param $args
     * @return array
     */
    private function checkArgs($args)
    {
        unset($args[0]);

        $arguments = [];
        array_walk($args, function($a) use(&$arguments) {

            $name = $a;
            if (strpos($a, '=')) {
                list($name, $value) = explode('=', $a, 2);
            }

            switch($name) {
                case '--env':
                    $this->env = $value;
                    $this->config->setEnv($this->env);
                    break;

                case 'create':
                    $this->createFromTemplate = true;
                    break;

                case '--comment':
                    $arguments['comment'] = $value;
                    break;

                default:
                    throw new InvalidArgumentException(sprintf('Unknow attribute %s`', $name));
            }
        });
        return $arguments;
    }

    /**
     * @return bool
     */
    private function isInitialized()
    {
        return is_dir($this->projectDir . '/migrations');
    }

    /**
     * @param $msg
     * @param bool $newline
     * @return $this
     */
    private function consoleOutput($msg, $newline = true)
    {
        echo $msg . ($newline ? PHP_EOL : '');
        return $this;
    }

    private function initMigrations()
    {
        $configPath = $this->configFactory->init()
            ->getConfigPath();

        $this->getAdapter()->init();

        $this->consoleOutput('Please change database settings in ' . $configPath . PHP_EOL);
    }

    /**
     * @param $name
     * @param null $defaultValue
     * @param bool $required
     * @return null
     * @throws InvalidArgumentException
     */
    private function getConfig($name, $defaultValue = null, $required = true)
    {
        if (isset($this->config[$name])) {
            return $this->config[$name];
        }

        if ($required) {

            var_dump($this->config);exit;
            throw new InvalidArgumentException(sprintf('Undefined config value `%s`', $name));
        }

        return $defaultValue;
    }

    /**
     * @return $this
     * @throws RuntimeException
     */
    private function createFromTemplate()
    {
        $filename = date('YmdHis') . '__';

        $comment = 'add_sql_rename_this_to_something_meaningfull';
        if (!empty($this->arguments['comment'])) {
            $comment = $this->arguments['comment'];
        }

        $filename .= preg_replace('/[^a-z0-9_]+/i', '_', $comment);

        $filename .= '.sql';

        $template = file_get_contents(__DIR__ . '/../template/default.tpl');

        if (!$template) {
            throw new RuntimeException('Error while reading from template');
        }

        $path = $this->projectDir . '/migrations/sql/' . $filename;

        $created = file_put_contents($path, $template);

        if (!$created) {
            throw new RuntimeException('Error while writing file ' . $path);
        }

        $this->consoleOutput('New migration file ' . $path . ' created.')
             ->consoleOutput('Please edit it and run migration with command:')
             ->consoleOutput(' `$ composer dbup`' . PHP_EOL);

        return $this;
    }

    /**
     * @return SyncAdapter\Database|FileSystem
     */
    private function getAdapter()
    {
        if (null === $this->adapter) {

            $syncMode = $this->getConfig('sync_mode');
            
            switch($syncMode) {
                case self::SYNC_MODE_FS:
                    $this->adapter = new SyncAdapter\FileSystem($this->config);
                    break;
                case self::SYNC_MODE_DB:
                    $this->adapter = new SyncAdapter\Database($this->config);
                    break;
                default:
                    throw new InvalidArgumentException(
                        sprintf('Undefined sync mode %s', $syncMode)
                    );
            }
        }
        
        return $this->adapter;
    }

    public function run()
    {
        $this->consoleOutput('* Running PHP Postgres Migration tools ...'.PHP_EOL);
        if (!$this->env) {
            $this->env = $this->config->getEnv();
        }

        if ($this->createFromTemplate) {
            return $this->createFromTemplate();
        }

        $applied = $this->getAdapter()
            ->getAppliedFiles();

        $defined = array_map(
            function($file) {
                return pathinfo($file, PATHINFO_BASENAME);
            },
            glob($this->projectDir .'/migrations/sql/*.sql')
        );

        $unprocessed = array_diff($defined, $applied);

        if (empty($unprocessed)) {
            $this->consoleOutput('No unprocessed files found for env ' . $this->env . '.' . PHP_EOL);
        }

        $this->pgPassChecked = false;

        foreach($unprocessed as $sqlFile) {
            $this->processSqlFile($sqlFile);
        }
        return $this;
    }


    /**
     * @param $sqlFile
     * @param $output
     * @param bool $error
     * @return $this
     */
    private function log($sqlFile, $output, $error = true)
    {
        if (null === $this->logDir) {
            $this->logDir = $this->projectDir . '/' . $this->getConfig('log_dir') . '/' . $this->env . '/';

            if (!is_dir($this->logDir)) {
                if (!mkdir($this->logDir, 0775, true)) {
                    throw new RuntimeException('Error while creating log dir');
                }
            }
        }

        $filename = $this->logDir . pathinfo($sqlFile, PATHINFO_FILENAME);
        $filename .= $error ? '.error' : '.success';
        $filename .= '.' . pathinfo($sqlFile, PATHINFO_EXTENSION);

        if (false === file_put_contents($filename, $output)) {
            throw new RuntimeException('Cannot log file '.$filename);
        }

        return $this;
    }

    /**
     * @param $sqlFile
     * @return $this
     */
    private function processSqlFile($sqlFile)
    {
        $path = $this->projectDir .'/migrations/sql/'.$sqlFile;

        $this->consoleOutput('Processing sql file '.$sqlFile. ' ... ', false);

        $database = $this->config->getEnvironmentVar('database');
        $host = $this->config->getEnvironmentVar('host');
        $port = $this->config->getEnvironmentVar('port');
        $user = $this->config->getEnvironmentVar('user');
        $password = $this->config->getEnvironmentVar('password');

        if (!$this->pgPassChecked) {
            PgPass::check($host, $port, $database, $user, $password);
            $this->pgPassChecked = true;
        }

        $cmd = self::PSQL_CMD . ' -U ' . $user . ' -h ' . $host
            . ' -p ' . $port . ' -d ' . $database . ' < ' . $path . ' 2>&1';

        $output = [];
        $status = null;
        exec($cmd, $output, $status);

        $output = implode(PHP_EOL, $output);

        $resultWrittenMsg = sprintf(' - result written to %s%s%s',
            $this->getConfig('log_dir'),
            $this->env,
            PHP_EOL);

        if (empty($status)) {

            $this->getAdapter()->setApplied($path);

            $this->log($sqlFile, $output, false);
            $this->consoleOutput('done.')
                 ->consoleOutput($resultWrittenMsg);
            
            return $this;
        }

        $this->log($sqlFile, $output, true);
        $this->consoleOutput('failed.')
             ->consoleOutput($resultWrittenMsg);
        
        throw new RuntimeException(sprintf('Error while processing sql file %s', $sqlFile));
    }
}
