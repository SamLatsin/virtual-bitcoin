<?php
define('API_TICKER_TOKEN', 
	[
		"btc"=>"PUT_YOUR_RANDOMLY_GENERATED_TOKEN_HERE",
	]
); 

define('CRON', "/cron/");

define('USER', "btcuser");
define('PASS',"btcpass");

/* Functions */
require_once 'middlewares.php';
/* Classes */
require_once 'classes/App.php';
require_once 'classes/Wallet.php';
require_once 'classes/RecoverQueue.php';
require_once 'classes/BtcAddress.php';
require_once 'classes/Transaction.php';
/* APP */
require_once 'classes_init.php';
require_once 'main.php';
require_once 'recover.php';
/* Tickers */
require_once 'tickers/btc.php';