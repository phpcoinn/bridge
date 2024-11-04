import axios from 'https://cdn.jsdelivr.net/npm/@bundled-es-modules/axios@0.27.2/axios.min.js'

let account;


export default {

    account: null,
    balance: null,

    init(config, cb) {
        this.config = config;
        account = localStorage.getItem('account');
        if(account) {
            account = JSON.parse(account);
            if(account) {
                this.account = account;
                this.getBalance((balance, err)=>{
                    this.balance = balance;
                    cb(account, null);
                })
                return;
            }
        }
        this.account = account;
        cb(account, null);
    },

    reload() {
        this.getBalance((balance, err)=>{
            this.balance = balance;
        })
    },

    logout(cb) {
        localStorage.removeItem('account');
        this.account = null;
        cb();
    },

    getBalance(cb) {
        let url = `${this.config.node}/api.php?q=getBalance&address=${account.address}`;
        axios.get(url).then(response => {
            cb(response.data.data, null);
        }).catch(err => {
            cb(null, err);
        })
    },

    auth(cb) {

        let request_code = window.generateRandomString(10);
        let redirect = encodeURIComponent(document.location.href);
        let url = `${this.config.node}/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/gateway/auth.php?app=${this.config.appName}&request_code=${request_code}&redirect=${redirect}`

        axios.get(url).then(res => {
            this.createAuthContainer();
            let content = res.data;
            let div = document.createElement('div');
            div.innerHTML = content;
            let card = div.getElementsByClassName('card')[0]
            this.authContainer.appendChild(card);
            this._attachStyleSheets();
            this.authContainer.style.visibility = 'hidden';
            window.document.body.appendChild(this.authContainer);

            this._handleBack();
            this._hanldlePrivateKey();

            let btn = document.getElementById('btn-create-address');
            btn.remove();

            window.onSubmit = (event) => {
                this.login(cb);
                event.preventDefault();
            }
        })
    },

    login(cb) {
        let privateKey = document.getElementById('private_key').value;
        let nonce = window.generateRandomString(10);
        let sig;
        try {
            sig = sign(this.config.chainId+nonce, privateKey)
        } catch (e) {
            e = 'Invalid private key';
            cb(null, e);
            return;
        }
        let publicKey = get_public_key(privateKey)
        let url = `${this.config.node}/api.php?q=authenticate&public_key=${publicKey}&nonce=${nonce}&signature=${sig}`
        let remember_private_key = document.getElementById('rememberPrivateKey').checked;
        axios.get(url).then(res => {
            if(!res) {
                cb(null, 'Empty response from API server');
                return;
            }
            let data = res.data;
            if(!data) {
                cb(null, 'No response from API server');
                return;
            }
            if(data.status === 'error') {
                cb(null, 'Error response from API server: ' + data.data);
                console.error(data.data);
                return;
            }
            account = data.data;
            if(remember_private_key) {
                account.private_key = privateKey;
            }
            localStorage.setItem('account', JSON.stringify(account))
            this.account = account;
            this.authContainer.remove();
            this.reload()
            cb(this.account, null);
        }).catch(err => {
            console.error(err);
            cb(null, err);
        })
    },

    createAuthContainer() {
        this.authContainer = window.document.getElementById('phpcoin-auth-container');
        if(this.authContainer) this.authContainer.remove();
        this.authContainer = document.createElement('div');
        this.authContainer.id= 'phpcoin-auth-container';
    },

    _createStylesheetLink(url) {
        let link = document.createElement('link');
        link.setAttribute('rel', 'stylesheet');
        link.setAttribute('type', 'text/css');
        link.href =url;
        link.onload =  () => {
            this.links.loaded++;
            if(this.links.requested === this.links.loaded) {
                this._allLinksLoaded();
            }
        }
        this.links.requested++;
        return link;
    },

    _attachStyleSheets() {
        this.links = {
            requested: 0,
            loaded: 0
        }
        this.authContainer.appendChild(this._createStylesheetLink( 'https://node1.phpcoin.net/apps/common/css/icons.min.css'));
        this.authContainer.appendChild(this._createStylesheetLink( 'https://node1.phpcoin.net/apps/common/css/bootstrap.min.css'));
        this.authContainer.appendChild(this._createStylesheetLink( 'https://node1.phpcoin.net/apps/common/css/app.min.css'));
    },

    _allLinksLoaded () {
        console.log("_allLinksLoaded")
        this.authContainer.style.visibility = 'visible';
    },

    signTx(tx, cb) {

        if(!tx.fee) tx.fee=0;
        if(!tx.dst) tx.dst="";
        if(!tx.date) tx.date = Math.floor(Date.now()/1000)

        let txString = btoa(JSON.stringify(tx));
        let redirect = encodeURIComponent(document.location.href);

        let url = `${this.config.node}/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/gateway/approve.php?app=${this.config.appName}&tx=${txString}&redirect=${redirect}`

        axios.get(url).then(res => {
            this.createAuthContainer();
            let content = res.data;
            let div = document.createElement('div');
            div.innerHTML = content;
            let card = div.getElementsByClassName('card')[0]
            this.authContainer.appendChild(card);

            this._attachStyleSheets();

            this.authContainer.style.visibility = 'hidden';
            window.document.body.appendChild(this.authContainer);

            window.toggleDetails = () => {
                let txInfo = document.getElementById('tx_info');
                if (txInfo.style.display === 'none') {
                    txInfo.style.display = 'block';
                } else {
                    txInfo.style.display = 'none';
                }
            }

            this._handleBack();
            this._hanldlePrivateKey();

            window.onSubmit = (event) => {
                event.preventDefault();
                tx.public_key = this.account.public_key;
                let tx_base = Number(tx.val).toFixed(8)
                tx_base+='-'+Number(tx.fee).toFixed(8)
                tx_base+='-'+tx.dst
                tx_base+='-'+tx.msg
                tx_base+='-'+tx.type
                tx_base+='-'+tx.public_key
                tx_base+='-'+tx.date
                let privateKey = document.getElementById('private_key').value;
                let sig
                try {
                    sig = window.sign(this.config.chainId+tx_base, privateKey);
                } catch (e) {
                    cb(null, "Error signing transaction");
                    return;
                }
                tx.signature = sig;
                axios.post(`${this.config.node}/api.php?q=sendTransactionJson`, tx).then(response => {
                    let res = response.data;
                    if(res.status === 'ok') {
                        this.authContainer.remove();
                        cb(res.data, null);
                    } else {
                        cb(null, res.data);
                    }
                }).catch(err => {
                    console.log(err);
                    cb(null, err);
                })
            }
        })
    },

    _hanldlePrivateKey() {
        let passwordAddon = window.document.getElementById('password-addon');
        let icon = passwordAddon.children[0];
        let input = passwordAddon.previousElementSibling;
        passwordAddon.addEventListener('click', (el) => {
            if(icon.className === 'mdi mdi-eye-outline') {
                icon.className = 'mdi mdi-eye-off-outline';
                input.type = 'text';
            } else {
                icon.className = 'mdi mdi-eye-outline';
                input.type = 'password';
            }
        })
        if(this.account && this.account.private_key) {
            input.value = this.account.private_key;
        }
    },

    _handleBack() {
        let a = this.authContainer.getElementsByClassName('card-footer')[0].children[0];
        a.addEventListener('click', (e) => {
            e.preventDefault();
            this.authContainer.remove();
        })
    }

}