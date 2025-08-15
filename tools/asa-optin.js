// tools/asa-optin.js
const algosdk = require('algosdk');

(async () => {
  try {
    const ALGOD_URL   = process.env.ALGOD_URL || 'https://testnet-api.algonode.cloud';
    const ALGOD_TOKEN = process.env.ALGOD_TOKEN || '';
    const ASA_ID      = Number(process.env.ASA_ID);
    const USER_MNEMO  = process.env.USER_MNEMONIC;

    if (!ASA_ID) throw new Error('ASA_ID manquant (export ASA_ID=...)');
    if (!USER_MNEMO) throw new Error('USER_MNEMONIC manquant (export USER_MNEMONIC="...25 mots...")');

    const client = new algosdk.Algodv2(ALGOD_TOKEN, ALGOD_URL, '');
    const user   = algosdk.mnemonicToSecretKey(USER_MNEMO);
    const addr   = user.addr.toString(); // ✅ adresse lisible

    console.log('[OPTIN] address =', addr);

    // 0) Vérifie le solde (min. ~0.2 ALGO recommandé)
    const before = await client.accountInformation(addr).do();
    const balance = before.amount || 0;
    console.log('[OPTIN] balance microAlgos =', balance);
    if (balance < 200_000) {
      throw new Error("Solde insuffisant (< 0.2 ALGO). Alimente l'adresse via le faucet TestNet, puis réessaie.");
    }

    // 1) Opt-in (asset transfer 0 de soi vers soi)
    const params = await client.getTransactionParams().do();
    const txn = algosdk.makeAssetTransferTxnWithSuggestedParamsFromObject({
      sender: addr,
      receiver: addr,
      amount: 0,
      assetIndex: ASA_ID,
      suggestedParams: params,
    });

    const txId  = txn.txID().toString();
    const signed = txn.signTxn(user.sk);
    await client.sendRawTransaction(signed).do();
    console.log('[OPTIN] txId =', txId, '(waiting confirmation) ...');

    await algosdk.waitForConfirmation(client, txId, 30);

    // 2) Poll holdings jusqu’à apparition (max ~30s)
    const deadline = Date.now() + 30_000;
    let holding = null;
    while (Date.now() < deadline) {
      const acc = await client.accountInformation(addr).do();
      holding = (acc.assets || []).find(a => a['asset-id'] === ASA_ID);
      if (holding) break;
      await new Promise(r => setTimeout(r, 2000));
    }

    if (!holding) {
      throw new Error('Opt-in confirmé mais holding pas encore visible. Réessaie dans 10–20s.');
    }

    console.log(JSON.stringify({ ok: true, txId, assetId: ASA_ID, address: addr }, null, 2));
  } catch (e) {
    console.error(JSON.stringify({ ok: false, error: e.message }, null, 2));
    process.exit(1);
  }
})();
