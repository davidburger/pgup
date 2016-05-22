<?php

namespace PgUp;

use PgUp\Exception\RuntimeException;

class PgPass
{
    const PATH = '~/.pgpass';

    /**
     * @param $host
     * @param $port
     * @param $database
     * @param $user
     * @param $password
     * @return bool
     * @throws RuntimeException
     */
    static public function check($host, $port, $database, $user, $password)
    {
        $record = implode(':', func_get_args());

        $exists = is_file(self::PATH);

        if ($exists) {
            $data = trim(file_get_contents(self::PATH));

            $lines = array_map(function($line) {
                return trim($line);
            }, explode("\n", $data));

            if (false !== array_search($record, $lines)) {
                return false;
            }
        }

        //vagrant www-data sync fix
        $cmd = sprintf('echo "%s" >> %s; chmod 0600 %s', $record, self::PATH, self::PATH);
        exec($cmd, $output, $status);

        if (!empty($status)) {
            throw new RuntimeException(sprintf('File `%s` setup error', self::PATH));
        }

        return true;
    }
}
