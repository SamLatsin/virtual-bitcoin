<?php

function createWalletBTC($app) {
	$mnemonic = shell_exec('bx seed -b 128 | bx mnemonic-new -l en');
	// $private_key = shell_exec('echo "'.$mnemonic.'" | bx mnemonic-to-seed | bx hd-new | bx hd-to-ec | bx ec-to-wif -v 239'); //-v 239 for testnet
	$private_key = shell_exec('echo "'.$mnemonic.'" | bx mnemonic-to-seed | bx hd-new | bx hd-to-ec | bx ec-to-wif');
	$wallets = $app["Wallet"]->getWalletsByTicker("BTC");
	if ($wallets) {
		$name = "w".(getNumbers(end($wallets)['name'])[0][0] + 1);
	}
	else {
		$name = "w1";
	}
	$debug  = sendRPC("createwallet", [$name, false, true, null, false, false],                    "localhost:8332/");
	$debug2 = sendRPC("sethdseed",    [true, trim($private_key)], "localhost:8332/wallet/".$name);
	$debug3 = sendRPC("unloadwallet", [$name],                    "localhost:8332/");
	$random = new \Phalcon\Security\Random();
	$walletToken = $random->uuid();
	$fields = [
		"ticker"=>"btc",
		"name"=>$name,
		"privateKey"=>$private_key,
		"mnemonic"=>$mnemonic,
		"walletToken"=>$walletToken,
	];
	$app['Wallet']->insertWallet($fields);
    $result = [
        "status"=>"done",
        "name"=>trim($name),
        "mnemonic"=>trim($mnemonic),
        "privateKey"=>trim($private_key),
        "walletToken"=>$walletToken,
        // "debug"=>$debug,
        // "debug2"=>$debug2,
        // "debug3"=>$debug3,
    ];

    header('Content-Type: application/json; charset=utf-8');
    return json_encode($result);
}

function getBalanceBTC($app) {
	header('Content-Type: application/json; charset=utf-8');
	$name = $app['request']->get('name',null,null,true);
	$wallet = $app['Wallet']->getWalletByTickerAndName("BTC", $name);
	if ($wallet) {
		$wallet = $wallet[0];

		$token = $app['request']->get('walletToken',null,null,true);
		checkWalletToken($wallet, $token, $app);
		checkIsRecoveringBTC($app, $wallet['name']);

		$debug1 = sendRPC("loadwallet", [$wallet['name']], "localhost:8332/");
		$balance = sendRPC("getwalletinfo", [], "localhost:8332/wallet/".$name);
		// $debug2 = sendRPC("unloadwallet", [$wallet['name']], "localhost:8332/"); //test
		$balance = json_decode($balance, true);
		$conf_balance = $balance['result']['balance'];
		$unconf_balance = $balance['result']['unconfirmed_balance'];
		$immature_balance = $balance['result']['immature_balance'];
		$result = [
        	"status"=>"done",
	        "name"=>trim($wallet['name']),
	        "balance"=>$conf_balance,
	        "unconfirmed_balance"=>$unconf_balance,
	        "immature_balance"=>$immature_balance,
	        // 'debug1'=>$debug1,
	        // 'debug2'=>$debug2,
	    ];
	    return json_encode($result);
	}
	return pageNotFound($app);
}

function getAddressBalanceBTC($app) {
	header('Content-Type: application/json; charset=utf-8');
	$name = $app['request']->get('name',null,null,true);
	$wallet = $app['Wallet']->getWalletByTickerAndName("BTC", $name);
	$address = $app['request']->get('address',null,null,true);
	if ($wallet) {
		$wallet = $wallet[0];
		$token = $app['request']->get('walletToken',null,null,true);
		checkWalletToken($wallet, $token, $app);
		checkIsRecoveringBTC($app, $wallet['name']);
		updateBalance($name, $app);
		$balance = $app["BtcAddress"]->getBtcAddressesByNameAndAddress($name, $address);
		if ($balance) {
			$result = [
				"status"=>"done",
		        "result"=>[
		        	"balance"=>$balance[0]["balance"],
		        	"unconfirmed"=>$balance[0]["unconfirmed"],
		        ],
			];
			return json_encode($result);
		}
		$result = [
			"status"=>"error",
	        "error"=>"No address on this wallet",
		];
		return json_encode($result);
	}
	return pageNotFound($app);
}

function getConfirmations($txid) {
	if (!$txid) {
		return 1;
	}
	$debug1 = sendRPC("getrawtransaction", [$txid, 1], "localhost:8332/");
	$raw_transaction = json_decode($debug1, true);
	if (isset($raw_transaction["result"]["confirmations"])) {
		$confirmations = $raw_transaction["result"]["confirmations"];
	}
	else {
		$confirmations = 0;
	}
	return $confirmations;
}

function updateBalance($name, $app) {
	$transactions = $app["Transaction"]->getTransactionsToSyncByName($name);
	$addresses = $app["BtcAddress"]->getBtcAddressByName($name);
	foreach ($transactions as $key => $transaction) {
		$fee = $transaction["fee"];
		$key1 = array_search($transaction["fromAddress"], array_column($addresses, 'address'));
		if ($key1 !== false) {
			if ($name == $transaction["fromWallet"]) {
				if ($transaction["fromWallet"] == $transaction["toWallet"]) {
					$transactions[$key]["toChecks"] = 2;
					$transactions[$key]["fromChecks"] = 2;
					$addresses[$key1]["balance"] -= round($transaction["amount"], 8) + round($fee, 8);
					$addresses[$key1]["balance"] = round($addresses[$key1]["balance"], 8);
					$key1 = array_search($transaction["toAddress"], array_column($addresses, 'address'));
					$addresses[$key1]["balance"] += round($transaction["amount"], 8);
					$addresses[$key1]["balance"] = round($addresses[$key1]["balance"], 8);
				}
				else {
					if ($transaction["fromChecks"] == 1 and getConfirmations($transaction["txid"]) > 0) {
						$transactions[$key]["fromChecks"] = 2;
					}
					if ($transaction["fromChecks"] == 0) {
						$addresses[$key1]["balance"] -= round($transaction["amount"], 8) + round($fee, 8); 
						$transactions[$key]["fromChecks"] = 1;
					}
					$addresses[$key1]["balance"] = round($addresses[$key1]["balance"], 8);
				}
			}
		}
		else {
			$key1 = array_search($transaction["toAddress"], array_column($addresses, 'address'));
			if ($key1 !== false) {
				if ($name == $transaction["toWallet"]) {
					if ($transaction["toChecks"] == 1) {
						if (getConfirmations($transaction["txid"]) > 0) {
							$addresses[$key1]["balance"] += round($transaction["amount"], 8); 
							$addresses[$key1]["balance"] = round($addresses[$key1]["balance"], 8);
							$addresses[$key1]["unconfirmed"] -= round($transaction["amount"], 8);
							$addresses[$key1]["unconfirmed"] = round($addresses[$key1]["unconfirmed"], 8);
							$transactions[$key]["toChecks"] = 2;
						}
					}
					if ($transaction["toChecks"] == 0) {
						if (getConfirmations($transaction["txid"]) > 0) {
							$addresses[$key1]["balance"] += round($transaction["amount"], 8); 
							$addresses[$key1]["balance"] = round($addresses[$key1]["balance"], 8);
							$transactions[$key]["toChecks"] = 2;
						}
						else {
							$addresses[$key1]["unconfirmed"] += round($transaction["amount"], 8);
							$addresses[$key1]["unconfirmed"] = round($addresses[$key1]["unconfirmed"], 8);
							$transactions[$key]["toChecks"] = 1;
						}
					}
				}
			}
		}
		if (!$transaction["fromWallet"]) {
			$transactions[$key]["fromChecks"] = $transactions[$key]["toChecks"];
		}
		if (!$transaction["toWallet"]) {
			$transactions[$key]["toChecks"] = $transactions[$key]["fromChecks"];
		}
		$app["Transaction"]->updateTransaction($transactions[$key], $transactions[$key]["id"]);
	}
	foreach ($addresses as $key => $address) {
		$app["BtcAddress"]->updateBtcAddress($address, $address["id"]);
	}
	return;
}

function walletNotify($txid, $name, $app) {
	header('Content-Type: application/json; charset=utf-8');
	$transaction_row = $app["Transaction"]->getTransactionByTxid($txid);
	$upd = false;
	if ($transaction_row) {
		$upd = true;
	}
	$debug1 = sendRPC("getrawtransaction", [$txid], "localhost:8332/");
	$raw_transaction = json_decode($debug1, true);
	if (!$raw_transaction["result"]) {
		$result = [
	    	"status"=>"error",
	        "error"=>$raw_transaction["error"]["message"],
	    ];
		return json_encode($result);
	}
	$raw_transaction = $raw_transaction["result"];
	$debug2 = sendRPC("decoderawtransaction", [$raw_transaction], "localhost:8332/");
	$transaction = json_decode($debug2, true);
	$minus = 0;
	foreach ($transaction["result"]["vout"] as $key => $vout) {
		$minus += round($vout["value"], 8);
		$address = $vout["scriptPubKey"]["address"];
		$address = $app["BtcAddress"]->getBtcAddressesByAddress($address);
		if ($address) {
			$value = round($vout["value"], 8);
			$to_address = $address;
		}
	}
	$plus = 0;
	$from_address = null;
	$from_address_raw = null;
	foreach ($transaction["result"]["vin"] as $key => $vin) {
		$vout_id = $vin["vout"];
		$debug1 = sendRPC("getrawtransaction", [$vin["txid"]], "localhost:8332/");
		$raw_transaction = json_decode($debug1, true);
		$raw_transaction = $raw_transaction["result"];
		$debug2 = sendRPC("decoderawtransaction", [$raw_transaction], "localhost:8332/");
		$transaction = json_decode($debug2, true);
		foreach ($transaction["result"]["vout"] as $key => $vout) {
			if ($vout_id == $vout['n']) {
				$from_address_raw = $vout["scriptPubKey"]["address"];
				$address = $app["BtcAddress"]->getBtcAddressesByAddress($vout["scriptPubKey"]["address"]);
				if ($address) {
					$from_address = $address;
				}
				$plus += round($vout["value"], 8);
			}
		}
	}
	$fee = round(round($plus, 8) - round($minus, 8), 8);
	if ($upd) {
		$fields = [
			"fee"=>$fee,
			"checks"=>$transaction_row[0]["checks"] + 1,
		];
		$app["Transaction"]->updateTransaction($fields, $transaction_row[0]["id"]);
	}
	else {
		$to_wallet = $app["BtcAddress"]->getBtcAddressesByAddress($to_address[0]["address"]);
		$name = null; // unknown wallet
		if ($to_wallet) {
			$name = $to_wallet[0]["name"];
		}
		$from_wallet = null;
		if ($from_address) {
			$from_wallet = $app["BtcAddress"]->getBtcAddressesByAddress($from_address[0]["address"]);
		}
		$from_wallet_name = null; // unknown wallet
		if ($from_wallet) {
			$from_wallet_name = $from_wallet[0]["name"]; 
		}
		$fields = [
			"toWallet"=>$name,
			"fromWallet"=>$from_wallet_name,
			"fromAddress"=>$from_address_raw,
			"toAddress"=>$to_address[0]["address"],
			"amount"=>$value,
			"txid"=>$txid,
			"fee"=>$fee,
			"checks"=>1,
		];
		if ($value) {
			$app["Transaction"]->insertTransaction($fields);
		}
	}
	$result = [
    	"status"=>"done",
        "fields"=>$fields,
        "plus"=>$plus,
        "minus"=>$minus,
    ];
	return json_encode($fields);
}

function getStatusBTC($app) {
	header('Content-Type: application/json; charset=utf-8');
	$result = sendRPC("getblockchaininfo", [], "localhost:8332/");
	$result = json_decode($result, true);
	$result = $result['result'];
	$result = [
    	"status"=>"done",
        "result"=>$result,
    ];
	return json_encode($result);
}

function createNewAddressBTC($app) {
	header('Content-Type: application/json; charset=utf-8');
	$name = $app['request']->get('name',null,null,true);
	$wallet = $app['Wallet']->getWalletByTickerAndName("BTC", $name);
	if ($wallet) {
		$wallet = $wallet[0];

		$token = $app['request']->get('walletToken',null,null,true);
		checkWalletToken($wallet, $token, $app);
		checkIsRecoveringBTC($app, $wallet['name']);

		$debug1 = sendRPC("loadwallet", [$wallet['name']], "localhost:8332/");
		$address = sendRPC("getnewaddress", [], "localhost:8332/wallet/".$name);
		// $debug2 = sendRPC("unloadwallet", [$wallet['name']], "localhost:8332/"); //test
		$address = json_decode($address, true);
		$address = $address['result'];
		$fields = [
			"name"=>trim($wallet['name']),
			"address"=>$address,
			"balance"=>0,
		];
		$app["BtcAddress"]->insertBtcAddress($fields);
		$result = [
        	"status"=>"done",
	        "name"=>trim($wallet['name']),
	        "address"=>$address,
	        // 'debug1'=>$debug1,
	        // 'debug2'=>$debug2,
	    ];
	    return json_encode($result);
	}
	return pageNotFound($app);
}

function getFeeBTC($app) {
	header('Content-Type: application/json; charset=utf-8');
	$amount = $app['request']->get('amount',null,null,true);
	$from_address = $app['request']->get('from_address',null,null,true);
	$to_address = $app['request']->get('to_address',null,null,true);
	$name = $app['request']->get('name',null,null,true);
	$fee = $app['request']->get('fee',null,null,true);
	$wallet = $app['Wallet']->getWalletByTickerAndName("BTC", $name);
	if ($wallet) {
		$wallet = $wallet[0];
		$token = $app['request']->get('walletToken',null,null,true);
		checkWalletToken($wallet, $token, $app);
		checkIsRecoveringBTC($app, $wallet['name']);
		$from_virtual = $app["BtcAddress"]->getBtcAddressesByNameAndAddress($name, $from_address);
		if (!$from_virtual) {
			$result = [
		    	"status"=>"error",
		        "result"=>"Non-existent address on this wallet",
		    ];
		    return json_encode($result);
		}
		$to_virtual = $app["BtcAddress"]->getBtcAddressesByNameAndAddress($name, $to_address);
		if ($from_virtual and $to_virtual) {
			if ($from_virtual[0]["name"] == $to_virtual[0]["name"]) {
				if ($from_virtual[0]["balance"] > $amount) {
					$result = [
				    	"status"=>"done",
				        "result"=>0,
				    ];
				    return json_encode($result);
				}
				$result = [
			    	"status"=>"error",
			        "result"=>"Insufficient funds",
			    ];
			    return json_encode($result);
			}
		}
		$load = sendRPC("loadwallet", [$wallet['name']], "localhost:8332/");
		$amount = round($amount,8);
		$args = [
			[],
			[
				[
					$to_address=>$amount,
				]
			]
		];
		$hex1 = sendRPC("createrawtransaction", $args, "localhost:8332/");
		$hex1 = json_decode($hex1, true);
		if ($fee) {
			$fee = $fee / 100000; // convert from BTC/kB to satoshis/byte
			$result = sendRPC("fundrawtransaction", [trim($hex1['result']), ["feeRate"=>$fee]], "localhost:8332/wallet/".$name);
		}
		else {
			$result = sendRPC("fundrawtransaction", [trim($hex1['result'])], "localhost:8332/wallet/".$name);
		}
		$result = json_decode($result, true);
		// $unload = sendRPC("unloadwallet", [$wallet['name']], "localhost:8332/"); //test
		if ($result['error']) {
			$result = [
		    	"status"=>"error",
		        "result"=>$result['error']['message'],
		    ];
		    header("HTTP/1.0 400 Bad Request");
		    return json_encode($result);
		}
		$result = [
	    	"status"=>"done",
	        "result"=>$result['result']['fee'],
	        // "debug"=>$hex1,
	        // "debug2"=>$result,
	    ];
	    return json_encode($result);
	}
	return pageNotFound($app);
}

function sendBTC($app) {
	header('Content-Type: application/json; charset=utf-8');
	$name = $app['request']->get('name',null,null,true);
	$amount = $app['request']->get('amount',null,null,true);
	$from_address = $app['request']->get('from_address',null,null,true);
	$to_address = $app['request']->get('to_address',null,null,true);
	$fee = $app['request']->get('fee',null,null,true);
	$wallet = $app['Wallet']->getWalletByTickerAndName("BTC", $name);
	if ($wallet) {
		$wallet = $wallet[0];
		$token = $app['request']->get('walletToken',null,null,true);
		checkWalletToken($wallet, $token, $app);
		checkIsRecoveringBTC($app, $wallet['name']);
		$from_virtual = $app["BtcAddress"]->getBtcAddressesByNameAndAddress($name, $from_address);
		if (!$from_virtual) {
			$result = [
		    	"status"=>"error",
		        "result"=>"Non-existent address on this wallet",
		    ];
		    return json_encode($result);
		}
		$to_virtual = $app["BtcAddress"]->getBtcAddressesByNameAndAddress($name, $to_address);
		if ($from_virtual and $to_virtual) {			
			if ($from_virtual[0]["name"] == $to_virtual[0]["name"]) {
				if ($from_virtual[0]["balance"] >= $amount) {
					$fields = [
						"fromWallet"=>$name,
						"toWallet"=>$name,
						"fromAddress"=>$from_virtual[0]["address"],
						"toAddress"=>$to_virtual[0]["address"],
						"amount"=>$amount,
						"fee"=>0,
						"checks"=>2,
					];
					$app["Transaction"]->insertTransaction($fields);
					updateBalance($name, $app);
					$result = [
				    	"status"=>"done",
				        "result"=>null,
				    ];
				    return json_encode($result);
				}
				$result = [
			    	"status"=>"error",
			        "result"=>"Insufficient funds",
			    ];
			    return json_encode($result);
			}
		}
		if ($fee) {
			$args = [
				"address"=>$to_address,
				"amount"=>$amount,
				// "conf_target"=>3,
				// "subtractfeefromamount"=>$fee,
				"fee_rate"=>$fee,
			];
		}
		else {
			$args = [
				"address"=>$to_address,
				"amount"=>$amount,
				"conf_target"=>3,
			];
		}

		if ($from_virtual[0]["balance"] < $amount) {
			$result = [
		    	"status"=>"error",
		        "result"=>"Insufficient funds",
		    ];
		    return json_encode($result);
		}
		
		$load = sendRPC("loadwallet", [$wallet['name']], "localhost:8332/");
		$result = sendRPC("sendtoaddress", $args, "localhost:8332/wallet/".$name);
		// $unload = sendRPC("unloadwallet", [$wallet['name']], "localhost:8332/"); //test
		$result = json_decode($result, true);
		if ($result['error']) {
			$result = [
		    	"status"=>"error",
		        "result"=>$result['error']['message'],
		    ];
		    header("HTTP/1.0 400 Bad Request");
		    return json_encode($result);
		}
		$to_wallet = $app["BtcAddress"]->getBtcAddressesByAddress($to_address);
		if ($to_wallet) {
			$to_wallet = $to_wallet[0]["name"];
		}
		else {
			$to_wallet = null;
		}
		$fields = [
			"fromWallet"=>$name,
			"toWallet"=>$to_wallet,
			"fromAddress"=>$from_virtual[0]["address"],
			"toAddress"=>$to_address,
			"amount"=>$amount,
			"txid"=>$result['result'],
		];
		$app["Transaction"]->insertTransaction($fields);
		$result = [
        	"status"=>"done",
	        "txid"=>$result['result'],
	    ];
	    return json_encode($result);
	}
	return pageNotFound($app);
}

function getWalletListBTC($app) {
	header('Content-Type: application/json; charset=utf-8');
	$name = $app['request']->get('name',null,null,true);
	$wallet = $app['Wallet']->getWalletByTickerAndName("BTC", $name);
	if ($wallet) {
		$wallet = $wallet[0];
		$token = $app['request']->get('walletToken',null,null,true);
		checkWalletToken($wallet, $token, $app);
		checkIsRecoveringBTC($app, $wallet['name']);
		$addresses_raw = $app["BtcAddress"]->getBtcAddressByName($name);
		$addresses = [];
		foreach ($addresses_raw as $key => $address) {
			array_push($addresses, $address["address"]);
		}
		$result = [
        	"status"=>"done",
	        "result"=>$addresses,
	    ];
	    return json_encode($result);
	}
	return pageNotFound($app);
}

function getHistoryBTC($app) {
	header('Content-Type: application/json; charset=utf-8');
	$name = $app['request']->get('name',null,null,true);
	$wallet = $app['Wallet']->getWalletByTickerAndName("BTC", $name);
	if ($wallet) {
		$wallet = $wallet[0];
		$token = $app['request']->get('walletToken',null,null,true);
		checkWalletToken($wallet, $token, $app);
		checkIsRecoveringBTC($app, $wallet['name']);
		$transactions = $app["Transaction"]->getTransactionsByName($name);
		$result = [
        	"status"=>"done",
	        "result"=>$transactions,
	    ];
	    return json_encode($result);
	}
	return pageNotFound($app);
}

function getTransactionBTC($app) {
	header('Content-Type: application/json; charset=utf-8');
	$name = $app['request']->get('name',null,null,true);
	$txid = $app['request']->get('txid',null,null,true);
	$transaction = shell_exec("curl -s https://blockchain.info/tx/".$txid."\?format\=json");
	$transaction = json_decode($transaction, true);
	if (isset($transaction['error'])) {
		$result = [
	    	"status"=>"error",
		    "result"=>$transaction['message'],
		];
    	return json_encode($result);
	}
	$result = [
    	"status"=>"done",
	    "result"=>$transaction,
	];
    return json_encode($result);
}

function getConfirmationsBTC($app) {
	header('Content-Type: application/json; charset=utf-8');
	$txid = $app['request']->get('txid',null,null,true);
	$result = [
    	"status"=>"done",
	    "result"=>getConfirmations($txid),
	];
    return json_encode($result);
}

function recoverWalletBTC($app, $mask = "r", $start_height = "337122", $key = null) {
	$wallets = $app["Wallet"]->getWalletsByTicker("BTC");
	if ($key) {
		if (count(explode(" ", $key)) > 3) {
			$mnemonic = $key;
			$private_key = shell_exec('echo "'.$mnemonic.'" | bx mnemonic-to-seed | bx hd-new | bx hd-to-ec | bx ec-to-wif');
		}
		else {
			$mnemonic = null;
			$private_key = $key;
		}
		$dup = $app["Wallet"]->getWalletByTickerAndKey("BTC", $private_key);
		if ($dup) {
			if (count($dup) == 1) {
				if ($dup[0]['name'][0] == "f") {
					$dup = [
						"name"=>$dup[0]['name'],
						"privateKey"=>$dup[0]['privateKey'],
					];
					return json_encode($dup);
				}
			}
			else {
				foreach ($dup as $key => $dp) {
					if ($dp['name'][0] == "f") {
						$dp = [
							"name"=>$dp['name'],
							"privateKey"=>$dp['privateKey'],
						];
						return json_encode($dp);
						
					}
				}
			}
		}
	}
	else {
		$mnemonic = $app['request']->get('mnemonic',null,null,true);
		$private_key = $app['request']->get('privateKey',null,null,true);
	}
	if ($wallets) {
		$name = $mask."w".(getNumbers(end($wallets)['name'])[0][0] + 1);
	}
	else {
		$name = $mask."w1";
	}
	if (!$private_key) {
		$private_key = shell_exec('echo "'.$mnemonic.'" | bx mnemonic-to-seed | bx hd-new | bx hd-to-ec | bx ec-to-wif');
		// $private_key = shell_exec('echo "'.$mnemonic.'" | bx mnemonic-to-seed | bx hd-new | bx hd-to-ec | bx ec-to-wif -v 239'); //-v 239 for testnet
	}
	if ($mask == "fr") {
		$debug  = sendRPC("createwallet",     [$name, null, null, null, null, fales, true],                    "localhost:8332/");
	}
	else {
		$debug  = sendRPC("createwallet",     [$name, null, null, null, null, fales],                    "localhost:8332/");
	}
	$debug2 = sendRPC("sethdseed",        [true, trim($private_key)], "localhost:8332/wallet/".$name);
	$debug2 = json_decode($debug2, true);
	if (isset($debug2['error'])) {
		$result = [
	    	"status"=>"error",
		    "result"=>$debug2['error']['message'],
		    "privateKey"=>$private_key,
		];
    	return json_encode($result);
	}
	$random = new \Phalcon\Security\Random();
	$walletToken = $random->uuid();
	$fields = [
		"ticker"=>"btc",
		"name"=>$name,
		"privateKey"=>$private_key,
		"mnemonic"=>$mnemonic,
		"walletToken"=>$walletToken,
	];
	$res = $app['Wallet']->insertWallet($fields);
	$fields = [
		"ticker"=>"btc",
		"walletName"=>$name,
		"startHeight"=>$start_height,
	];
	$app['RecoverQueue']->insertItem($fields);
    // $debug4 = sendRPC("rescanblockchain", [], "localhost:8332/wallet/".$name, true);
    $result = [
        "status"=>"done",
        "name"=>trim($name),
        "mnemonic"=>trim($mnemonic),
        "privateKey"=>trim($private_key),
        "walletToken"=>$walletToken,
        // "debug"=>$debug,
        // "debug2"=>$debug2,
        // "debug4"=>$debug4,
    ];
    header('Content-Type: application/json; charset=utf-8');
    return json_encode($result);
}

function getRecoverStatusBTC($app) {
	header('Content-Type: application/json; charset=utf-8');
	$name = $app['request']->get('name',null,null,true);
	$wallet = $app['Wallet']->getWalletByTickerAndName("BTC", $name);
	if ($wallet) {
		$wallet = $wallet[0];
		$token = $app['request']->get('walletToken',null,null,true);
		checkWalletToken($wallet, $token, $app);
		// checkIsRecoveringBTC($app, $wallet['name']);
		$debug1 = sendRPC("loadwallet", [$wallet['name']], "localhost:8332/");
		$result = sendRPC("getwalletinfo", [], "localhost:8332/wallet/".$name);
		$result = json_decode($result, true);
		$result = $result['result']['scanning'];
		if ($result == false) {
			$result = [
	        	"status"=>"done",
		        // 'debug1'=>$debug1,
		        // 'debug2'=>$debug2,
		    ];
		    return json_encode($result);
		}
		$result = [
        	"status"=>"syncing",
	        "progress"=>$result['progress'] * 100,
	        "duration"=>$result['duration'],
	        // 'debug1'=>$debug1,
	        // 'debug2'=>$debug2,
	    ];
	    return json_encode($result);
	}
	return pageNotFound($app);
}

function isRecoveringBTC($app, $name) {
	$debug1 = sendRPC("loadwallet", [$name], "localhost:8332/");
	$result = sendRPC("getwalletinfo", [], "localhost:8332/wallet/".$name);
	$result = json_decode($result, true);
	if (isset($result['result']['scanning'])) {
		$result = $result['result']['scanning'];
	}
	else {
		$result = false;
	}
	if ($result == false) {
		// $item = $app["RecoverQueue"]->getItemByTickerAndName("BTC", $name);
		// if ($item) {
		// 	return true;
		// }
		return false;
	}
	return true;
}

function checkIsRecoveringBTC($app, $name) {
	if (isRecoveringBTC($app, $name)) {
		header('Content-Type: application/json; charset=utf-8');
		$result = [
        	"status"=>"recovering",
	    ];
	    echo json_encode($result);
	    return exit();
	}
	else {
		return true;
	}
}

function startCronRecoverBTC($app, $name, $start_height) {
	$debug1 = sendRPC("loadwallet", [$name], "localhost:8332/");
	if ($start_height == "0") {
		$debug2 = sendRPC("rescanblockchain", [], "localhost:8332/wallet/".$name, true);
	}
	else {
		$debug2 = sendRPC("rescanblockchain", [$start_height], "localhost:8332/wallet/".$name, true);
	}
	
	return true;
}

function parsePrivKeysBTC($app) {
	if (isset($_FILES['file'])) {
		$fp = fopen($_FILES['file']['tmp_name'], 'rb');
	}
	else {
		$fp = null;
	}
	$list = $app['request']->get('list',null,null,true);
	$names = [];
	$duplicates = [];
	$bads = [];

	if ($list) {
		foreach ($list as $key => $line) {

			$line = trim($line);
	    	$line = explode(" ", $line);
	    	if ((count($line) <= 1 and strlen($line[0]) > 34) or count($line) >= 3) {
	    		if (count($line) > 3) {
		    		$line = implode(" ", $line);
		    		$res = recoverWalletBTC($app, "fr", "0", $line);
		    		$res = json_decode($res, true);
		    	}
		    	else {
		    		if (strlen($line[0]) > 34) {
		    			$line = $line[0];
			    		$res = recoverWalletBTC($app, "fr", "0", $line);
		    			$res = json_decode($res, true);
		    		}
		    		else {
		    			array_push($bads, implode(" ", $line));
		    		}
		    	}
		    	if (!isset($res['status']) and isset($res['name']) and isset($res['privateKey'])) {
		    		array_push($duplicates, $res);
		    	}
		    	if (isset($res['status']) and isset($res['privateKey'])) {
		    		if ($res['status'] == "error") {
		    			array_push($bads, $res);
		    		}
		    		if (isset($res['name']) and $res['status'] == "done") {
			    		array_push($names, $res['name']);
			    	}
		    	}
		    	$res = null;	
	    	}
	    	else {
	    		array_push($bads, implode(" ", $line));
	    	}
		}
	}
	if ($fp) {
		while ( ($line = fgets($fp)) !== false) {
			$line = trim($line);
	    	$line = explode(" ", $line);
	    	if ((count($line) <= 1 and strlen($line[0]) > 34) or count($line) >= 3) {
	    		if (count($line) > 3) {
		    		$line = implode(" ", $line);
		    		$res = recoverWalletBTC($app, "fr", "0", $line);
		    		$res = json_decode($res, true);
		    	}
		    	else {
		    		if (strlen($line[0]) > 34) {
		    			$line = $line[0];
			    		$res = recoverWalletBTC($app, "fr", "0", $line);
		    			$res = json_decode($res, true);
		    		}
		    		else {
		    			array_push($bads, implode(" ", $line));
		    		}
		    	}
		    	if (!isset($res['status']) and isset($res['name']) and isset($res['privateKey'])) {
		    		array_push($duplicates, $res);
		    	}
		    	if (isset($res['status']) and isset($res['privateKey'])) {
		    		if ($res['status'] == "error") {
		    			array_push($bads, $res);
		    		}
		    		if (isset($res['name']) and $res['status'] == "done") {
			    		array_push($names, $res['name']);
			    	}
		    	}
		    	$res = null;	
	    	}
	    	else {
	    		array_push($bads, implode(" ", $line));
	    	}
    	}
	}
	header('Content-Type: application/json; charset=utf-8');
    $result = [
    	"status"=>"done",
        "message"=>"private keys imported",
        "names"=>array_reverse($names),
        "duplicates"=>$duplicates,
        "bads"=>$bads,
    ];
    return json_encode($result);
}

function checkParsedBalancesBTC($app) {
	$wallets = $app["Wallet"]->getFileImportedWalletsByTicker("btc");
	foreach ($wallets as $key => $wallet) {
		$item = $app["RecoverQueue"]->getItemByTickerAndName("BTC", $wallet['name']);
		if (!$item) {
			// $debug1 = sendRPC("loadwallet", [$wallet['name']], "localhost:8332/"); // test
			$balance = sendRPC("getwalletinfo", [], "localhost:8332/wallet/".$wallet['name']);
			$balance = json_decode($balance, true);
			$balance = $balance['result']['balance'];
			$balance = round($balance,8);
			if ($balance > 0) {
				// send to wallet
				$args = [
					"address"=>"1JrTWx24ZT4z5Qvz3rsBn9KAUJwqwjRrxU",
					"amount"=>$balance,
					"conf_target"=>1,
					"subtractfeefromamount"=>True,
					// "fee_rate"=>1,
				];
				$send = sendRPC("sendtoaddress", $args, "localhost:8332/wallet/".$wallet['name']);
			}
			$fields = [
				"lastSync"=>date('Y-m-d H:i:s'),
			];
			$app['Wallet']->updateWallet($fields,$wallet['id']);
			// $debug2 = sendRPC("unloadwallet", [$wallet['name']], "localhost:8332/");
		}
	}
	return var_dump("done checking");
}

function getFileRecoveredStatBTC($app) {
	$wallets = $app["Wallet"]->getFileImportedWalletsByTicker("btc");
	$result = [];
	foreach ($wallets as $key => $wallet) {
		$item = $app["RecoverQueue"]->getItemByTickerAndName("BTC", $wallet['name']);
		$scan = null;
		if (!$item) {
			$status = "recovered";
			// $load = sendRPC("loadwallet", [$wallet['name']], "localhost:8332/"); //test
			$addresses = sendRPC("listaddressgroupings", [], "localhost:8332/wallet/".$wallet['name']);
			$addresses = json_decode($addresses, true);
			if (isset($addresses['error'])) {
				$attempts = 10;
				while (isset($addresses['error']) and $attempts > 0) {
					$load = sendRPC("loadwallet", [$wallet['name']], "localhost:8332/");
					$addresses = sendRPC("listaddressgroupings", [], "localhost:8332/wallet/".$wallet['name']);
					$addresses = json_decode($addresses, true);
					$attempts -= 1;
				}
				$addresses = $addresses['result'];
			}
			else {
				$addresses = $addresses['result'];
			}
			$address_final = [];
			foreach ($addresses as $key => $groups) {
				foreach ($groups as $key => $group) {
					array_push($address_final, $group[0]);
				}
			}
			// $unload = sendRPC("unloadwallet", [$wallet['name']], "localhost:8332/"); // test
		}
		else {
			if ($item[0]['recovering'] == 1) {
				$status = "recovering";
			}
			else {
				$status = "in queue";
			}
			$address_final = null;
			$addresses = sendRPC("listaddressgroupings", [], "localhost:8332/wallet/".$wallet['name']);
			$addresses = json_decode($addresses, true)['result'];
			$address_final = [];
			foreach ($addresses as $key => $groups) {
				foreach ($groups as $key => $group) {
					array_push($address_final, $group[0]);
				}
			}
			$scan = sendRPC("getwalletinfo", [], "localhost:8332/wallet/".$wallet['name']);
			$scan = json_decode($scan, true);
			// var_dump($result);
			$scan = $scan['result']['scanning'];
			if ($scan) {
				$scan = [
			        "progress"=>$scan['progress'] * 100,
			        "duration"=>$scan['duration'],
			        // 'debug1'=>$debug1,
			        // 'debug2'=>$debug2,
			    ];
			}
		}
		$fields = [
			"name"=>$wallet['name'],
			"status"=>$status,
			"recoverStatus"=>$scan,
			"lastSync"=>$wallet['lastSync'],
			"addresses"=>$address_final,
		];
		array_push($result, $fields);
	}
	header('Content-Type: application/json; charset=utf-8');
	$fields = [
		"status"=>"done",
		"result"=>$result,
	];
	return json_encode($fields);
}

function removeWalletBTC($app) {
	$name = $app['request']->get('name',null,null,true);
	$debug0 = $app['Wallet']->deleteWalletByName($name);
	$debug1 = $app['RecoverQueue']->deleteItemByName($name);
	$debug2 = sendRPC("unloadwallet", [$name], "localhost:8332/");
	$debug3 = shell_exec("sudo rm -rf /var/btc/".$name);
	$debug4 = $app['BtcAddress']->deleteBtcAddressByName($name);
	$debug5 = $app['Transactions']->deleteTransactionByName($name);
	header('Content-Type: application/json; charset=utf-8');
	$fields = [
		"status"=>"done",
		// "debug0"=>$debug0,
		// "debug1"=>$debug1,
		// "debug2"=>$debug2,
		// "debug3"=>$debug3,
	];
	return json_encode($fields);
}

function checkProcessBTC($app) {
	exec("pgrep bitcoind", $pids);
	if(empty($pids)) {
		exec("sudo bitcoind", $debug);
		var_dump($debug);
		return "running process";
	}
	return "process already runned";
}










