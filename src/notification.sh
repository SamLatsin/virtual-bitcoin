#!/bin/sh
curl 'http://127.0.0.1:6565/api/walletnotify/btc' -X 'POST' -d "txid=$1&name=$2&token={PUT_YOUR_TOKEN_HERE}"
