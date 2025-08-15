export type CursorPage<T> = { data: T[]; next: string | null };

const BASE = import.meta.env.VITE_API_BASE || 'http://localhost:8000/api';
const TOKEN = import.meta.env.VITE_DEV_TOKEN || 'TEST_TOKEN'; // à adapter

async function req<T>(path: string, init: RequestInit = {}): Promise<T> {
  const r = await fetch(`${BASE}${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${TOKEN}`,
      ...(init.headers || {}),
    },
  });
  if (!r.ok) throw new Error(`${r.status} ${await r.text()}`);
  return r.json();
}

export const api = {
  me: {
    credits: () => req<{ balance_micro: number }>('/me/credits'),
  },
  payments: {
    list: (limit = 20, cursor?: string) =>
      req<CursorPage<{
        id:number; provider:string; amount_eur:string; amount_micro:string;
        status:string; external_id:string; created_at:string;
      }>>(`/payments?limit=${limit}${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`),
  },
  transfers: {
    list: (limit = 20, cursor?: string) =>
      req<CursorPage<{
        id:number; direction:'in'|'out'; amount_units:string;
        tx_id:string|null; status:string; reason:string|null; created_at:string;
      }>>(`/onchain-transfers?limit=${limit}${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`),
  },
};
