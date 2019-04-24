# AsLong/AliyunOSS

## 安装
### Composer 安装扩展包
~~~
$ composer require aslong/aliyun-oss
~~~


## 配置文件
~~~
    'aslong-oss' => [
        'driver'            => 'aliyunoss',
        'access_id'         => '',
        'access_key'        => '',
        'bucket'            => 'bucket',
        'endpoint'          => 'oss-cn-qingdao.aliyuncs.com',
        'endpoint_internal' => 'oss-cn-qingdao-internal.aliyuncs.com',
        'cdnDomain'         => '<CDN domain, cdn域名>',
        'ssl'               => true,
        'isCName'           => false,
        'debug'             => true,
    ]
~~~

