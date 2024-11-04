<?php

use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\Utils;
use Web3\Web3;

class BridgeService
{

    public $blockchain;
    private $bridgeConfig;
    private $blockchainConfig;
    private \Web3\Eth $eth;
    private Contract $contract;
    private $node;
    private $state;

    function __construct($blockchain) {
        $this->blockchain = $blockchain;
        $this->blockchainConfig = CONFIG['blockchains'][$this->blockchain];
        $this->connectBlockChainRpc();
        $this->node = CONFIG['node'];
        $this->chainId = CONFIG['chainId'];
    }

    public function checkMint()
    {
        $this->loadState();
        $address = $this->blockchainConfig['phpCoinAddress'];
        $minConfirmations = CONFIG['minConfirmations'];
        $startHeight = $this->state[$this->blockchain]['startHeight'];
        if(empty($startHeight)) {
            $startHeight = $this->blockchainConfig['startHeight'];
        }
        $block = SdkUtil::api_get($this->node, "currentBlock");
        $block_height = $block['height'];
        _log("Processing bridge transactions for " . $address);
        $txs = SdkUtil::api_get($this->node, "findTransactions&dst=" . $address."&fromHeight=$startHeight");
        _log("Found " . count($txs) . " transactions");
        $txs = array_reverse($txs);
        $minted_none = true;
        foreach ($txs as $tx) {
            $txId = $tx['id'];
            _log("Processing transaction " . $txId);
            $height = $tx['height'];
            $confirmations = $block_height - $height;
            if ($confirmations < $minConfirmations) {
                _log("Not enough confirmations $confirmations / $minConfirmations");
                continue;
            }
            $sender = $tx['src'];
            $amount = $tx['val'];
            $chainAddress = $tx['message'];
            if (empty($chainAddress)) {
                _log("Empty transaction message");
                continue;
            }
            _log("Sender = $sender Amount = $amount Chain address = $chainAddress");

            $mintTx = $this->find_mint_tx($chainAddress, $amount, $txId);
            if ($mintTx) {
                _log("Transaction already minted " . $mintTx);
                continue;
            }
            $targetChainTxId = $this->mint($chainAddress, $amount, $txId);
            $minted_none = false;
        }
        if($minted_none) {
            $this->state[$this->blockchain]['startHeight'] = $height;
            $this->saveState();
        }
    }

    function find_mint_tx($chainAddress, $amount, $txId) {
        $decimals = $this->blockchainConfig['decimals'];
        $contractDeployHeight = $this->blockchainConfig['contractDeployHeight'];
        $contractAddress = $this->blockchainConfig['contractAddress'];
        $amountWei = bcmul($amount, pow(10, $decimals));
        $eventSignature = "MintToken(address,uint256,string)";
        $transferEventSignature = \Web3\Utils::sha3($eventSignature);
        $topics = [
            $transferEventSignature,  // Event type
            '0x' . str_pad(substr($chainAddress, 2), 64, '0', STR_PAD_LEFT)  // Sender address topic
        ];
        $options = [
            'fromBlock' => '0x' . dechex($contractDeployHeight),  // Start from the first block
            'toBlock' => 'latest',
            'address' => $contractAddress,
            'topics' => $topics
        ];
        $this->eth->getLogs($options, function ($err, $logs) use ($txId, $chainAddress,$amountWei, &$foundTx) {
            if ($err !== null) {
                throw new Error($err->getMessage());
            }
            $foundTx = null;
            $ethabi = new MyEthAbi();
            foreach ($logs as $log) {
                $data = $log->data;
                $params = $ethabi->decodeData(['uint256','string'], $data);
                $decodedAmount = $params[0];
                $decodedTxId = $params[1];

                if($decodedTxId == $txId && $amountWei == $decodedAmount->toString()) {
                    $foundTx = $log->transactionHash;
                    break;
                }
            }
        });
        return $foundTx;
    }

    function connectBlockChainRpc() {
        $rpcUrl = $this->blockchainConfig['rpcUrl'];
        $web3 = new Web3(new HttpProvider($rpcUrl, 5));
        $this->eth = $web3->eth;
        $abi = file_get_contents(__DIR__ . "/{$this->blockchain}_abi.json");
        $this->contract = new Contract($web3->provider, $abi);
    }

    private function mint($chainAddress, mixed $amount, mixed $txId)
    {
        $decimals = $this->blockchainConfig['decimals'];
        $contractAddress = $this->blockchainConfig['contractAddress'];
        $chainId = $this->blockchainConfig['chainId'];
        $mintAmount = bcmul($amount, pow(10, $decimals));
        echo "mintAmount=$mintAmount" . PHP_EOL;
        $rawTransactionData = '0x' . $this->contract->at($contractAddress)->getData('mint', $chainAddress, $mintAmount, $txId);
        echo "rawTransactionData=$rawTransactionData" . PHP_EOL;
        $transactionCount = $this->get_tx_count($this->blockchainConfig['walletAddress']);
        echo "transactionCount=$transactionCount" . PHP_EOL;
        $transactionParams = [
            'nonce' => "0x" . dechex($transactionCount->toString()),
            'from' => $this->blockchainConfig['walletAddress'],
            'to' => $contractAddress,
            'value' => '0x0',
            'data' => $rawTransactionData
        ];
        $gasLimit = $this->estimate_gas($transactionParams);
        echo "Gas limit base = ".$gasLimit."\n";
        $gasPrice = $this->get_gas_price();
        echo "Gas price = ".$this->toDecimal($gasPrice)."\n";
        $totalGas = bcmul($gasPrice, $gasLimit->toString());
        echo "Total Gas: " . $this->toDecimal($totalGas) . ' POL' . PHP_EOL;
        $transactionParams['gas']='0x' .dechex($gasLimit->toString());
        $transactionParams['gasPrice']='0x' . dechex($gasPrice);
        $transactionParams['chainId'] = $chainId;
        $walletPrivateKey = SECURE_CONFIG["blockchains"][$this->blockchain]['walletPrivateKey'];
        $txHash = $this->send_raw_tx($walletPrivateKey, $transactionParams);
        echo "txHash=$txHash" . PHP_EOL;
        return $txHash;
    }

    function get_tx_count($walletAddress) {
        $transactionCount = null;
        $this->eth->getTransactionCount($walletAddress, function ($err, $transactionCountResult) use (&$transactionCount) {
            if ($err) {
                echo 'getTransactionCount error: ' . $err->getMessage() . PHP_EOL;
            } else {
                $transactionCount = $transactionCountResult;
            }
        });
        return $transactionCount;
    }

    function estimate_gas($transactionParams) {
        $estimatedGas = null;
        $this->eth->estimateGas($transactionParams, function ($err, $gas) use (&$estimatedGas) {
            if ($err) {
                echo 'estimateGas error: ' . $err->getMessage() . PHP_EOL;
            } else {
                $estimatedGas = $gas;
            }
        });
        return $estimatedGas;
    }

    function get_gas_price() {
        $gasPrice = 0;
        $this->eth->gasPrice(function ($err, $gasPriceResult) use (&$gasPrice) {
            if ($err !== null) {
                echo 'Error: ' . $err->getMessage() . PHP_EOL;
                return;
            }
            $gasPrice = $gasPriceResult->toString();
        });
        return $gasPrice;
    }

    function send_raw_tx($walletPrivateKey, $transactionParams) {
        $tx = new \Web3p\EthereumTx\Transaction($transactionParams);
        $signedTx = '0x' . $tx->sign($walletPrivateKey);
        $txHash = null;
        $this->eth->sendRawTransaction($signedTx, function ($err, $txResult) use (&$txHash) {
            if($err) {
                echo 'transaction error: ' . $err->getMessage() . PHP_EOL;
            } else {
                $txHash = $txResult;
            }
        });
        return $txHash;
    }

    public function getConversions($address)
    {
        $dst = $this->blockchainConfig['phpCoinAddress'];
        $rows = SdkUtil::api_get($this->node, "findTransactions&src=" . $address."&dst=".$dst."&type=1");
        $txs = [];
        foreach ($rows as $row) {
            $dst = $row["message"];
            $amount = $row['val'];
            $mint_tx = $this->find_mint_tx($dst, $amount, $row['id']);
            $txs[]=[
                "id"=>$row["id"],
                "date"=>$row["date"],
                "date_full"=>date("Y-m-d H:i:s", $row["date"]),
                "height"=>$row["height"],
                "val"=>$row["val"],
                "dst"=>$dst,
                "mint_tx"=>$mint_tx
            ];

        }
        return $txs;
    }

    public function getBurnConversions($address) {
        $contractDeployHeight = $this->blockchainConfig['contractDeployHeight'];
        $contractAddress = $this->blockchainConfig['contractAddress'];
        $eventSignature = "BurnToken(address,uint256,string)";
        $transferEventSignature = \Web3\Utils::sha3($eventSignature);
        $topics = [
            $transferEventSignature,  // Event type
            '0x' . str_pad(substr($address, 2), 64, '0', STR_PAD_LEFT)  // Sender address topic
        ];
        $options = [
            'fromBlock' => '0x' . dechex($contractDeployHeight),  // Start from the first block
            'toBlock' => 'latest',
            'address' => $contractAddress,
            'topics' => $topics
        ];
        $foundLogs = null;
        $this->eth->getLogs($options, function ($err, $logs) use (&$foundLogs) {
            if ($err !== null) {
                return;
            }
            $foundLogs = $logs;
        });
        $list = [];
        $ethabi = new MyEthAbi();
        $foundLogs = array_reverse($foundLogs);
        foreach ($foundLogs as $log) {
            $txHash = $log->transactionHash;
            $blockNumber = hexdec($log->blockNumber);
            $data = $log->data;
            $params = $ethabi->decodeData(['uint256','string'], $data);
            $decodedAmount = $params[0];
            $decodedPHPCoinAddress = $params[1];
            $txId = null;
            $src = $this->blockchainConfig['phpCoinAddress'];
            $rows = SdkUtil::api_get($this->node, "findTransactions&src=" . $src."&dst=".$decodedPHPCoinAddress."&type=1&message=$txHash");
            if($rows) {
                $txId = $rows[0]['id'];
            }

            $list[]=[
                "txhash"=>$log->transactionHash,
                "height"=>$blockNumber,
                "val"=>$this->toDecimal($decodedAmount->toString()),
                "dst"=>$decodedPHPCoinAddress,
                "txId"=>$txId
            ];
        }

        return $list;
    }

    public function checkBurn()
    {
        $this->loadState();
        $contractDeployHeight = $this->blockchainConfig['contractDeployHeight'];
        $contractAddress = $this->blockchainConfig['contractAddress'];
        $burnMethod = "BurnToken(address,uint256,string)";
        $burnSignature = \Web3\Utils::sha3($burnMethod);
        $topics = [
            $burnSignature
        ];
        $startHeight = $this->state[$this->blockchain]['processedHeight'];
        if(empty($startHeight)) {
            $startHeight = $contractDeployHeight;
        }
        $options = [
            'fromBlock' => '0x' . dechex($startHeight),  // Start from the first block
            'toBlock' => 'latest',
            'address' => $contractAddress,
            'topics' => $topics
        ];
        $foundLogs = null;
        $this->eth->getLogs($options, function ($err, $logs) use (&$foundLogs) {
            if ($err !== null) {
                return;
            }
            $foundLogs = $logs;
        });
        _log("Found ".count($foundLogs)." logs");

        $height = $this->getHeight();
        $min_confirmations = $this->blockchainConfig['minConfirmations'];

        $ethabi = new MyEthAbi();
        $processed = 0;
        $total = 0;
        foreach ($foundLogs as $log) {
            $total++;
            $blockNumber = hexdec($log->blockNumber);
            $confirmations = $height - $blockNumber;
            _log("Processing transaction ".$log->transactionHash. " block=$blockNumber confirmations=$confirmations/$min_confirmations");
            if($confirmations < $min_confirmations) {
                _log("Not enough confirmations");
                continue;
            }
            $txHash = $log->transactionHash;
            $data = $log->data;
            $params = $ethabi->decodeData(['uint256','string'], $data);
            $decodedAmount = $params[0];
            $decodedPHPCoinAddress = $params[1];
            $sender = $ethabi->decodeData(['address'], $log->topics[1])[0];
            $amount = $this->toDecimal($decodedAmount);
            _log("Burning ".$amount." address=".$sender." phpcoin address=".$decodedPHPCoinAddress);
            $src = $this->blockchainConfig['phpCoinAddress'];
            $rows = SdkUtil::api_get($this->node, "findTransactions&src=" . $src."&dst=".$decodedPHPCoinAddress."&type=1&message=$txHash&mempool=1", $err);
            if($rows === false) {
                _log("Error searching transaction in mempool: $err");
                continue;
            }
            if(count($rows)>0) {
                _log("Transaction in mempool");
                continue;
            }

            $rows = SdkUtil::api_get($this->node, "findTransactions&src=" . $src."&dst=".$decodedPHPCoinAddress."&type=1&message=$txHash");
            if($rows === false) {
                _log("Error searching transaction in mempool");
                continue;
            }
            if(count($rows)>0) {
                _log("Transaction is already processed");
                $processed++;
                continue;
            }

            $private_key = SECURE_CONFIG['blockchains'][$this->blockchain]['phpCoinAddressPrivateKey'];
            $res = SdkUtil::createAndSendTx($this->node, $private_key, $decodedPHPCoinAddress, $amount, TX_TYPE_SEND, $txHash, $this->chainId);
            if($res) {
                _log("Created transaction $res");
            }
            sleep(10);
        }
        if($processed == $total) {
            $this->state[$this->blockchain]['processedHeight']=$blockNumber;
        }
        $this->saveState();
    }

    function toDecimal($num, $decimals = 8) {
        return bcdiv($num, pow(10, $this->blockchainConfig['decimals']), $decimals);
    }

    function loadState() {
        $this->state = json_decode(file_get_contents('./state.json'), true);
    }

    function saveState() {
        file_put_contents('./state.json', json_encode($this->state, JSON_PRETTY_PRINT));
    }

    public function getTotalSupply()
    {
        $address = $this->blockchainConfig['phpCoinAddress'];
        $res = SdkUtil::api_get($this->node, 'getBalance&address='.$address);
        $data['phpcoinBalance']=$res;
        $data['tokenSupply']=$this->getTokenTotalSupply();
        return $data;
    }

    function getTokenTotalSupply() {
        $balance = null;
        $contractAddress = $this->blockchainConfig['contractAddress'];
        $this->contract->at($contractAddress)->call('totalSupply', [
            'from' => $contractAddress
        ], function ($err, $results) use (&$balance) {
            if ($err !== null) {
                echo $err->getMessage() . PHP_EOL;
            }
            if (isset($results)) {
                foreach ($results as &$result) {
                    $bn = Utils::toBn($result);
                    $balance = $bn->toString();
                    break;
                }
            }
        });
        return $this->toDecimal($balance);
    }

    public function getHeight() {
        $height = null;
        $this->eth->blockNumber(function ($err, $blockNumber)  use (&$height) {
            if ($err !== null) {
                throw new Error($err->getMessage());
            }
            $height = $blockNumber->toString();
        });
        return $height;
    }

    public function getElapsedTime() {
        $ts = null;
        $blockNumber = $this->getHeight();
        $latestBlockHex = '0x' . dechex($blockNumber);
        $this->eth->getBlockByNumber($latestBlockHex, false, function ($err, $block) use (&$ts) {
            if ($err !== null) {
                throw new Error($err->getMessage());
            }
            $tsHex = $block->timestamp;
            $ts = hexdec($tsHex);
        });
        return $ts;
    }

}