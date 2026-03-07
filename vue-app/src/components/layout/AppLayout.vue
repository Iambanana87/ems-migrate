<script setup>
import { RouterView } from "vue-router";
import Header from "./Header.vue";

// Phase 3 AppLayout wraps the entire application.
// Contains Header and main content area (RouterView).
</script>

<template>
  <div class="ems-app-layout flex flex-col min-h-screen">
    <Header />
    <main class="flex-grow bg-[#f4f6f9] p-0">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
/* Any layout specific styles (most handled by Tailwind) */
</style>
