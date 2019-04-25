<?php

namespace AsLong\OSS;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use League\Flysystem\Util;
use OSS\Core\OssException;
use OSS\OssClient;

class OssAdapter extends AbstractAdapter
{

    protected $debug;

    /**
     * @var array
     */
    protected static $resultMap = [
        'Body'           => 'raw_contents',
        'Content-Length' => 'size',
        'ContentType'    => 'mimetype',
        'Size'           => 'size',
        'StorageClass'   => 'storage_class',
    ];

    /**
     * @var array
     */
    protected static $metaOptions = [
        'CacheControl',
        'Expires',
        'ServerSideEncryption',
        'Metadata',
        'ACL',
        'ContentType',
        'ContentDisposition',
        'ContentLanguage',
        'ContentEncoding',
    ];

    protected static $metaMap = [
        'CacheControl'         => 'Cache-Control',
        'Expires'              => 'Expires',
        'ServerSideEncryption' => 'x-oss-server-side-encryption',
        'Metadata'             => 'x-oss-metadata-directive',
        'ACL'                  => 'x-oss-object-acl',
        'ContentType'          => 'Content-Type',
        'ContentDisposition'   => 'Content-Disposition',
        'ContentLanguage'      => 'response-content-language',
        'ContentEncoding'      => 'Content-Encoding',
    ];

    //Aliyun OSS Client OssClient
    protected $client;
    //bucket name
    protected $bucket;

    protected $endPoint;

    protected $cdnDomain;

    protected $ssl;

    protected $isCname;

    //配置
    protected $options = [
        'Multipart' => 128,
    ];

    /**
     * 初始化适配器
     * @param OssClient $client
     * @param string    $bucket
     * @param string    $endPoint
     * @param bool      $ssl
     * @param bool      $isCname
     * @param bool      $debug
     * @param null      $prefix
     * @param array     $options
     */
    public function __construct(OssClient $client, $bucket, $endPoint, $ssl, $isCname = false, $debug = false, $cdnDomain, $prefix = null, array $options = [])
    {
        $this->debug  = $debug;
        $this->client = $client;
        $this->bucket = $bucket;
        $this->setPathPrefix($prefix);
        $this->endPoint  = $endPoint;
        $this->ssl       = $ssl;
        $this->isCname   = $isCname;
        $this->cdnDomain = $cdnDomain;
        $this->options   = array_merge($this->options, $options);
    }

    /**
     * 获取bucket名称
     * @return string
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * 获取OSS实例
     * @return OssClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * 写入新文件
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        $object  = $this->applyPathPrefix($path);
        $options = $this->getOptions($this->options, $config);

        if (!isset($options[OssClient::OSS_LENGTH])) {
            $options[OssClient::OSS_LENGTH] = Util::contentSize($contents);
        }
        if (!isset($options[OssClient::OSS_CONTENT_TYPE])) {
            $options[OssClient::OSS_CONTENT_TYPE] = Util::guessMimeType($path, $contents);
        }
        try {
            $this->client->putObject($this->bucket, $object, $contents, $options);
        } catch (OssException $e) {
            return false;
        }
        return $this->normalizeResponse($options, $path);
    }

    /**
     * 使用stream写入文件
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        $options  = $this->getOptions($this->options, $config);
        $contents = stream_get_contents($resource);

        return $this->write($path, $contents, $config);
    }

    /**
     * 更新文件
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        if (!$config->has('visibility') && !$config->has('ACL')) {
            $config->set(static::$metaMap['ACL'], $this->getObjectACL($path));
        }

        return $this->write($path, $contents, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        $contents = stream_get_contents($resource);
        return $this->update($path, $contents, $config);
    }

    /**
     * 重命名文件
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:50:03+0800
     * @param [type] $path [description]
     * @param [type] $newpath [description]
     * @return [type] [description]
     */
    public function rename($path, $newpath)
    {
        if (!$this->copy($path, $newpath)) {
            return false;
        }

        return $this->delete($path);
    }

    /**
     * 复制文件
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:50:14+0800
     * @param [type] $path [description]
     * @param [type] $newpath [description]
     * @return [type] [description]
     */
    public function copy($path, $newpath)
    {
        $object    = $this->applyPathPrefix($path);
        $newObject = $this->applyPathPrefix($newpath);
        try {
            $this->client->copyObject($this->bucket, $object, $this->bucket, $newObject);
        } catch (OssException $e) {
            return false;
        }

        return true;
    }

    /**
     * 删除文件
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:50:33+0800
     * @param [type] $path [description]
     * @return [type] [description]
     */
    public function delete($path)
    {
        $bucket = $this->bucket;
        $object = $this->applyPathPrefix($path);

        try {
            $this->client->deleteObject($bucket, $object);
        } catch (OssException $e) {
            return false;
        }

        return !$this->has($path);
    }

    /**
     * 删除目录
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:50:51+0800
     * @param [type] $dirname [description]
     * @return [type] [description]
     */
    public function deleteDir($dirname)
    {
        $dirname    = rtrim($this->applyPathPrefix($dirname), '/') . '/';
        $dirObjects = $this->listDirObjects($dirname, true);

        if (count($dirObjects['objects']) > 0) {

            foreach ($dirObjects['objects'] as $object) {
                $objects[] = $object['Key'];
            }

            try {
                $this->client->deleteObjects($this->bucket, $objects);
            } catch (OssException $e) {
                return false;
            }

        }

        try {
            $this->client->deleteObject($this->bucket, $dirname);
        } catch (OssException $e) {
            return false;
        }

        return true;
    }

    /**
     * 创建文件夹
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:51:14+0800
     * @param [type] $dirname [description]
     * @param Config $config [description]
     * @return [type] [description]
     */
    public function createDir($dirname, Config $config)
    {
        $object  = $this->applyPathPrefix($dirname);
        $options = $this->getOptionsFromConfig($config);

        try {
            $this->client->createObjectDir($this->bucket, $object, $options);
        } catch (OssException $e) {
            return false;
        }

        return ['path' => $dirname, 'type' => 'dir'];
    }

    /**
     * 设置文件可见性
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:51:24+0800
     * @param [type] $path [description]
     * @param [type] $visibility [description]
     */
    public function setVisibility($path, $visibility)
    {
        $object = $this->applyPathPrefix($path);
        $acl    = ($visibility === AdapterInterface::VISIBILITY_PUBLIC) ? OssClient::OSS_ACL_TYPE_PUBLIC_READ : OssClient::OSS_ACL_TYPE_PRIVATE;

        $this->client->putObjectAcl($this->bucket, $object, $acl);

        return compact('visibility');
    }

    /**
     * 文件是否存在
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:57:19+0800
     * @param [type] $path [description]
     * @return boolean [description]
     */
    public function has($path)
    {
        $object = $this->applyPathPrefix($path);
        return $this->client->doesObjectExist($this->bucket, $object);
    }

    /**
     * 读取文件
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:57:36+0800
     * @param [type] $path [description]
     * @return [type] [description]
     */
    public function read($path)
    {
        $result = $this->readObject($path);

        $result['contents'] = (string) $result['raw_contents'];
        unset($result['raw_contents']);
        return $result;
    }

    /**
     * 读取文件流
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:58:03+0800
     * @param [type] $path [description]
     * @return [type] [description]
     */
    public function readStream($path)
    {
        $result           = $this->readObject($path);
        $result['stream'] = $result['raw_contents'];
        rewind($result['stream']);
        // Ensure the EntityBody object destruction doesn't close the stream
        $result['raw_contents']->detachStream();
        unset($result['raw_contents']);

        return $result;
    }

    /**
     * 列出内容
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:58:34+0800
     * @param string $directory [description]
     * @param boolean $recursive [description]
     * @return [type] [description]
     */
    public function listContents($directory = '', $recursive = false)
    {
        $dirObjects = $this->listDirObjects($directory, true);
        $contents   = $dirObjects["objects"];

        $result = array_map([$this, 'normalizeResponse'], $contents);
        $result = array_filter($result, function ($value) {
            return $value['path'] !== false;
        });

        return Util::emulateDirectories($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $object = $this->applyPathPrefix($path);

        try {
            $objectMeta = $this->client->getObjectMeta($this->bucket, $object);
        } catch (OssException $e) {
            $this->logErr(__FUNCTION__, $e);
            return false;
        }

        return $objectMeta;
    }

    /**
     * 获取文件尺寸
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:59:04+0800
     * @param [type] $path [description]
     * @return [type] [description]
     */
    public function getSize($path)
    {
        $object         = $this->getMetadata($path);
        $object['size'] = $object['content-length'];
        return $object;
    }

    /**
     * 获取文件mime类型
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:59:22+0800
     * @param [type] $path [description]
     * @return [type] [description]
     */
    public function getMimetype($path)
    {
        if ($object = $this->getMetadata($path)) {
            $object['mimetype'] = $object['content-type'];
        }

        return $object;
    }

    /**
     * 获取文件最后修改时间
     * @Author:<C.Jason>
     * @Date:2019-04-24T13:59:34+0800
     * @param [type] $path [description]
     * @return [type] [description]
     */
    public function getTimestamp($path)
    {
        if ($object = $this->getMetadata($path)) {
            $object['timestamp'] = strtotime($object['last-modified']);
        }

        return $object;
    }

    /**
     * 获取文件可见性
     * @Author:<C.Jason>
     * @Date:2019-04-24T14:00:49+0800
     * @param [type] $path [description]
     * @return [type] [description]
     */
    public function getVisibility($path)
    {
        $object = $this->applyPathPrefix($path);
        try {
            $acl = $this->client->getObjectAcl($this->bucket, $object);
        } catch (OssException $e) {
            $this->logErr(__FUNCTION__, $e);
            return false;
        }

        if ($acl == OssClient::OSS_ACL_TYPE_PUBLIC_READ) {
            $res['visibility'] = AdapterInterface::VISIBILITY_PUBLIC;
        } else {
            $res['visibility'] = AdapterInterface::VISIBILITY_PRIVATE;
        }

        return $res;
    }

    /**
     * 获取文件URL地址
     * @param $path
     * @return string
     */
    public function getUrl($path)
    {
        if (!$this->has($path)) {
            throw new \Exception($path . ' not found');
        }

        return ($this->ssl ? 'https://' : 'http://') . ($this->isCname ? ($this->cdnDomain == '' ? $this->endPoint : $this->cdnDomain) : $this->bucket . '.' . $this->endPoint) . '/' . ltrim($path, '/');
    }

    /**
     * 基础的信息返回
     * @param array  $object
     * @param string $path
     * @return array file metadata
     */
    protected function normalizeResponse(array $object, $path = null)
    {
        $result = ['path' => $path ?: $this->removePathPrefix(isset($object['Key']) ? $object['Key'] : $object['Prefix'])];

        $result['dirname'] = Util::dirname($result['path']);

        if (isset($object['LastModified'])) {
            $result['timestamp'] = strtotime($object['LastModified']);
        }

        if (substr($result['path'], -1) === '/') {
            $result['type'] = 'dir';
            $result['path'] = rtrim($result['path'], '/');

            return $result;
        }

        $result = array_merge($result, Util::map($object, static::$resultMap), ['type' => 'file']);

        return $result;
    }

    /**
     * Get options for a OSS call. done
     * @param array  $options
     * @return array OSS options
     */
    protected function getOptions(array $options = [], Config $config = null)
    {
        $options = array_merge($this->options, $options);

        if ($config) {
            $options = array_merge($options, $this->getOptionsFromConfig($config));
        }

        return array(OssClient::OSS_HEADERS => $options);
    }

    /**
     * 获取配置
     * @param Config $config
     * @return array
     */
    protected function getOptionsFromConfig(Config $config)
    {
        $options = [];

        foreach (static::$metaOptions as $option) {
            if (!$config->has($option)) {
                continue;
            }
            $options[static::$metaMap[$option]] = $config->get($option);
        }

        if ($visibility = $config->get('visibility')) {
            $options['x-oss-object-acl'] = $visibility === AdapterInterface::VISIBILITY_PUBLIC ? OssClient::OSS_ACL_TYPE_PUBLIC_READ : OssClient::OSS_ACL_TYPE_PRIVATE;
        }

        if ($mimetype = $config->get('mimetype')) {
            $options['Content-Type'] = $mimetype;
        }

        return $options;
    }
}
