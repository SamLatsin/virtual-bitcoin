<?php
namespace App;

class Transaction{
   public $app;
   public $model;

  function __construct($app,$model=null) {
      $this->app = $app;
      $this->model = 'MyApp\Models\Transactions';
  }

  function getTransactions() {
    $phql = "SELECT * FROM ".$this->model." ORDER BY id DESC";
    return $this->app->modelsManager->executeQuery($phql)->toArray();
  }

  function getTransactionById($id){
    $phql = "SELECT * FROM ".$this->model." WHERE id=:id:";
    return $this->app->modelsManager->executeQuery($phql,['id'=>$id])->toArray();
  }

  function getTransactionByTxid($txid) {
    $phql = "SELECT * FROM ".$this->model." WHERE txid=:txid:";
    return $this->app->modelsManager->executeQuery($phql,['txid'=>$txid])->toArray();
  }

  function getTransactionsByName($name) {
    $phql = "SELECT * FROM ".$this->model." WHERE fromWallet=:name: OR toWallet=:name:";
    return $this->app->modelsManager->executeQuery($phql,['name'=>$name])->toArray();
  }

  function getTransactionsToSyncByName($name) {
    $phql = "SELECT * FROM ".$this->model." WHERE (fromWallet=:name: OR toWallet=:name:) and (fromChecks<2 OR toChecks<2)";
    return $this->app->modelsManager->executeQuery($phql,['name'=>$name])->toArray();
  }

  function deleteTransaction($id){
    $phql  = "DELETE FROM ".$this->model." WHERE id = :id:";
    $res = $this->app->modelsManager->executeQuery($phql,['id'=>$id]);
    return $res;
  }

  function deleteTransactionByName($name){
    $phql  = "DELETE FROM ".$this->model." WHERE name = :name:";
    $res = $this->app->modelsManager->executeQuery($phql,['name'=>$name]);
    return $res;
  }

  function insertTransaction($fields){
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

  function updateTransaction($fields,$id=0,$upd=false){
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