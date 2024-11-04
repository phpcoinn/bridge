<?php
define("CLI_DEBUG", true);
$args = [];
for($i = 1; $i < count($argv); $i++) {
    $arg = $argv[$i];
    $arr=explode("=", $arg);
    $name=$arr[0];
    $value=$arr[1];
    $args[$name]=$value;
}


require_once '/var/www/phpcoin-mainnet/utils/sdk.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/BridgeService.php';
require 'vendor/autoload.php';
require_once './MyEthAbi.php';

$node = "https://main1.phpcoin.net";


$q=$args['q'];
$blockchain = $args['blockchain'];

$block = SdkUtil::api_get($node,"currentBlock");
$elapsed = time() - $block['date'];

if($elapsed > 600) {
    _log("PHPCoin mainnet elapsed=$elapsed stucked - exit");
    exit;
}

$bridgeService = new BridgeService($blockchain);

$elapsedBlockchain = $bridgeService->getElapsedTime();
if(time() - $elapsedBlockchain > 600) {
    _log("$blockchain elapsed=$elapsedBlockchain stucked - exit");
    exit;
}

//php cli.php blockchain=Polygon q=checkMint
if($q=="checkMint") {
    $bridgeService->checkMint();
}

//php cli.php blockchain=Polygon q=checkBurn
if($q=="checkBurn") {
    $bridgeService->checkBurn();
}
