<script setup>
import { useRouter } from "vue-router";
const router = useRouter();
</script>
<template>
  <div
    class="p-8 flex items-center justify-center min-h-[calc(100vh-64px)] bg-gray-50"
  >
    <div
      class="text-center max-w-md w-full bg-white p-8 rounded-2xl shadow-sm border border-gray-100"
    >
      <div
        class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4"
      >
        <svg
          width="24"
          height="24"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
      </div>
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Login</h1>
      <p class="text-gray-500 mb-6">This page has not been migrated yet.</p>
      <button
        @click="router.push('/')"
        class="w-full py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition"
      >
        Return to Home
      </button>
    </div>
  </div>
</template>
