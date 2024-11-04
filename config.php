<?php

const CONFIG = [
    "blockchains" => [
        "Polygon" => [
            "rpcUrl" => "https://polygon-rpc.com",
            "chainId" => 137,
            "phpCoinAddress" => "PpoLqN5aJ3fkcZvtWFuLH5SrqzA1f2DP2x",
            "startHeight" => 747149,
            "decimals"=>18,
            "contractDeployHeight" => 63710619,
            "contractAddress"=>"0x006E1D324FA995f1c1B8318b058Ae9c117A72c20",
            "walletAddress" => "0x9376eC118763df1e0B89bc5Ed42178fc951deF0E",
            "explorerUrl"=>"https://polygonscan.com/",
            "jsAbi"=>[
                "function decimals() view returns (string)",
                "function symbol() view returns (string)",
                "function balanceOf(address addr) view returns (uint)",
                "function burn(uint256 value, string coinAddress)"
            ],
            "minConfirmations" => 300,
        ]
    ],
    "minConfirmations" => 10,
    "node" => "https://main1.phpcoin.net",
    "chainId"=>"00"
];

require_once __DIR__ . "/config.secure.php";