<?php

namespace ApolloPY\Flysystem\AliyunOss;

use Storage;
use OSS\OssClient;
use League\Flysystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use ApolloPY\Flysystem\AliyunOss\Plugins\CtySetBucket;
use ApolloPY\Flysystem\AliyunOss\Plugins\CtyDirectUploadSignature;
use ApolloPY\Flysystem\AliyunOss\Plugins\CtyPutFile;
use ApolloPY\Flysystem\AliyunOss\Plugins\CtyGetFile;
use ApolloPY\Flysystem\AliyunOss\Plugins\PutFile;
use ApolloPY\Flysystem\AliyunOss\Plugins\SignedDownloadUrl;

/**
 * Aliyun Oss ServiceProvider class.
 *
 * @author  ApolloPY <ApolloPY@Gmail.com>
 */
class AliyunOssServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('oss', function ($app, $config) {
            $accessId = $config['access_id'];
            $accessKey= $config['access_key'];
            $endPoint = $config['endpoint_private'];
            $bucket   = $config['bucket_private'];

            $prefix = null;
            if (isset($config['prefix'])) {
                $prefix = $config['prefix'];
            }

            $client = new OssClient($accessId, $accessKey, $endPoint,true);
            $adapter = new AliyunOssAdapter($client, $bucket, $prefix);

            $filesystem = new Filesystem($adapter);
            $filesystem->addPlugin(new CtySetBucket());
            $filesystem->addPlugin(new CtyDirectUploadSignature());
            $filesystem->addPlugin(new CtyPutFile());
            $filesystem->addPlugin(new CtyGetFile());
            $filesystem->addPlugin(new PutFile());
            $filesystem->addPlugin(new SignedDownloadUrl());

            return $filesystem;
        });
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
