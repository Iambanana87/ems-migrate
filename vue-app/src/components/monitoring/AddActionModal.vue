<script setup>
import { ref, reactive } from "vue";
import api from "../../services/api";
import Modal from "../ui/Modal.vue";

const props = defineProps({
  isOpen: { type: Boolean, required: true },
  device: { type: Object, default: () => ({}) },
});
const emit = defineEmits(["close", "submit"]);

const close = () => {
  emit("close");
};

const form = reactive({
  issueId: "Auto-generated",
  issue: "",
  issueType: "maintenance",
  actualCavity: "",
  actualEff: "",
  actualCycle: "",
  target: "",
  moldCavity: "",
  effReq: "",
  upperLimit: "",
  lowerLimit: "",
});

const loading = ref(false);
const error = ref(null);

const submitForm = async () => {
  loading.value = true;
  error.value = null;
  try {
    await api.post("", {
      c: "DeviceAction",
      m: "store",
      device_id: props.device?.device_id,
      ...form
    });
    emit("submit", { ...form, deviceId: props.device?.device_id });
    close();
  } catch (err) {
    console.error("Failed to create action:", err);
    error.value = err.message || "Failed to create action.";
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <Modal :isOpen="isOpen" @close="close" maxWidth="max-w-5xl">
    <template #header>
      <h3 class="text-2xl font-semibold">
        Create new Action for {{ device?.device_id }}
      </h3>
    </template>
    <template #body>
      <form
        @submit.prevent="submitForm"
        class="space-y-5 mt-2 max-h-[70vh] px-1 overflow-x-hidden"
      >
        <div v-if="error" class="bg-red-100 text-red-700 px-4 py-3 rounded mb-4 text-sm">
          {{ error }}
        </div>

        <!-- Exact form port from index.html 444-551 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Issue ID</label>
            <input
              v-model="form.issueId"
              class="w-full border rounded px-2 py-1 bg-gray-100"
              readonly
            />
          </div>
          <div class="col-span-2">
            <label class="block text-sm text-gray-600 mb-1"
              >Issue Description <span class="text-red-500">*</span></label
            >
            <input
              v-model="form.issue"
              class="w-full border rounded px-2 py-1"
              required
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Issue Type</label>
            <select
              v-model="form.issueType"
              class="w-full border rounded px-2 py-1 bg-white"
            >
              <option value="maintenance">Maintenance</option>
              <option value="quality">Quality</option>
              <option value="operator">Operator Error</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Actual Cavity</label
            ><input
              v-model="form.actualCavity"
              class="w-full border rounded px-2 py-1"
            />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1"
              >Actual Efficiency</label
            ><input
              v-model="form.actualEff"
              class="w-full border rounded px-2 py-1"
            />
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Actual Cycle</label
            ><input
              v-model="form.actualCycle"
              class="w-full border rounded px-2 py-1"
            />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Target</label
            ><input
              v-model="form.target"
              class="w-full border rounded px-2 py-1"
            />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Mold Cavity</label
            ><input
              v-model="form.moldCavity"
              class="w-full border rounded px-2 py-1"
            />
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1"
              >Efficiency Required</label
            ><input
              v-model="form.effReq"
              class="w-full border rounded px-2 py-1"
            />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Upper Limit</label
            ><input
              v-model="form.upperLimit"
              class="w-full border rounded px-2 py-1"
            />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Lower Limit</label
            ><input
              v-model="form.lowerLimit"
              class="w-full border rounded px-2 py-1"
            />
          </div>
        </div>

        <!-- Action Plans table empty placeholder -->
        <div class="border rounded mt-4">
          <div
            class="flex items-center justify-between px-3 py-2 bg-gray-50 border-b"
          >
            <h4 class="font-semibold">Action Plans</h4>
            <button
              type="button"
              class="px-3 py-1 text-sm rounded bg-blue-600 text-white"
            >
              + Add Plan
            </button>
          </div>
          <div class="p-4 text-center text-sm text-gray-500">
            No action plans added yet.
          </div>
        </div>
      </form>
    </template>
    <template #footer>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          @click="close"
          class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
        >
          Cancel
        </button>
        <button
          type="button"
          @click="submitForm"
          :disabled="loading"
          class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50 flex items-center"
        >
          <span v-if="loading" class="mr-2">...</span>
          Create Action
        </button>
      </div>
    </template>
  </Modal>
</template>
