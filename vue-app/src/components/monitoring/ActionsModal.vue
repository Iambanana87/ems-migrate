<script setup>
import { ref, watch } from "vue";
import api from "../../services/api";
import Modal from "../ui/Modal.vue";

const props = defineProps({
  isOpen: { type: Boolean, required: true },
  device: { type: Object, default: () => ({}) },
});
const emit = defineEmits(["close", "add-plan"]);

const close = () => {
  emit("close");
};

const actionPlans = ref([]);
const loading = ref(false);
const error = ref(null);

watch(
  () => props.isOpen,
  async (isOpen) => {
    if (isOpen && props.device?.device_id) {
      loading.value = true;
      error.value = null;
      try {
        const res = await api.get("", {
          params: {
            c: "DeviceAction",
            m: "listV2",
            device_id: props.device.device_id,
          },
        });
        actionPlans.value = res.data?.data || res.data || [];
      } catch (err) {
        console.error("Failed to fetch actions:", err);
        error.value = err.message || "Failed to load actions.";
        actionPlans.value = [];
      } finally {
        loading.value = false;
      }
    } else {
      actionPlans.value = [];
    }
  }
);
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
        <div v-if="loading" class="flex justify-center p-4">
          <div class="animate-spin w-8 h-8 set border-b-2 border-blue-600 rounded-full"></div>
        </div>
        
        <div v-else-if="error" class="bg-red-100 text-red-700 px-4 py-3 rounded mb-4">
          {{ error }}
        </div>

        <template v-else-if="actionPlans.length > 0">
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
