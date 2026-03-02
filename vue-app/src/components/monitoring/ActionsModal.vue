<script setup>
import { ref } from "vue";
import Modal from "../ui/Modal.vue";

const props = defineProps({
  isOpen: { type: Boolean, required: true },
  device: { type: Object, default: () => ({}) },
});
const emit = defineEmits(["close", "add-plan"]);

const close = () => {
  emit("close");
};

// Mock action plans
const actionPlans = ref([]);
</script>

<template>
  <Modal :isOpen="isOpen" @close="close" maxWidth="max-w-6xl">
    <template #header>
      <h3 class="text-xl font-bold text-gray-800">
        {{ device?.device_id }} Actions
      </h3>
    </template>
    <template #body>
      <div class="space-y-2 mt-2 max-h-[60vh] overflow-auto">
        <template v-if="actionPlans.length > 0">
          <!-- Render action list -->
          <div
            v-for="(action, index) in actionPlans"
            :key="index"
            class="p-3 bg-white border rounded shadow-sm"
          >
            <div class="font-bold">#{{ action.id }} - {{ action.issue }}</div>
            <div class="text-sm text-gray-600 mt-1">
              Status: {{ action.status }}
            </div>
          </div>
        </template>
        <template v-else>
          <div class="text-center text-gray-500 py-8">
            No active actions found for this device.
          </div>
        </template>
      </div>
    </template>
    <template #footer>
      <div class="flex justify-end">
        <button
          @click="close"
          class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
        >
          Close
        </button>
      </div>
    </template>
  </Modal>
</template>
