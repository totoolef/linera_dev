const algosdk = require('algosdk');
const a = algosdk.generateAccount();
const m = algosdk.secretKeyToMnemonic(a.sk);
console.log(JSON.stringify({
  address: a.addr.toString(), // ← conversion en chaîne lisible
  mnemonic: m
}, null, 2));
