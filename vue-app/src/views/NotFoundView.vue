<script setup>
import { useRouter } from "vue-router";
const router = useRouter();
</script>
<template>
  <div
    class="p-8 flex items-center justify-center min-vh-100 bg-gray-50 text-center flex-col"
  >
    <h1 class="text-9xl font-bold text-gray-200">404</h1>
    <h2 class="text-2xl font-bold text-gray-900 mt-4 mb-2">Page Not Found</h2>
    <p class="text-gray-500 mb-8 max-w-sm mx-auto">
      The link you followed may be broken, or the page may have been removed.
    </p>
    <button
      @click="router.push('/')"
      class="px-6 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition shadow-md"
    >
      Back to Dashboard
    </button>
  </div>
</template>
