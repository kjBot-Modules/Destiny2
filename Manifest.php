<?php
namespace kjBotModule\kj415j45\Destiny2;

use Exception;
use kjBot\Framework\DataStorage;

class Manifest{
    private $apikey;
    private $aggregateManifest; // manifest aggregateJSON 对象
    private $manifest; //manifest aggregateJSON merge
    private $manifestJSON; //https://www.bungie.net/Platform/Destiny2/Manifest/ 的返回 JSON
    private $manifestJSONobj; //manifest 返回的 Response

    protected $baseHost = 'https://www.bungie.net';
    protected $host = 'https://www.bungie.net/Platform';
    protected $locale = 'zh-chs';

    public function __construct($apikey, $locale = 'zh-chs'){
        $this->apikey = $apikey;
        $this->locale = $locale;
    }

    /**
     * 给定ID返回该抽象物品的数据
     *
     * @param int/string $id
     * @return ?array [分类名 => 数据]
     */
    public function findID($id): ?array{
        $result = [];
        foreach($this->manifest as $componentName => $component){
            if(array_key_exists($id, $component)){
                $result[$componentName] = $component[$id];
            }
        }
        if($result !== [])return $result;
        return null;
    }

    /**
     * 检查清单是否是最新版本
     *
     * @return boolean
     */
    public function needUpdate(): bool{
        $lastManifest = DataStorage::GetData('Destiny2.Manifest/LastManifest.json');
        if(false === $lastManifest)return true; //没有上次获取的清单的情况下需要获取新的数据库
        if(false === DataStorage::GetData('Destiny2.Manifest/Manifest.'.$this->locale.'.json'))return true; //没有数据库的情况下需要获取新的数据库

        $lastManifestObj = json_decode($lastManifest)->Response;

        return version_compare($lastManifestObj->version, $this->manifestJSONobj->version, '!=');
    }

    /**
     * 取得最新清单
     * 同时更新本实例内的 $manifestJSON 与 $manifestJSONobj
     *
     * @return string $manifestJSON
     */
    protected function fetchLatestManifest(){
        $manifestJSON = file_get_contents(
            $this->host.'/Destiny2/Manifest/', false, stream_context_create([
                'http' => [
                    'header' => 'X-API-Key: '.$this->apikey,
                ]
            ])
        );
        if(false === $manifestJSON){
            throw new Exception('Can not fetch latest manifest, network error');
        }
        $this->manifestJSON = $manifestJSON;
        $this->manifestJSONobj = json_decode($manifestJSON)->Response;
        return $manifestJSON;
    }

    /**
     * 取得数据库
     * 同时更新本实例内的 $aggregateManifest 与 $manifest
     * 
     * @var bool $cache = false 是否使用缓存
     *
     * @return string
     */
    protected function fetchAggregateManifest($cache = false){
        if($cache){
            $aggregateManifest = DataStorage::GetData('Destiny2.Manifest/Manifest.'.$this->locale.'.json');
        }else{
            $aggregateManifest = file_get_contents(
                $this->baseHost.((array)$this->manifestJSONobj->jsonWorldContentPaths)[$this->locale]
            );
            if(false === $aggregateManifest){
                throw new Exception('Can not fetch aggregate manifest');
            }
        }
        $this->aggregateManifest = $aggregateManifest;
        $aggregateManifestArray = json_decode($aggregateManifest, true, 512, JSON_BIGINT_AS_STRING); //数字ID只能转成关联数组了

        $this->manifest = $aggregateManifestArray;

        return $aggregateManifest;
    }


    /**
     * 必须最先调用
     *
     * @return $this
     */
    public function getLatestManifest(){
        $this->fetchLatestManifest(); //获取最新清单
        if(!$this->needUpdate()){
            //如果不需要更新，从缓存中获取数据库
            $this->fetchAggregateManifest(true);
        }else{
            //如果需要更新，从棒鸡取得最新数据库并保存
            $this->fetchAggregateManifest();
            DataStorage::SetData('Destiny2.Manifest/Manifest.'.$this->locale.'.json', $this->aggregateManifest);
        }
        DataStorage::SetData('Destiny2.Manifest/LastManifest.json', $this->manifestJSON);
        return $this;
    }

    public function getManifest(){
        return $this->manifest;
    }

    public function getAggregateManifest(): string{
        return $this->aggregateManifest;
    }

    public function getManifestJSON(): string{
        return $this->manifestJSON;
    }

    public function getManifestJSONobj(): object{
        return $this->manifestJSONobj;
    }

    public function getVersion(): string{
        return $this->manifestJSONobj->version;
    }
}