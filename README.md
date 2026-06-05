# PHPCoin Bridge

Cross-chain bridge between PHPCoin and Polygon.

The bridge locks PHPCoin sent to a configured bridge address and mints the
equivalent Polygon token amount. In the opposite direction, it detects Polygon
token burns and sends PHPCoin to the requested destination address.

Live application: [bridge.phpcoin.net](https://bridge.phpcoin.net)

## Architecture

- `BridgeService.php` - mint/burn processing and conversion queries
- `cli.php` - CLI entrypoint for scheduled mint and burn workers
- `web/index.php` - browser bridge interface
- `web/api.php` - read-only bridge API used by the interface
- `config.php` - public chain and bridge configuration
- `config.secure.php` - local private keys, excluded from git
- `state.json` - local worker progress, excluded from git
- `Polygon_abi.json` - deployed Polygon token contract ABI

The application depends on a PHPCoin node checkout because it uses the PHPCoin
PHP SDK from `/var/www/phpcoin-mainnet/utils/sdk.php`.

## Requirements

- PHP with BCMath
- Composer
- PHPCoin node checkout and PHP SDK
- Polygon RPC endpoint
- Funded Polygon bridge wallet
- Funded PHPCoin bridge wallet
- Web server with the document root set to `web/`

Install PHP dependencies:

```bash
composer install
```

## Secure Configuration

Create `config.secure.php` locally. Never commit this file.

```php
<?php

const SECURE_CONFIG = [
    'blockchains' => [
        'Polygon' => [
            'walletPrivateKey' => 'POLYGON_PRIVATE_KEY',
            'phpCoinAddressPrivateKey' => 'PHPCOIN_PRIVATE_KEY',
        ],
    ],
];
```

Create the initial local worker state:

```json
{
  "Polygon": {
    "startHeight": null,
    "processedHeight": null
  }
}
```

Save it as `state.json`. The file is updated by the CLI workers and must be
writable by the worker user.

## Workers

Check PHPCoin deposits and mint Polygon tokens:

```bash
php cli.php blockchain=Polygon q=checkMint
```

Check Polygon burns and send PHPCoin:

```bash
php cli.php blockchain=Polygon q=checkBurn
```

Example cron schedule:

```cron
*/5 * * * * cd /var/www/bridge && php cli.php blockchain=Polygon q=checkMint >> /var/www/bridge/logs/mint.log
*/5 * * * * cd /var/www/bridge && php cli.php blockchain=Polygon q=checkBurn >> /var/www/bridge/logs/burn.log
```

## Web API

The web interface uses `web/api.php` with these actions:

- `validateAddress`
- `getConfig`
- `getAbi`
- `getConversions`
- `getBurnConversions`
- `getTotalSupply`

Example:

```text
/api.php?q=getTotalSupply&blockchain=Polygon
```

## Safety

- Keep `config.secure.php` outside the web root and restrict its permissions.
- Keep `state.json`, logs, `vendor/`, and development files out of git.
- Test configuration and dependency updates before deploying because workers
  control funded bridge wallets.
- Monitor both worker logs and bridge wallet balances.

## Validation

Run PHP syntax checks:

```bash
php -l BridgeService.php
php -l cli.php
php -l config.php
php -l web/api.php
php -l web/index.php
```

Audit Composer dependencies:

```bash
composer audit --locked
```
