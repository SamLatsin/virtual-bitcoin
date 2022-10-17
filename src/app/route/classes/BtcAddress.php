<?php
namespace App;

class BtcAddress{
   public $app;
   public $model;

  function __construct($app,$model=null) {
      $this->app = $app;
      $this->model = 'MyApp\Models\BtcAddresses';
  }

  function getBtcAddresses() {
    $phql = "SELECT * FROM ".$this->model." ORDER BY id DESC";
    return $this->app->modelsManager->executeQuery($phql)->toArray();
  }

  function getBtcAddressById($id){
    $phql = "SELECT * FROM ".$this->model." WHERE id=:id:";
    return $this->app->modelsManager->executeQuery($phql,['id'=>$id])->toArray();
  }

  function getBtcAddressesByNameAndAddress($name, $address) {
    $phql = "SELECT * FROM ".$this->model." WHERE name=:name: AND address=:address:";
    return $this->app->modelsManager->executeQuery($phql,['name'=>$name, 'address'=>$address])->toArray();
  }

  function getBtcAddressesByAddress($address) {
    $phql = "SELECT * FROM ".$this->model." WHERE address=:address:";
    return $this->app->modelsManager->executeQuery($phql,['address'=>$address])->toArray();
  }

  function getBtcAddressByName($name) {
    $phql = "SELECT * FROM ".$this->model." WHERE name=:name:";
    return $this->app->modelsManager->executeQuery($phql,['name'=>$name])->toArray();
  }

  function getFileImportedBtcAddressesByTicker($ticker) {
    $phql = "SELECT * FROM ".$this->model." WHERE ticker=:ticker: AND name like \"frw%\"";
    return $this->app->modelsManager->executeQuery($phql,['ticker'=>$ticker])->toArray();
  }

  function deleteBtcAddress($id){
    $phql  = "DELETE FROM ".$this->model." WHERE id = :id:";
    $res = $this->app->modelsManager->executeQuery($phql,['id'=>$id]);
    return $res;
  }

  function deleteBtcAddressByName($name){
    $phql  = "DELETE FROM ".$this->model." WHERE name = :name:";
    $res = $this->app->modelsManager->executeQuery($phql,['name'=>$name]);
    return $res;
  }

  function insertBtcAddress($fields){
    $phql = 'INSERT INTO '.$this->model;
    foreach ($fields as $key => $field) {
      $keys[] = $key;
      $values[] = ':'.$key.':';
    }
    $keyRes = implode(',',$keys);
    $valRes =  implode(',',$values);
    $phql = $phql.' ('.$keyRes.') VALUES ('.$valRes.')';
    $res = $this->app->modelsManager->executeQuery($phql,$fields);
    return  $res->getModel()->id;
  }

  function updateBtcAddress($fields,$id=0,$upd=false){
    $phql = 'UPDATE '.$this->model.' SET ';
    foreach ($fields as $key => $field) {
      if($upd!=$key){
        $values[] = $key.'=:'.$key.':';
      }
    }
    $valRes =  implode(', ',$values);
    if(!$upd){
      $phql = $phql.$valRes.' WHERE id='.$id;
    }else{
      $phql = $phql.$valRes.' WHERE '.$upd.'=:'.$upd.':';
    }
    $res = $this->app->modelsManager->executeQuery($phql,$fields);
    return $res;
  }
}