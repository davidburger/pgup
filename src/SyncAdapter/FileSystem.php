<?php

namespace PgUp\SyncAdapter;

use PgUp\Exception\RuntimeException;

class FileSystem extends AdapterAbstract
{
    /**
     * @return string
     */
    private function getAppliedPath()
    {
        $dir = $this->config->getProjectDir()
            . '/migrations/sql/applied/' . $this->config->getEnv() . '/';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * @return array
     */
    public function getAppliedFiles()
    {
        return array_map(
            function($file) {
                return pathinfo($file, PATHINFO_BASENAME);
            },
            glob($this->getAppliedPath() . '*.sql')
        );
    }

    /**
     * @param $path
     * @return $this
     * @throws \Exception
     */
    public function setApplied($path)
    {
        $sqlFile = pathinfo($path, PATHINFO_BASENAME);
        $target = $this->getAppliedPath() . $sqlFile;
        $copied = copy($path, $target);

        if (!$copied) {
            throw new RuntimeException(sprintf('Copy failed for %s to %s', $path, $target));
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function init()
    {
        $appliedDir = $this->config->getProjectDir() . '/migrations/sql/applied';

        if (!mkdir($appliedDir, 0775, true)) {
            throw new RuntimeException('Cannot create sql/applied folder');
        }

        return $this;
    }
}
