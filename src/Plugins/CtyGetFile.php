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
class CtyGetFile extends AbstractPlugin
{
    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'ctyGetFile';
    }

    /**
     * Handle.
     *
     * @param string $ossFilePath
     * @param int 默认十年
     */
    public function handle($ossFilePath, $useSsl=true, $timeout=3600)
    {
        if (! method_exists($this->filesystem, 'getAdapter')) {
            return false;
        }

        if (! method_exists($this->filesystem->getAdapter(), 'ctyGetFile')) {
            return false;
        }

        return $this->filesystem->getAdapter()->ctyGetFile($ossFilePath, $useSsl, $timeout);
    }

}
