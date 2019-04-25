# AsLong/AliyunOSS

## 安装

### Composer 安装扩展包

~~~
$ composer require aslong/aliyun-oss
~~~


## 配置文件

~~~
config/filesystems.php


'aslong-oss' => [
    'driver'            => 'aliyunoss',
    'access_id'         => env('ASLONG_OSS_ACCESS_ID'),
    'access_key'        => env('ASLONG_OSS_ACCESS_KEY'),
    'bucket'            => env('ASLONG_OSS_BUCKET'),
    'endpoint'          => env('ASLONG_OSS_ENDPOINT'),
    'endpoint_internal' => env('ASLONG_OSS_INTERNAL'),
    'cdnDomain'         => env('ASLONG_OSS_CDNDOMAIN'),
    'ssl'               => env('ASLONG_OSS_SLL'),
    'isCName'           => env('ASLONG_OSS_ISCNAME'),
    'debug'             => env('ASLONG_OSS_DEBUG'),
]
~~~

