<?php

namespace ApolloPY\Flysystem\AliyunOss\Plugins;

use League\Flysystem\Config;
use League\Flysystem\Plugin\AbstractPlugin;

/**
 * PutFile class
 * 上传本地文件.
 *
 * @author  ApolloPY <ApolloPY@Gmail.com>
 */
class CtySetBucket extends AbstractPlugin
{
    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'ctySetBucket';
    }

    /**
     * Handle.
     *
     * @param string $path
     * @param string $isPublic
     * @param array  $config
     * @return bool
     */
    public function handle($isPublic=true)
    {
        if (! method_exists($this->filesystem, 'getAdapter')) {
            return false;
        }

        if (! method_exists($this->filesystem->getAdapter(), 'ctySetBucket')) {
            return false;
        }

        return $this->filesystem->getAdapter()->ctySetBucket($isPublic);
    }
}
