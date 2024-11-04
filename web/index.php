<!doctype html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHPCoin Bridge</title>

    <link rel="stylesheet" href="https://node1.phpcoin.net/apps/common/css/preloader.min.css" type="text/css" />
    <link href="https://node1.phpcoin.net/apps/common/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css" />
    <link href="https://node1.phpcoin.net/apps/common/css/sweetalert2.min.css" rel="stylesheet" type="text/css" />
    <link href="https://node1.phpcoin.net/apps/common/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="https://node1.phpcoin.net/apps/common/css/app.min.css" id="app-style" rel="stylesheet" type="text/css" />


    <link href="/style.css" rel="stylesheet">
</head>
<body>
<script src="https://node1.phpcoin.net/apps/common/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
<script src="https://node1.phpcoin.net/apps/common/js/phpcoin-crypto.js" type="text/javascript"></script>
<script src="https://node1.phpcoin.net/apps/common/js/sweetalert2.min.js"></script>
<div id="app" class="container mt-5">


    <div class="card shadow-primary">
        <div class="card-header bg-transparent border-bottom">
            <div class="h3">PHPCoin Bridge</div>
        </div>
        <div class="card-body">


            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a :class="`nav-link ${side === 'mint' ? 'active' : ''}`" href="#" @click.prevent="switchSide('mint')">PHP Coin => Token</a>
                </li>
                <li class="nav-item">
                    <a :class="`nav-link ${side === 'burn' ? 'active' : ''}`" href="#" @click.prevent="switchSide('burn')">Token => PHP Coin</a>
                </li>
                <li class="nav-item ms-auto">
                    <a href="" @click.prevent="reset" class="nav-link">Reset</a>
                </li>
            </ul>

            <hr/>

            <template v-if="side === 'mint'">

                <div class="row mb-3 mt-3">
                    <label for="amount" class="col-sm-2 col-form-label">Network:</label>
                    <div class="col-sm-10">
                        <select v-model="selChain" @change="changedBlockChain" class="form-select">
                            <option value="">Select chain</option>
                            <option v-for="chain in chains" :key="chain">{{chain}}</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3 mt-3">
                    <div class="col-sm-6">
                        <div v-if="selChain && supply" class="border p-2">
                            <div class="fw-bold mb-2">Bridge status:</div>
                            <div>
                                <dl class="row mb-0">
                                    <dd class="col-sm-3">PHPCoin supply:</dd>
                                    <dt class="col-sm-9">
                                        <a :href="`${config.node}/apps/explorer/address.php?address=${config.blockchains[selChain].phpCoinAddress}`" target="_blank">
                                            {{supply.phpcoinBalance}}
                                        </a>
                                    </dt>
                                </dl>
                                <dl class="row mb-0">
                                    <dd class="col-sm-3">Token supply:</dd>
                                    <dt class="col-sm-9">
                                        <a :href="`${config.blockchains[selChain].explorerUrl}/token/${config.blockchains[selChain].contractAddress}`" target="_blank">
                                            {{supply.tokenSupply}}
                                        </a>
                                    </dt>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6" v-if="selChain">
                        <template v-if="!phputil.account">
                            <p>You need to login with your PHPCoin address from which you want to convert coins</p>
                            <button class="btn btn-outline-primary" @click="connectPHPCoinWallet">Connect PHPCoin Wallet</button>
                        </template>
                        <template v-else>
                            <div class="d-flex flex-wrap">
                                <div>
                                    <div>
                                        Your PHPCoin address:
                                        <div class="fw-bold">{{phputil.account.address}}</div>
                                    </div>
                                    <div class="mt-2">
                                        Your PHPCoin balance:
                                        <div class="fw-bold">{{phputil.balance}}</div>
                                    </div>
                                </div>
                                <div class="ms-auto">
                                    <button class="btn btn-outline-primary" @click="logout">Logout</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <form v-if="selChain" method="post" @submit.prevent="startConversion">
                    <div class="row mb-3">
                        <label for="amount" class="col-sm-2 col-form-label">PHPCoin Amount:</label>
                        <div class="col-sm-10">
                            <input type="text" v-model="amount" class="form-control" id="amount" required/>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="chainAddress" class="col-sm-2 col-form-label">Target chain address:</label>
                        <div class="col-sm-10">
                            <input type="text" v-model="chainAddress"  class="form-control" id="chainAddress" required/>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Start conversion</button>
                </form>


                    <hr/>
                    <template v-if="selChain">
                        <h4>Your conversions</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>height</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Dst</th>
                                    <th>Mint tx</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="item in conversions">
                                    <td>
                                        <a :href="`${config.node}/apps/explorer/tx.php?id=${item.id}`" target="_blank">
                                            {{shortenString(item.id)}}
                                        </a>
                                    </td>
                                    <td>{{item.height}}</td>
                                    <td class="text-nowrap">{{item.date_full}}</td>
                                    <td>{{item.val}}</td>
                                    <td>
                                        <a :href="`${config.blockchains[selChain].explorerUrl}/address/${item.dst}`" target="_blank">
                                            {{shortenString(item.dst)}}
                                        </a>
                                    </td>
                                    <td>
                                        <a :href="`${config.blockchains[selChain].explorerUrl}/tx/${item.mint_tx}`" target="_blank">
                                            {{shortenString(item.mint_tx)}}
                                        </a>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
            </template>
            <template v-if="side === 'burn'">
                <div class="row mb-3 mt-3">
                    <label for="amount" class="col-sm-2 col-form-label">Network:</label>
                    <div class="col-sm-10">
                        <select v-model="selChain" @change="changedBlockChain" class="form-select">
                            <option value="">Select chain</option>
                            <option v-for="chain in chains" :key="chain">{{chain}}</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3 mt-3" v-if="selChain && supply">
                    <div class="col-sm-6 border p-2">
                        <div class="fw-bold mb-2">Bridge status:</div>
                        <div>
                            <dl class="row mb-0">
                                <dt class="col-sm-3">PHPCoin supply:</dt>
                                <dd class="col-sm-9">
                                    <a :href="`${config.node}/apps/explorer/address.php?address=${config.blockchains[selChain].phpCoinAddress}`" target="_blank">
                                        {{supply.phpcoinBalance}}
                                    </a>
                                </dd>
                                <dt class="col-sm-3">Token supply:</dt>
                                <dd class="col-sm-9">
                                    <a :href="`${config.blockchains[selChain].explorerUrl}/token/${config.blockchains[selChain].contractAddress}`" target="_blank">
                                        {{supply.tokenSupply}}
                                    </a>
                                </dd>
                            </dl>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <button class="btn btn-primary" @click="connectMetamask" v-if="!signer">
                            Connect Metamask
                        </button>
                        <button class="btn btn-outline-primary" @click="disconenctMetamask" v-if="signer">Disconnect Metamask</button>
                        <template v-if="signer">
                            <dl class="row mb-0 mt-2">
                                <dt class="col-3">Address:</dt>
                                <dd class="col-9">
                                    <a :href="`${config.blockchains[selChain].explorerUrl}/address/${signer.address}`" target="_blank">
                                        {{signer.address}}
                                    </a>
                                </dd>
                                <dt class="col-3">Coin balance:</dt>
                                <dd class="col-9">{{coinBalance}}</dd>
                                <dt class="col-3">Token balance:</dt>
                                <dd class="col-9">{{balance}}</dd>
                            </dl>
                        </template>
                    </div>
                </div>
                <form v-if="signer" method="post" @submit.prevent="startBurnConversion">
                    <div class="row mb-3">
                        <label for="amount" class="col-sm-2 col-form-label">Token Amount:</label>
                        <div class="col-sm-10">
                            <input type="text" v-model="burnAmount" class="form-control" id="amount" required/>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="phpCoinAddress" class="col-sm-2 col-form-label">PHPCoin address:</label>
                        <div class="col-sm-10">
                            <input type="text" v-model="phpcoinAddress"  class="form-control" id="phpCoinAddress" required/>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Start conversion</button>
                </form>
                <div class="alert alert-info mt-2 d-flex align-items-center" v-if="burnTxStatus === 'pending'">
                    <i class="fa fa-circle-notch fa-spin fa-2x me-2"></i>
                    Transaction in progress...
                </div>
                <div class="alert alert-danger mt-2" v-if="burnTxStatus === 'error'">
                    Error sending transaction
                </div>
                <div class="alert alert-success mt-2" v-if="burnTxStatus === 'success'">
                    Transaction completed:
                    <a class="alert-link" :href="`${config.blockchains[selChain].explorerUrl}/tx/${tx.hash}`" target="_blank">
                        {{tx.hash}}
                    </a>
                </div>

                <hr/>

                <template v-if="burnConversions && signer && signer.address">
                    <h4>Your conversions</h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Height</th>
                                <th>Amount</th>
                                <th>Dst</th>
                                <th>Transfer tx</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="item in burnConversions">
                                <td>
                                    <a :href="`${config.blockchains[selChain].explorerUrl}/tx/${item.txhash}`" target="_blank">
                                        {{shortenString(item.txhash)}}
                                    </a>
                                </td>
                                <td>{{item.height}}</td>
                                <td>{{item.val}}</td>
                                <td>
                                    <a :href="`${config.node}/apps/explorer/address.php?address=${item.dst}`" target="_blank">
                                        {{shortenString(item.dst)}}
                                    </a>
                                </td>
                                <td>
                                    <a :href="`${config.node}/apps/explorer/tx.php?id=${item.txId}`" target="_blank">
                                        {{shortenString(item.txId)}}
                                    </a>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </template>
        </div>
    </div>
</div>

<script type="module">
    const { createApp, ref, toRaw } = Vue

    const appName = 'PHPCoin Bridge'

    import phputil from './phputil.js';
    import axios from 'https://cdn.jsdelivr.net/npm/@bundled-es-modules/axios@0.27.2/axios.min.js'
    import { ethers } from "https://cdnjs.cloudflare.com/ajax/libs/ethers/6.7.0/ethers.min.js";

    window.app = createApp({
        setup() {
        },
        data() {
            return {
                side: null,
                balance: null,
                chains: [
                    'Polygon'
                ],
                selChain: null,
                phputil: phputil,
                amount: null,
                chainAddress: null,
                conversions: null,
                config:  null,
                provider: null,
                signer: null,
                contract: null,
                abi: null,
                burnAmount: null,
                phpcoinAddress: null,
                coinBalance: null,
                tx: null,
                burnConversions: false,
                supply: null,
                burnTxStatus: null
            }
        },
        mounted() {



            this.api('getConfig', {}, config => {
                this.config = config;

                this.side = localStorage.getItem('bridgeSide');
                this.selChain = localStorage.getItem('selChain');
                if(this.selChain) {
                    this.changedBlockChain()
                    if(localStorage.getItem('connectedMetamask')) {
                        this.connectMetamask()
                    }
                }
                this.phputil.init({
                    appName: "PHPCoin Bridge",
                    node: this.config.node,
                    chainId: this.config.chainId
                },()=>{

                });
            })
        },
        computed: {
        },
        methods: {
            switchSide(side) {
                this.side = side;
                localStorage.setItem('bridgeSide', side);
            },
            connectPHPCoinWallet() {
                this.phputil.auth((res, err) => {
                    if(err) {
                        this.showError(err);
                    }
                });
            },
            logout() {
                this.phputil.logout(()=>{
                });
            },
            startConversion() {
                this.api('validateAddress',{
                    address: this.chainAddress,
                    chain: this.selChain
                }, valid=>{
                    if(!valid) {
                        this.showError('Invalid blockchain address');
                        return;
                    }
                    let tx = {
                        src: this.phputil.account.address,
                        dst: this.config.blockchains[this.selChain].phpCoinAddress,
                        val: this.amount,
                        type: 1,
                        msg: this.chainAddress
                    };
                    this.phputil.signTx(tx, (txId, err)=> {
                        if(err) {
                            this.showError(err);
                            return;
                        }
                        this.txId = txId;
                        window.Swal.fire({
                            title: "Success!",
                            text: `Your transaction is created: ${txId}`,
                            icon: "success"
                        });
                    });
                })
            },

            handleApiResponse(response, cb) {
                let res = response.data;
                if(!res) {
                    this.showError("No response from API");
                    return;
                }
                if(res.status !== 'ok') {
                    this.showError("Error response from API: "+res.data);
                    return;
                }
                cb(res.data);
            },
            showError(err) {
                console.trace();
                window.Swal.fire({
                    title: "Error!",
                    text: err,
                    icon: "error"
                });
            },
            api(q, params, cb) {
                axios.get(`/api.php?q=${q}`, {params}).then(res=>{
                    this.handleApiResponse(res, (res)=>{
                        cb(res);
                    });
                }).catch(err=>{
                    this.showError(err);
                });
            },
            apiPost(q, data, cb) {
                axios.post(`/api.php?q=${q}`, data).then(res => {
                    this.handleApiResponse(res, (res)=>{
                        cb(res);
                    });
                }).catch(err=>{
                    this.showError(err);
                })
            },
            getConversions() {
                if(!this.phputil.account) {
                    return;
                }
                this.api('getConversions', {address: this.phputil.account.address, blockchain: this.selChain}, list => {
                    this.conversions = list;
                })
            },
            connectMetamask() {
                if (window.ethereum == null) {
                    this.showError("MetaMask not installed");
                } else {
                    this.provider = new ethers.BrowserProvider(window.ethereum);
                    console.log(this.provider);
                    toRaw(this.provider).getSigner().then(signer => {
                        this.signer = signer;
                        console.log(this.signer)
                        this.abi = this.config.blockchains[this.selChain].jsAbi;
                        this.contract = new ethers.Contract(this.config.blockchains[this.selChain].contractAddress, this.abi, toRaw(this.provider))
                        console.log(this.contract);
                        localStorage.setItem('connectedMetamask', true)
                        toRaw(this.contract).balanceOf(this.signer.address).then(balance=>{
                            this.balance = ethers.formatEther(balance);
                            toRaw(this.provider).getBalance(this.signer.address).then(coinBalance=>{
                                this.coinBalance = ethers.formatEther(coinBalance);
                            }).catch(err=>{
                                this.showError(err);
                            });
                        }).catch(err=>{
                            this.showError(err);
                        });
                        this.getBurnConversions();
                    }).catch(err=>{
                        this.showError(err);
                    });
                }
            },
            disconenctMetamask() {
                this.provider = null;
                this.signer=null;
                localStorage.setItem('connectedMetamask', false)
            },
            startBurnConversion() {
                if(!window.verifyAddress(this.phpcoinAddress)) {
                    this.showError("Invalid PHPCoin Address");
                    return;
                }
                try {
                    this.burnAmount = this.burnAmount.trim();
                    this.phpcoinAddress = this.phpcoinAddress.trim();
                    let contract = new ethers.Contract(this.config.blockchains[this.selChain].contractAddress, this.abi, toRaw(this.signer))
                    let amount = ethers.parseUnits(`${this.burnAmount}`, this.config.blockchains[this.selChain].decimals);
                    this.burnTxStatus = 'pending';
                    console.log(amount, this.phpcoinAddress);
                    contract.burn(amount, this.phpcoinAddress).then(tx => {
                        this.tx = tx;
                        toRaw(this.tx).wait().then(() => {
                            this.burnTxStatus = 'success';
                            window.Swal.fire({
                                title: "Success!",
                                text: `Your transaction is created: ${tx.hash}`,
                                icon: "success"
                            });
                        }).catch(err => {
                            this.burnTxStatus = 'error';
                            this.showError(err);
                        });
                    }).catch(err => {
                        this.showError(err);
                        this.burnTxStatus = 'error';
                    });
                } catch (e) {
                    this.showError(e);
                }
            },
            getBurnConversions() {
                if(!this.signer) {
                    return;
                }
                this.api('getBurnConversions', {address: this.signer.address, blockchain: this.selChain}, list => {
                    console.log(list);
                    this.burnConversions = list;
                })
            },
            changedBlockChain() {
                console.log(this.selChain)
                localStorage.setItem('selChain', this.selChain)
                if(!this.selChain) {
                    return;
                }
                this.conversions = null;
                this.api('getTotalSupply', {blockchain: this.selChain}, data => {
                    this.supply = data;
                    if(this.side === 'mint') {
                        this.getConversions();
                    }
                    if(this.side === 'burn') {
                        this.getBurnConversions();
                    }
                })
            },
            shortenString(s, c=8) {
                if(!s) return null;
                return s.substr(0, c) +"..." + s.substr(-c);
            },
            reset() {
                if(!confirm('Reset form?')) {
                    return;
                }
                this.side = null;
                this.selChain = null;
                localStorage.removeItem('bridgeSide');
                localStorage.removeItem('selChain');
                localStorage.removeItem('connectedMetamask');
            }
        }
    }).mount('#app')
</script>
</body>
</html>