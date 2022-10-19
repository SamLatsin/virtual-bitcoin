# virtual-bitcoin
Implementation of crypto-nodes with no fee between same wallet. Based on my old project [crypto-nodes](https://github.com/SamLatsin/crypto-nodes)

## Overview

Bitcoin node API written in [PHP](https://www.php.net) [Phalcon](https://phalcon.io/en-us), persistant storage is [MySQL](https://www.mysql.com). See [API documentation](https://sam-latsin.gitbook.io/virtual-btc-api-eng/).
### Use cases
* Crypto P2P exchange
* Online marketplace
* Banking
* Personal purposes like cold wallet
### Features
* Create wallets
* Check balance
* Generate new address
* Check node status
* Network fee calculation
* Send cryptocurrency
* No fee between addresses in same wallet
* Get history of transactions
* Wallet recover with progress status
* Get transaction info
* Node auto restart on fail or boot
* No third-party API used
* Multiple wallets import by private key or mnemonic
* Cron task for checking imported wallets and sending all bitcoins to one wallet from them
* Wallletnotify implementation for updating transaction list
* Scalability

### Requirements
* Linux server
* At least 500 GB SSD
* At least 16 GB RAM
* [Phalcon framework](https://github.com/phalcon/cphalcon.git)
* [MySQL](https://www.mysql.com)
* [Bitcoind](https://github.com/bitcoin/bitcoin.git)
* [Bitcoin Explorer](https://github.com/libbitcoin/libbitcoin-explorer.git)

### Installation
The installation process may seem quite complicated, but you only need to do it once 😁

Install all requirments.

From command line run:
```
git clone https://github.com/SamLatsin/virtual-bitcoin.git
cd /var/www/
mv crypto-nodes/src/ /var/www/btc-node/
```

Import `virtual-bitcoin/db snapshot/main.sql` to your Database.

Add all lines from `virtual-bitcoin/configs/crontab.txt` to your crontab.

After installing Bitcoin node put corresponding config to them from `virtual-bitcoin/configs/bitcoin.conf`. Bitcoin data stored in `var/btc`.

Edit file `/var/www/crypto-rest-api/app/app.php` and put your Database credentials.

Edit file `/var/www/crypto-rest-api/app/route/load.php` and generate Bitcoin token.

After all steps done you can check if API works, check [API documentation](https://sam-latsin.gitbook.io/virtual-btc-api-eng/).

## License

Virtual-bitcoin is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

