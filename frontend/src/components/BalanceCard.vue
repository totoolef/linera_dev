<script setup lang="ts">
import { onMounted } from 'vue';
import { useBalanceStore } from '@/stores/balance';
import { microToEuro, formatMicro } from '@/lib/format';

const store = useBalanceStore();
onMounted(() => store.fetch());
</script>

<template>
  <div class="rounded-2xl p-4 shadow border bg-white">
    <div class="text-sm text-gray-500">Solde</div>

    <div class="mt-1" v-if="!store.loading">
      <div class="text-3xl font-semibold">
        {{ microToEuro(store.micro) }}
      </div>
      <div class="text-sm text-gray-500 mt-1">
        {{ formatMicro(store.micro) }} micro‑crédits
      </div>
    </div>

    <div v-else class="text-gray-400">Chargement...</div>
    <div v-if="store.error" class="text-sm text-red-600 mt-2">{{ store.error }}</div>
  </div>
</template>
