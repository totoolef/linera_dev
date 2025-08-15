<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { api } from '@/lib/api';
import { dateTime } from '@/lib/format';

type Row = {
  id: number;
  provider: string;
  amount_eur: string;
  amount_micro: string;
  status: string;
  external_id: string;
  created_at: string;
};

const rows = ref<Row[]>([]);
const next = ref<string | null>(null);
const loading = ref(false);
const error = ref('');

async function load(cursor?: string) {
  try {
    loading.value = true;
    error.value = '';
    const r = await api.payments.list(20, cursor);
    rows.value.push(...r.data);
    next.value = r.next;
  } catch (e: any) {
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
      <h2 class="font-semibold">Paiements</h2>
    </div>

    <div v-if="error" class="text-sm text-red-600 mb-2">{{ error }}</div>

    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-gray-500">
          <th class="py-2">Date</th>
          <th>Provider</th>
          <th>Montant (€)</th>
          <th>Micro</th>
          <th>Statut</th>
          <th>External ID</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in rows" :key="r.id" class="border-t">
          <td class="py-2">{{ dateTime(r.created_at) }}</td>
          <td>{{ r.provider }}</td>
          <td>{{ Number(r.amount_eur).toFixed(2) }}</td>
          <td>{{ r.amount_micro }}</td>
          <td>{{ r.status }}</td>
          <td class="truncate max-w-[260px]" :title="r.external_id">
            {{ r.external_id }}
          </td>
        </tr>
      </tbody>
    </table>

    <div class="mt-3">
      <button v-if="next" @click="load(next!)" class="px-3 py-1 rounded border">
        Charger plus
      </button>
      <span v-else class="text-gray-500 text-sm">Fin de liste</span>
    </div>
  </div>
</template>
