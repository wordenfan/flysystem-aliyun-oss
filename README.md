# Flysystem Adapter for Aliyun OSS.

This is a Flysystem adapter for the Aliyun OSS ~2.0.4

inspire by [aobozhang/aliyun-oss-adapter](https://github.com/aobozhang/aliyun-oss-adapter)

## Installation

```bash
composer require apollopy/flysystem-aliyun-oss
```

## for Laravel

This service provider must be registered.

```php
// config/app.php

'providers' => [
    '...',
    ApolloPY\Flysystem\AliyunOss\AliyunOssServiceProvider::class,
];
```

edit the config file: config/filesystems.php

add config

```php
'oss' => [
    'driver'     => 'oss',
    'access_id'  => env('OSS_ACCESS_ID','your id'),
    'access_key' => env('OSS_ACCESS_KEY','your key'),
    'bucket'     => env('OSS_BUCKET','your bucket'),
    'endpoint'   => env('OSS_ENDPOINT','your endpoint'),
    'prefix'     => env('OSS_PREFIX', ''), // optional
],
```

change default to oss

```php
    'default' => 'oss'
```

## Use

see [Laravel wiki](https://laravel.com/docs/5.1/filesystem)

## Plugins

inspire by [itbdw/laravel-storage-qiniu](https://github.com/itbdw/laravel-storage-qiniu)

```php
Storage::disk('oss')->putFile($path, '/local_file_path/1.png', ['mimetype' => 'image/png']);
Storage::disk('oss')->signedDownloadUrl($path, 3600, 'oss-cn-beijing.aliyuncs.com', true);
```

## IDE Helper

if installed [barryvdh/laravel-ide-helper](https://github.com/barryvdh/laravel-ide-helper)

edit the config file: config/ide-helper.php

```php
'interfaces'      => [
    '\Illuminate\Contracts\Filesystem\Filesystem' => ApolloPY\Flysystem\AliyunOss\FilesystemAdapter::class,
],
```

## Extend Plugins

客户端直传获取签名

```php
public function getOssDirectUploadSignature(){
    $disk = Storage::disk('oss');

    $fileName = 'test.png';
    $local_file_path = 'oss_transfer';//服务器本地路径
    $oss_path = 'health_record/dev_test';//OSS路径
    $put_res = $disk->ctyDirectUploadSignature($oss_path, $local_file_path,$fileName);
    var_dump($put_res);
    exit;
}
```
客户端直传回调函数

```php
public function getOssDirectUploadSignatureCallback(){
    header('HTTP/1.0 200 OK');
    header("Content-Type: application/json");
    $data = array("Status"=>"Ok");
    header("Content-Length:".strlen(json_encode($data)));
    echo json_encode($data);
}
```
系统函数调用

```php
public function systemHandler(){
    //测试文件系统自有方法
    $disk = Storage::disk('oss');
    $disk->ctySetBucket('cty-img-test');
    $res = $disk->lastModified('health_record/2016_05_04_13570876_test_02.jpg');
    var_dump(date('Y-m-d H:i:s',$res));
}
```
文件读取

```php
public function readFile()
{
    $disk = Storage::disk('oss');

    //private读
    $oss_file = 'health_record/dev_test/1.png';
    $get_res = $disk->ctyGetFile($oss_file);
    var_dump($get_res);
    exit;

    //public读
    $oss_file = 'avatar/3shkZ7-a.png';
    $get_res = $disk->ctyGetFile($oss_file);
    var_dump($get_res);
    exit;
}
```
文件写入

```php
public function writeFile(Request $request){
    $disk = Storage::disk('oss');

    //public写(服务器端)
    $fileName = 'test.png';
    $local_file_path = 'oss_transfer';//服务器本地路径
    $oss_path = 'avatar/test';//OSS路径
    $put_res = $disk->ctyPutFile($oss_path, $local_file_path,$fileName);
    var_dump($put_res);
    exit;

    //public写(服务器端)
    $fileName = 'test.png';
    $local_file_path = 'oss_transfer';//服务器本地路径
    $oss_path = 'health_record/dev_test';//OSS路径
    $put_res = $disk->ctyPutFile($oss_path, $local_file_path,$fileName);
    var_dump($put_res);
    exit;

    //Form表单public写
    $local_file_path = 'oss_transfer';//服务器本地路径
    $oss_path = 'health_record/test';//OSS路径
    $put_res = $disk->ctyPutFile($oss_path, $local_file_path,$request);
    var_dump($put_res);
    exit;

    //Form表单private写
    $local_file_path = 'oss_transfer';//服务器本地路径
    $oss_path = 'private_health_record/test';//OSS路径
    $put_res = $disk->ctyPutFile($oss_path, $local_file_path,$request);
    var_dump($put_res);
    exit;


}
```
