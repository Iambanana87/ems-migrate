<script setup>
// Skeleton matches tpl-skel-card perfectly
</script>

<template>
  <div
    class="sk-wrapper sk-card skeleton border rounded-lg shadow-sm bg-white overflow-hidden min-h-[180px] flex flex-col"
  >
    <div class="skeleton sk-header h-10 w-full mb-2"></div>
    <div class="p-2 space-y-3 flex-grow">
      <div class="flex mx-auto justify-center space-x-4 mb-4">
        <div class="skeleton sk-line lg h-8 w-16 !bg-gray-300 rounded"></div>
        <div class="border-l border-gray-200"></div>
        <div class="skeleton sk-line h-8 w-16 !bg-gray-300 rounded"></div>
      </div>

      <div class="grid grid-cols-3 gap-2 px-2 mt-4">
        <div class="skeleton sk-line h-4 rounded"></div>
        <div class="skeleton sk-line h-4 rounded"></div>
        <div class="skeleton sk-line h-4 rounded"></div>
      </div>
      <div class="grid grid-cols-3 gap-2 px-2 pb-2">
        <div class="skeleton sk-line h-4 rounded"></div>
        <div class="skeleton sk-line h-4 rounded"></div>
        <div class="skeleton sk-line h-4 rounded"></div>
      </div>
    </div>
    <div class="skeleton sk-footer h-6 w-full mt-auto"></div>
  </div>
</template>

<style scoped>
/* Inherits .skeleton array from global.css */
.sk-header {
  background: var(--sk-base);
}
.sk-footer {
  background: var(--sk-base);
}
.sk-line {
  background: var(--sk-base);
}
.sk-line.lg {
  background: #d1d5db;
}
</style>
