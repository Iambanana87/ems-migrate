<script setup>
import { ref } from "vue";

const isAutoSwitchActive = ref(false);

const toggle = () => {
  // Logic for Auto-switch functionality will be tied to Pinia/Composables in Phase 4
};
</script>

<template>
  <div
    class="flex items-center items-center bg-gray-100 rounded-full border border-gray-200"
    title="Auto Switch Views"
  >
    <label class="relative inline-flex items-center cursor-pointer">
      <input
        type="checkbox"
        v-model="isAutoSwitchActive"
        @change="toggle"
        class="sr-only peer"
      />
      <div
        class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-blue-500 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"
      ></div>
    </label>
  </div>
</template>

<style scoped>
/* Scoped overrides if needed, Tailwind handles the base look */
</style>
