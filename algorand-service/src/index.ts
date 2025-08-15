import 'dotenv/config';
import express from 'express';
import algosdk from 'algosdk';
import { z } from 'zod';
import { SignJWT, importJWK } from 'jose';
import crypto from 'node:crypto';

function jsonSafe<T>(v: T): T {
  return JSON.parse(
    JSON.stringify(v, (_k, val) => (typeof val === 'bigint' ? val.toString() : val))
  );
}

const app = express();
app.use(express.json());

// --- Checks ENV ---
if (!process.env.ALGOD_URL) throw new Error('ALGOD_URL missing in .env');
if (process.env.ALGOD_TOKEN === undefined) {
  // peut être vide selon le provider, mais doit exister
  process.env.ALGOD_TOKEN = '';
}
if (!process.env.BANK_MNEMONIC) throw new Error('BANK_MNEMONIC missing in .env');

// --- SDK client & bank account ---
const client = new algosdk.Algodv2(
  process.env.ALGOD_TOKEN || '',
  process.env.ALGOD_URL,
  ''
);
const bankAccount = algosdk.mnemonicToSecretKey(process.env.BANK_MNEMONIC);
// Adresse banque en string (pas le type Address)
const BANK_ADDR = bankAccount.addr.toString();

// --- Healthcheck ---
app.get('/health', async (_req, res) => {
  try {
    const status = await client.status().do();
    const lastRound =
      typeof (status as any).lastRound === 'bigint'
        ? Number((status as any).lastRound)
        : (status as any).lastRound ?? (status as any)['last-round'];

    res.json({ ok: true, lastRound: Number(lastRound) });
  } catch (e: any) {
    res.status(500).json({ ok: false, error: e.message });
  }
});


/**
 * POST /asa/create
 * Crée l’ASA « Linera Credits » (1 unité = 1 micro-crédit)
 */
app.post('/asa/create', async (_req, res) => {
  try {
    const params = await client.getTransactionParams().do();

    const txn = algosdk.makeAssetCreateTxnWithSuggestedParamsFromObject({
      sender: BANK_ADDR,
      total: BigInt(10_000_000_000_000), // 1 unité = 1 micro-crédit
      decimals: 0,
      assetName: 'Linera Credits',
      unitName: 'LINμ',
      defaultFrozen: false,
      manager: BANK_ADDR,
      reserve: BANK_ADDR,
      freeze: BANK_ADDR,
      clawback: BANK_ADDR,
      suggestedParams: params,
    });

    // txId fiable calculé localement (évite les soucis de réponse réseau)
    const txId = txn.txID().toString();
    const signed = txn.signTxn(bankAccount.sk);

    const sendResp = await client.sendRawTransaction(signed).do();
    console.log('[ASA CREATE] txId(local)=', txId, ' sendResp=', sendResp);

    // Attente confortable (testnet peut être lent)
    try {
      await algosdk.waitForConfirmation(client, txId, 30);
    } catch {
      console.warn('[ASA CREATE] not confirmed after 30 rounds; fetching pending info anyway');
    }

    const ptx = await client.pendingTransactionInformation(txId).do();

    // Si rejetée, poolError est non-vide
    if ((ptx as any).poolError && (ptx as any).poolError !== '') {
      console.error('[ASA CREATE] poolError =', (ptx as any).poolError);
      return res.status(400).json({ error: (ptx as any).poolError, txId });
    }

    // assetIndex peut être bigint selon la lib → on normalise
    const rawAssetIndex = (ptx as any).assetIndex ?? (ptx as any)['asset-index'];
    if (rawAssetIndex === undefined || rawAssetIndex === null) {
      return res
        .status(504)
        .json({ error: 'ASA not confirmed yet', txId, pending: jsonSafe(ptx) }); // ← jsonSafe ici
    }
    const asaIdStr =
      typeof rawAssetIndex === 'bigint' ? rawAssetIndex.toString() : String(rawAssetIndex);

    console.log('[ASA CREATE] assetIndex =', asaIdStr);
    return res.json({ txId, asaId: asaIdStr }); // renvoyer une string => JSON-safe
  } catch (e: any) {
    console.error('[ASA CREATE] error =', e?.message || e);
    return res.status(500).json({ error: e.message });
  }
});


/**
 * POST /asa/transfer
 * body: { to: string, asaId: number, amount: string|number|bigint }
 */
app.post('/asa/transfer', async (req, res) => {
  const schema = z.object({
    to: z.string(),
    asaId: z.number(),
    amount: z.union([z.string(), z.number(), z.bigint()]),
  });

  try {
    const { to, asaId, amount } = schema.parse(req.body);
    const amt = typeof amount === 'bigint' ? amount : BigInt(amount);

    const params = await client.getTransactionParams().do();
    const txn = algosdk.makeAssetTransferTxnWithSuggestedParamsFromObject({
      sender: BANK_ADDR,
      receiver: to,       // v3
      amount: amt,        // v3
      assetIndex: asaId,
      suggestedParams: params,
    });

    const txId = txn.txID().toString(); // ✅ txId local
    const signed = txn.signTxn(bankAccount.sk);

    const sendResp = await client.sendRawTransaction(signed).do();
    console.log('[ASA TRANSFER] txId(local)=', txId, ' sendResp=', sendResp);

    await algosdk.waitForConfirmation(client, txId, 30);
    res.json({ txId });
  } catch (e: any) {
    console.error('[ASA TRANSFER] error =', e?.message || e);
    res.status(400).json({ error: e.message });
  }
});

/**
 * GET /tx/:txId
 * Retourne les infos de la tx (confirmedRound, poolError, assetIndex, etc.)
 */
app.get('/tx/:txId', async (req, res) => {
  try {
    const info = await client.pendingTransactionInformation(req.params.txId).do();
    res.json(jsonSafe(info)); // ← évite BigInt en JSON
  } catch (e: any) {
    res.status(404).json({ error: e.message });
  }
});

app.get('/bank/pubkey', async (_req, res) => {
  try {
    const pub = algosdk.decodeAddress(BANK_ADDR).publicKey;
    res.json({
      address: BANK_ADDR,
      publicKeyBase64: Buffer.from(pub).toString('base64'),
      publicKeyHex: Buffer.from(pub).toString('hex'),
      jwk: {
        kty: 'OKP',
        crv: 'Ed25519',
        x: Buffer.from(pub).toString('base64url'),
        kid: BANK_ADDR,
      },
    });
  } catch (e: any) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/jwt/issue', async (req, res) => {
  // payload attendu : { sub, method, path, bodyHash, ttlSeconds? }
  const schema = z.object({
    sub: z.string().min(1),
    method: z.string().min(1),
    path: z.string().min(1),
    bodyHash: z.string().min(10), // base64url sha256 du corps prévu
    ttlSeconds: z.number().int().min(10).max(300).optional().default(60),
  });

  try {
    const { sub, method, path, bodyHash, ttlSeconds } = schema.parse(req.body);

    const privateKey = await getEd25519PrivateKeyForJose(BANK_ADDR, bankAccount.sk);
    const now = Math.floor(Date.now() / 1000);
    const exp = now + ttlSeconds;
    const nonce = crypto.randomBytes(16).toString('base64url');

    const payload = {
      iss: BANK_ADDR, // émetteur = adresse Algorand banque
      sub,
      method,
      path,
      bodyHash, // hash du corps que le client va envoyer
      nonce,
      iat: now,
      exp,
      net: process.env.ALGORAND_NETWORK || 'testnet', // info réseau
    };

    const token = await new SignJWT(payload as any)
      .setProtectedHeader({ alg: 'EdDSA', kid: BANK_ADDR, typ: 'JWT' })
      .sign(privateKey);

    res.json({ token, iat: now, exp, kid: BANK_ADDR, alg: 'EdDSA' });
  } catch (e: any) {
    res.status(400).json({ error: e.message });
  }
});


// --- Start server ---
const PORT = Number(process.env.PORT || 8081);
app.listen(PORT, () => {
  console.log(`algorand-service up on :${PORT}`);
});

// base64url encode (Node 18+)
const b64u = (buf: Uint8Array | Buffer) => Buffer.from(buf).toString('base64url');

// JWK private pour Ed25519 (à partir de la seed + pubkey)
async function getEd25519PrivateKeyForJose(addr: string, sk64: Uint8Array) {
  // algosdk sk = 64 bytes (seed(32) + pub(32))
  const seed = sk64.slice(0, 32);
  const pub  = algosdk.decodeAddress(addr).publicKey;
  const jwk = {
    kty: 'OKP' as const,
    crv: 'Ed25519',
    d: b64u(seed),
    x: b64u(pub),
    kid: addr,
  };
  return importJWK(jwk, 'EdDSA');
}
