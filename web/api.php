<?php

ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_DEPRECATED);

use Web3\Utils;

@define("DEFAULT_CHAIN_ID", "00");
@define("ROOT", "/var/www/phpcoin-mainnet");
require ROOT . "/vendor/autoload.php";

require '../vendor/autoload.php';

global $config;
require '../config.php';
require_once '../MyEthAbi.php';
require_once __DIR__ . '/../BridgeService.php';

if(!isset($_GET['q'])) {
    api_err("Invalid request");
}

$q=$_GET['q'];
$data=json_decode(file_get_contents('php://input'));

try {

    if ($q == "validateAddress") {
        $address = $_REQUEST['address'];
        $chain = $_REQUEST['chain'];
        $valid = Utils::isAddress($address);
        api_echo($valid);
    }

    if ($q == "getConversions") {
        $address = $_REQUEST['address'];
        $blockchain = $_REQUEST['blockchain'];
        $bridgeService = new BridgeService($blockchain);
        $list = $bridgeService->getConversions($address);
        api_echo($list);
    }

    if ($q == "getConfig") {
        api_echo(CONFIG);
    }

    if ($q == "getAbi") {
        $blockchain = $_REQUEST['blockchain'];
        $abi = file_get_contents(__DIR__ . "/../{$blockchain}_abi.json");
        api_echo($abi);
    }

    if ($q == "getBurnConversions") {
        $address = $_REQUEST['address'];
        $blockchain = $_REQUEST['blockchain'];
        $bridgeService = new BridgeService($blockchain);
        $list = $bridgeService->getBurnConversions($address);
        api_echo($list);
    }

    if ($q == "getTotalSupply") {
        $blockchain = $_REQUEST['blockchain'];
        $bridgeService = new BridgeService($blockchain);
        api_echo($bridgeService->getTotalSupply());
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    api_err($e->getMessage());
}

api_err("Invalid request");