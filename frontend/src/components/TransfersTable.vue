<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { api } from '@/lib/api';
import { dateTime, formatMicro } from '@/lib/format';

type Row = {
  id:number; direction:'in'|'out'; amount_units:string;
  tx_id:string|null; status:string; reason:string|null; created_at:string;
};

const rows = ref<Row[]>([]);
const next = ref<string|null>(null);
const loading = ref(false);
const error = ref('');

function statusClass(s: string): string {
  if (s === 'confirmed' || s === 'completed') return 'bg-green-100 text-green-700';
  if (s === 'pending') return 'bg-gray-100 text-gray-700';
  if (s === 'failed') return 'bg-red-100 text-red-700';
  return 'bg-gray-100 text-gray-700';
}

async function load(cursor?: string) {
  try {
    loading.value = true; error.value = '';
    const r = await api.transfers.list(20, cursor);
    rows.value.push(...r.data);
    next.value = r.next;
  } catch (e:any) {
    error.value = e.message || 'Error';
  } finally {
    loading.value = false;
  }
}

onMounted(() => load());
</script>

<template>
  <div class="rounded-2xl p-4 shadow border bg-white">
    <div class="flex items-center justify-between mb-3">
      <h2 class="font-semibold">Transferts on‑chain</h2>
    </div>
    <div v-if="error" class="text-sm text-red-600 mb-2">{{ error }}</div>

    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-gray-500">
          <th class="py-2">Date</th>
          <th>Direction</th>
          <th>Montant (micro)</th>
          <th>TX</th>
          <th>Statut</th>
          <th>Raison</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in rows" :key="r.id" class="border-t">
          <td class="py-2">{{ dateTime(r.created_at) }}</td>
          <td>
            <span :class="r.direction==='in' ? 'text-green-600' : 'text-orange-600'">
              {{ r.direction }}
            </span>
          </td>
          <td>{{ formatMicro(r.amount_units) }}</td>
          <td class="truncate max-w-[280px]">
            <a v-if="r.tx_id"
               :href="`https://testnet.explorer.perawallet.app/tx/${r.tx_id}`"
               target="_blank"
               class="text-blue-600 underline">
              {{ r.tx_id }}
            </a>
            <span v-else class="text-gray-400">—</span>
          </td>
          <td>
            <span class="px-2 py-0.5 rounded text-xs font-medium" :class="statusClass(r.status)">
              {{ r.status }}
            </span>
          </td>
          <td>{{ r.reason || '—' }}</td>
        </tr>
      </tbody>
    </table>

    <div class="mt-3">
      <button v-if="next" @click="load(next!)" class="px-3 py-1 rounded border">Charger plus</button>
      <span v-else class="text-gray-500 text-sm">Fin de liste</span>
    </div>
  </div>
</template>
