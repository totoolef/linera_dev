import { defineStore } from 'pinia';
import { api } from '../lib/api';

export const useBalanceStore = defineStore('balance', {
  state: () => ({ micro: 0, loading: false, error: '' as string|'' }),
  actions: {
    async fetch() {
      try {
        this.loading = true; this.error = '';
        const r = await api.me.credits();
        this.micro = Number(r.balance_micro || 0);
      } catch (e:any) {
        this.error = e.message || 'Error';
      } finally {
        this.loading = false;
      }
    }
  },
});
