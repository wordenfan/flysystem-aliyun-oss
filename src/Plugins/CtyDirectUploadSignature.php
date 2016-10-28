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
class CtyDirectUploadSignature extends AbstractPlugin
{
    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'ctyDirectUploadSignature';
    }

    /**
     * Handle.
     *
     * @param string $dir
     * @return json
     */
    public function handle($dir,$expire,$useSsl=true)
    {
        if (! method_exists($this->filesystem, 'getAdapter')) {
            return false;
        }

        if (! method_exists($this->filesystem->getAdapter(), 'ctyDirectUploadSignature')) {
            return false;
        }

        return $this->filesystem->getAdapter()->ctyDirectUploadSignature($dir,$expire,$useSsl);
    }
}
