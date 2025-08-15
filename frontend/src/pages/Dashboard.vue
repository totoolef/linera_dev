<script setup lang="ts">
import BalanceCard from '@/components/BalanceCard.vue';
import PaymentsTable from '@/components/PaymentsTable.vue';
import TransfersTable from '@/components/TransfersTable.vue';
</script>

<template>
  <div class="max-w-6xl mx-auto p-6 space-y-6">
    <BalanceCard />
    <div class="grid md:grid-cols-2 gap-6">
      <PaymentsTable />
      <TransfersTable />
    </div>
  </div>
</template>
