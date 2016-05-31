<?php

namespace ApolloPY\Flysystem\AliyunOss;

use OSS\Http\ResponseCore;
use OSS\Http\RequestCore;
use OSS\OssClient;
use OSS\Core\OssException;
use League\Flysystem\Util;
use League\Flysystem\Config;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\StreamedTrait;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use Illuminate\Http\Request;
use DateTime;
use Log;

/**
 * Aliyun Oss Adapter class.
 *
 * @author  ApolloPY <ApolloPY@Gmail.com>
 */
class AliyunOssAdapter extends AbstractAdapter
{
    use StreamedTrait;
    use NotSupportingVisibilityTrait;

    /**
     * Aliyun Oss Client.
     *
     * @var \OSS\OssClient
     */
    protected $client;

    /**
     * bucket name.
     *
     * @var string
     */
    protected $bucket;
    /**
     * host name.
     *
     * @var string
     */
    protected $host;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    protected static $mappingOptions = [
        'mimetype' => OssClient::OSS_CONTENT_TYPE,
        'size'     => OssClient::OSS_LENGTH,
    ];

    /**
     * Constructor.
     *
     * @param OssClient $client
     * @param string    $bucket
     * @param string    $prefix
     * @param array     $options
     */
    public function __construct(OssClient $client, $bucket, $prefix = null, array $options = [])
    {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->setPathPrefix($prefix);
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Get the Aliyun Oss Client bucket.
     *
     * @param string $dir
     * @param bool $withHost
     *
     * @return array($bucket,$host)
     */
    public function getBucket($dir=null,$withHost=false)
    {
        if(!$dir){
            return $this->bucket;
        }
        //权限
        $public_dir  = config('filesystems.disks.oss.public');
        $private_dir = config('filesystems.disks.oss.private');
        $acl = 'public';
        if(in_array(explode('/',$dir)[0],$public_dir)){
            $acl = 'public';
        }elseif(in_array(explode('/',$dir)[0],$private_dir)){
            $acl = 'private';
        }
        //环境
        $bucket_key = 'filesystems.disks.oss.'.$acl.'_bucket';
        $host_key = 'filesystems.disks.oss.'.$acl.'_bucket';

        return $withHost ? [config($bucket_key),config($host_key)] : $bucket_key;
    }

    /**
     * Get the Aliyun Oss Client instance.
     *
     * @return \OSS\OssClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Write using a local file path.
     *
     * @param string $path
     * @param string $localFilePath
     * @param Config $config Config object
     * @return array|false false on failure file meta data on success
     */
    public function putFile($path, $localFilePath, Config $config)
    {
        $object = $this->applyPathPrefix($path);
        $options = $this->getOptionsFromConfig($config);

        $options[OssClient::OSS_CHECK_MD5] = true;

        if (! isset($options[OssClient::OSS_CONTENT_TYPE])) {
            $options[OssClient::OSS_CONTENT_TYPE] = Util::guessMimeType($path, '');
        }

        try {
            $bucket = $this->getBucket($path);
            $this->client->uploadFile($bucket, $object, $localFilePath, $options);
        } catch (OssException $e) {
            return false;
        }

        $type = 'file';
        $result = compact('type', 'path');
        $result['mimetype'] = $options[OssClient::OSS_CONTENT_TYPE];

        return $result;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        $object = $this->applyPathPrefix($path);
        $options = $this->getOptionsFromConfig($config);

        if (! isset($options[OssClient::OSS_LENGTH])) {
            $options[OssClient::OSS_LENGTH] = Util::contentSize($contents);
        }

        if (! isset($options[OssClient::OSS_CONTENT_TYPE])) {
            $options[OssClient::OSS_CONTENT_TYPE] = Util::guessMimeType($path, $contents);
        }

        try {
            $bucket = $this->getBucket($path);
            $this->client->putObject($bucket, $object, $contents, $options);
        } catch (OssException $e) {
            return false;
        }

        $type = 'file';
        $result = compact('type', 'path', 'contents');
        $result['mimetype'] = $options[OssClient::OSS_CONTENT_TYPE];
        $result['size'] = $options[OssClient::OSS_LENGTH];

        return $result;
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     * @return bool
     */
    public function rename($path, $newpath)
    {
        if (! $this->copy($path, $newpath)) {
            return false;
        }

        return $this->delete($path);
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     * @return bool
     */
    public function copy($path, $newpath)
    {
        $object = $this->applyPathPrefix($path);
        $newobject = $this->applyPathPrefix($newpath);

        try {
            $bucket = $this->getBucket($path);
            $this->client->copyObject($bucket, $object, $bucket, $newobject);
        } catch (OssException $e) {
            return false;
        }

        return true;
    }

    /**
     * Delete a file.
     *
     * @param string $path
     * @return bool
     */
    public function delete($path)
    {
        $object = $this->applyPathPrefix($path);

        try {
            $bucket = $this->getBucket($path);
            $this->client->deleteObject($bucket, $object);
        } catch (OssException $e) {
            return false;
        }

        return true;
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $list = $this->listContents($dirname, true);

        $objects = [];
        foreach ($list as $val) {
            if ($val['type'] === 'file') {
                $objects[] = $this->applyPathPrefix($val['path']);
            } else {
                $objects[] = $this->applyPathPrefix($val['path']).'/';
            }
        }

        try {
            $bucket = $this->getBucket($dirname);
            $this->client->deleteObjects($bucket, $objects);
        } catch (OssException $e) {
            return false;
        }

        return true;
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        $object = $this->applyPathPrefix($dirname);
        $options = $this->getOptionsFromConfig($config);

        try {
            $bucket = $this->getBucket($dirname);
            $this->client->createObjectDir($bucket, $object, $options);
        } catch (OssException $e) {
            return false;
        }

        return ['path' => $dirname, 'type' => 'dir'];
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function has($path)
    {
        $object = $this->applyPathPrefix($path);

        try {
            $bucket = $this->getBucket($path);
            $exists = $this->client->doesObjectExist($bucket, $object);
        } catch (OssException $e) {
            return false;
        }

        return $exists;
    }

    /**
     * Read a file.
     *
     * @param string $path
     * @return array|false
     */
    public function read($path)
    {
        $object = $this->applyPathPrefix($path);

        try {
            $bucket = $this->getBucket($path);
            $contents = $this->client->getObject($bucket, $object);
        } catch (OssException $e) {
            return false;
        }

        return compact('contents', 'path');
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $directory = $this->applyPathPrefix($directory);

        $bucket = $this->getBucket($directory);
        $delimiter = '/';
        $nextMarker = '';
        $maxkeys = 1000;
        $options = [
            'delimiter' => $delimiter,
            'prefix'    => $directory,
            'max-keys'  => $maxkeys,
            'marker'    => $nextMarker,
        ];

        $listObjectInfo = $this->client->listObjects($bucket, $options);

        $objectList = $listObjectInfo->getObjectList(); // 文件列表
        $prefixList = $listObjectInfo->getPrefixList(); // 目录列表

        $result = [];
        foreach ($objectList as $objectInfo) {
            if ($objectInfo->getSize() === 0 && $directory === $objectInfo->getKey()) {
                $result[] = [
                    'type'      => 'dir',
                    'path'      => $this->removePathPrefix(rtrim($objectInfo->getKey(), '/')),
                    'timestamp' => strtotime($objectInfo->getLastModified()),
                ];
                continue;
            }

            $result[] = [
                'type'      => 'file',
                'path'      => $this->removePathPrefix($objectInfo->getKey()),
                'timestamp' => strtotime($objectInfo->getLastModified()),
                'size'      => $objectInfo->getSize(),
            ];
        }

        foreach ($prefixList as $prefixInfo) {
            if ($recursive) {
                $next = $this->listContents($this->removePathPrefix($prefixInfo->getPrefix()), $recursive);
                $result = array_merge($result, $next);
            } else {
                $result[] = [
                    'type'      => 'dir',
                    'path'      => $this->removePathPrefix(rtrim($prefixInfo->getPrefix(), '/')),
                    'timestamp' => 0,
                ];
            }
        }

        return $result;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     * @return array|false
     * @throws \OSS\Core\OssException
     */
    public function getMetadata($path)
    {
        $object = $this->applyPathPrefix($path);

        try {
            $bucket = $this->getBucket($path);
            $result = $this->client->getObjectMeta($bucket, $object);
        } catch (OssException $e) {
            return false;
        }

        return [
            'type'      => 'file',
            'dirname'   => Util::dirname($path),
            'path'      => $path,
            'timestamp' => strtotime($result['last-modified']),
            'mimetype'  => $result['content-type'],
            'size'      => $result['content-length'],
        ];
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     * @return array|false
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     * @return array|false
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     * @return array|false
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the signed download url of a file.
     *
     * @param string $path
     * @param int    $expires
     * @param string $host_name
     * @param bool   $use_ssl
     * @return string
     */
    public function getSignedDownloadUrl($path, $expires = 3600, $host_name = '', $use_ssl = false)
    {
        $bucket = $this->getBucket($path);
        $object = $this->applyPathPrefix($path);
        $url = $this->client->signUrl($bucket, $object, $expires);

        if (! empty($host_name) || $use_ssl) {
            $parse_url = parse_url($url);
            if (! empty($host_name)) {
                $parse_url['host'] = $bucket.'.'.$host_name;
            }
            if ($use_ssl) {
                $parse_url['scheme'] = 'https';
            }

            $url = (isset($parse_url['scheme']) ? $parse_url['scheme'].'://' : '')
                   .(
                   isset($parse_url['user']) ?
                       $parse_url['user'].(isset($parse_url['pass']) ? ':'.$parse_url['pass'] : '').'@'
                       : ''
                   )
                   .(isset($parse_url['host']) ? $parse_url['host'] : '')
                   .(isset($parse_url['port']) ? ':'.$parse_url['port'] : '')
                   .(isset($parse_url['path']) ? $parse_url['path'] : '')
                   .(isset($parse_url['query']) ? '?'.$parse_url['query'] : '');
        }

        return $url;
    }

    /**
     * Get options from the config.
     *
     * @param Config $config
     * @return array
     */
    protected function getOptionsFromConfig(Config $config)
    {
        $options = $this->options;
        foreach (static::$mappingOptions as $option => $ossOption) {
            if (! $config->has($option)) {
                continue;
            }
            $options[$ossOption] = $config->get($option);
        }

        return $options;
    }

    /**
     * 文件直传读取签名
     */
    public function ctyDirectUploadSignature($dir,$expire)
    {
        $oss_config = config('filesystems.disks.oss');
        $id         = $oss_config['access_id'];
        $key        = $oss_config['access_key'];
        $endpoint   = $oss_config['endpoint'];

        $bucket_host = $this->getBucket($dir,true);
        $bucket = $bucket_host[0];
        $host   = $bucket_host[1];
        //$host = 'http://'.$bucket.'.'.$endpoint;

        $whole_url = \Request::getUri();
        $callbackUrl = substr($whole_url,0,strpos($whole_url,'/get_oss_signature/health_record'));
        $callbackUrl = $callbackUrl.'/get_oss_signature/callback';
        if(env('APP_ENV')=='local'||env('APP_ENV')=='dev'){
            $callbackUrl = $oss_config['oss_direct_upload_callback'];//方便本地调试
        }

        $callback_param = array('callbackUrl'=>$callbackUrl,
            "callbackHost"=> $oss_config['oss_direct_upload_callback_host'],//不带http://
            'callbackBody'=>'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
            'callbackBodyType'=>"application/x-www-form-urlencoded");
        $callback_string = json_encode($callback_param);

        $base64_callback_body = base64_encode($callback_string);
        //$base64_callback_body = '';//

        $now = time();
        $expire = $expire; //设置该policy超时时间是10s. 即这个policy过了这个有效时间，将不能访问
        $end = $now + $expire;
        $expiration = $this->gmt_iso8601($end);

        //最大文件大小.用户可以自己设置
        $condition = array(0=>'content-length-range', 1=>0, 2=>1048576000);
        $conditions[] = $condition;

        //表示用户上传的数据,必须是以$dir开始, 不然上传会失败,这一步不是必须项,只是为了安全起见,防止用户通过policy上传到别人的目录
        $start = array(0=>'starts-with', 1=>'$key', 2=>$dir);
        $conditions[] = $start;

        $arr = array('expiration'=>$expiration,'conditions'=>$conditions);
        //echo json_encode($arr);
        //return;
        $policy = json_encode($arr);
        $base64_policy = base64_encode($policy);
        $string_to_sign = $base64_policy;
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $key, true));

        $response = array();
        $response['accessid'] = $id;
        $response['host'] = $host;
        $response['policy'] = $base64_policy;
        $response['signature'] = $signature;
        $response['expire'] = $end;
        $response['callback'] = $base64_callback_body;
        //这个参数是设置用户上传指定的前缀
        $response['dir'] = $dir;
        return json_encode($response);
    }

    //获取标准
    private function gmt_iso8601($time) {
        $dtStr = date("c", $time);
        $mydatetime = new DateTime($dtStr);
        $expiration = $mydatetime->format(DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);
        return $expiration."Z";
    }
    /**
     * 传太医文件读取
     * ['private', 'public-read', 'public-read-write']
     */
    public function ctyGetFile($ossFilePath, $timeout=3600)
    {
        $object = $this->applyPathPrefix($ossFilePath);

        $bucket_host = $this->getBucket($ossFilePath,true);
        $bucket = $bucket_host[0];
        $host   = $bucket_host[1];

        $acl = $this->client->getBucketAcl($bucket);
        //public
        if(strpos($acl,'public') === 0){
            $read_url = $host.'/'.$object;
        }else{
            try {
                $read_url = $this->client->signUrl($bucket, $object, $timeout);
            } catch (OssException $e) {
                return json_encode(array(0,'文件读取失败',$e->getMessage()));
            }
        }

        return json_encode(array(0,'文件读取成功',$read_url));
    }
    /**
     * 传太医服务器端上传文件
     *
     */
    public function ctyPutFile($ossDir, $localDirPath,$request)
    {
        //是否为客户端上传
        if(is_string($request)){
            $file_name = $request;
        }else{
            $file_name = $this->prePutFile($request,$localDirPath);
            if(is_array($file_name)){
                return json_encode($file_name);
            }
        }
        $localFilePath = $localDirPath.'/'.$file_name;
        $ossFilePath = $ossDir.'/'.$file_name;
        //
        $object = $this->applyPathPrefix($ossFilePath);
        $bucket_host = $this->getBucket($ossFilePath,true);
        $bucket = $bucket_host[0];
        $host   = $bucket_host[1];
        $acl = $this->client->getBucketAcl($bucket);
        //public
        if(strpos($acl,'public') === 0){
            try {
                $this->client->uploadFile($bucket, $object, $localFilePath);
            } catch (OssException $e) {
                return json_encode(array(90000,'文件写入失败',$e->getMessage(),''));
            }
        }else{
            $timeout = 3600;
            try {
                $signedUrl = $this->client->signUrl($bucket, $object, $timeout, "PUT");
                $content = file_get_contents($localFilePath);
                $request = new RequestCore($signedUrl);
                $request->set_method('PUT');
                $request->add_header('Content-Type', '');
                $request->add_header('Content-Length', strlen($content));
                $request->set_body($content);
                $request->send_request();
                $res = new ResponseCore($request->get_response_header(),$request->get_response_body(), $request->get_response_code());
                if (!$res->isOK()) {
                    return json_encode(9000,'文件上传失败','');
                }
            } catch (OssException $e) {
                return json_encode(9000,'文件写入失败',$e->getMessage());
            }
        }

        //删除原文件
        @unlink($localFilePath);
        $data = $host.'/'.$object;

        return json_encode(array(0,'上传成功',$data));
    }
    /**
     * 传太医客户端form表单上传文件校验
     * 上传表单的name值为photo
     *
     * return 成功返回文件名,错误返回错误信息的数组
     */
    private function prePutFile(Request $request,$localDirPath)
    {
        if(!$request->hasFile('photo')){
            return array(90000,'文件photo不能为空','');
        }
        $file = $request->file('photo');

        $fileName = date('His').'_'.str_pad(mt_rand(0,99999999),8,rand(0,9),STR_PAD_LEFT).'_'.$file->getClientOriginalName();
        //本地上传
        if(empty($file) || !$file->isValid()){
            return array(90000,'文件错误或者为空','');
        }
        //目录是否具有读写权限
        if (!is_dir($localDirPath) || @opendir($localDirPath)=== false){
            return array(90000,'本地目录不存在或权限不足','');
        }
        //图片类型,大小校验
        $imageTypes = explode(';', config('filesystems.disks.oss.allowed_types'));
        $max_file_size = config('filesystems.disks.oss.max_file_size');
        $size = $file->getClientSize();
        $type = $file->getClientMimeType();

        if ($size > $max_file_size) {
            return array(90000,'文件大小不能超过:'.config('filesystems.disks.oss.max_file_size'),'');
        }

        if (!in_array($type, $imageTypes)) {
            return array(90000,'上传文件类型错误','');
        }
        $file->move($localDirPath, $fileName);

        return $fileName;
    }
}
