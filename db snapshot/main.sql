SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE TABLE `btc_addresses` (
  `id` int NOT NULL,
  `name` text,
  `address` text,
  `balance` double DEFAULT NULL,
  `unconfirmed` double DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

CREATE TABLE `functions` (
  `id` int NOT NULL,
  `ticker` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `create` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `newAddress` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `getAddress` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `send` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `history` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `balance` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `status` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `fee` text,
  `recover` text,
  `recoverStatus` text,
  `getTransaction` text,
  `isRecovering` text,
  `startCronRecover` text,
  `parsePrivKeys` text,
  `checkParsedBalances` text,
  `getFileRecoveredStat` text,
  `removeWallet` text,
  `checkProcess` text,
  `getConfirmations` text,
  `addressBalance` text,
  `getWalletList` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `functions` (`id`, `ticker`, `create`, `newAddress`, `getAddress`, `send`, `history`, `balance`, `status`, `fee`, `recover`, `recoverStatus`, `getTransaction`, `isRecovering`, `startCronRecover`, `parsePrivKeys`, `checkParsedBalances`, `getFileRecoveredStat`, `removeWallet`, `checkProcess`, `getConfirmations`, `addressBalance`, `getWalletList`) VALUES
(1, 'btc', 'createWalletBTC', 'createNewAddressBTC', NULL, 'sendBTC', 'getHistoryBTC', 'getBalanceBTC', 'getStatusBTC', 'getFeeBTC', 'recoverWalletBTC', 'getRecoverStatusBTC', 'getTransactionBTC', 'isRecoveringBTC', 'startCronRecoverBTC', 'parsePrivKeysBTC', 'checkParsedBalancesBTC', 'getFileRecoveredStatBTC', 'removeWalletBTC', 'checkProcessBTC', 'getConfirmationsBTC', 'getAddressBalanceBTC', 'getWalletListBTC');

-- --------------------------------------------------------

CREATE TABLE `recover_queue` (
  `id` int NOT NULL,
  `ticker` text,
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `walletName` text,
  `recovering` int NOT NULL DEFAULT '0',
  `startHeight` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

CREATE TABLE `transactions` (
  `id` int NOT NULL,
  `fromWallet` text,
  `toWallet` text,
  `fromAddress` text,
  `toAddress` text,
  `amount` double DEFAULT NULL,
  `fee` double DEFAULT NULL,
  `checks` int DEFAULT '0',
  `fromChecks` int NOT NULL DEFAULT '0',
  `toChecks` int NOT NULL DEFAULT '0',
  `txid` text,
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

CREATE TABLE `wallets` (
  `id` int NOT NULL,
  `ticker` text NOT NULL,
  `name` text NOT NULL,
  `privateKey` text NOT NULL,
  `mnemonic` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `walletToken` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `lastSync` datetime DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

ALTER TABLE `btc_addresses`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `functions`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `recover_queue`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `btc_addresses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `functions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `recover_queue`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=156;

ALTER TABLE `transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `wallets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
