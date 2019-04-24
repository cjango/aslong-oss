<?php

namespace AsLong\OSS;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use League\Flysystem\Filesystem;
use OSS\OssClient;

class ServiceProvider extends LaravelServiceProvider
{

    /**
     * Bootstrap the application services.
     * @return void
     */
    public function boot()
    {
        Storage::extend('aliyunoss', function ($app, $config) {
            $accessId  = $config['access_id'];
            $accessKey = $config['access_key'];

            $cdnDomain  = empty($config['cdnDomain']) ? '' : $config['cdnDomain'];
            $bucket     = $config['bucket'];
            $ssl        = empty($config['ssl']) ? false : $config['ssl'];
            $isCname    = empty($config['isCName']) ? false : $config['isCName'];
            $debug      = empty($config['debug']) ? false : $config['debug'];
            $endPoint   = $config['endpoint'];
            $epInternal = $isCname ? $cdnDomain : (empty($config['endpoint_internal']) ? $endPoint : $config['endpoint_internal']);

            $client  = new OssClient($accessId, $accessKey, $epInternal, $isCname);
            $adapter = new OssAdapter($client, $bucket, $endPoint, $ssl, $isCname, $debug, $cdnDomain);

            $filesystem = new Filesystem($adapter);

            // $filesystem->addPlugin(new PutFile());
            // $filesystem->addPlugin(new PutRemoteFile());

            return $filesystem;
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
