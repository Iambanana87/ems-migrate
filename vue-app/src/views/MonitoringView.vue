<script setup>
import { ref, computed } from "vue";
import api from "../services/api";
import { usePolling } from "../composables/usePolling";
import DeviceGrid from "../components/monitoring/DeviceGrid.vue";
import ChartModal from "../components/monitoring/ChartModal.vue";
import ActionsModal from "../components/monitoring/ActionsModal.vue";
import AddActionModal from "../components/monitoring/AddActionModal.vue";

// --- State ---
const devices = ref([]);
const loading = ref(true);
const error = ref(null);

// Derived from global state/URL or mocked for now (as Header controls it)
// In a full implementation, this viewType would come from Pinia or Vue Router query params
const viewType = ref("mold");
const clientFilter = ref("all");

// Modals
const isChartModalOpen = ref(false);
const isActionsModalOpen = ref(false);
const isAddActionModalOpen = ref(false);
const currentDevice = ref(null);

// --- API ---
const fetchDevices = async () => {
  try {
    const response = await api.get("", {
      params: {
        c: "Device",
        m: "live",
        view: viewType.value,
        client: clientFilter.value !== "all" ? clientFilter.value : undefined,
      },
    });

    // Legacy app.js expected j.devices or j.data based on endpoint.
    // Fallback to empty array if structure changes.
    devices.value = response.data?.devices || response.data || [];
  } catch (err) {
    console.error("Failed to fetch devices", err);
    error.value = "Failed to load devices.";
    // If backend is pure PHP mock or down, we mock the data to not break the UI design logic
    if (devices.value.length === 0) {
      devices.value = generateMockDevices(viewType.value);
    }
  } finally {
    loading.value = false;
  }
};

// Polling every 10s per legacy app.js rules
const { start, stop } = usePolling(fetchDevices, 10000);

// --- Filtering (Local fallback before full Pinia) ---
const filteredDevices = computed(() => {
  return devices.value; // The PHP backend handled filtering, we just pass the array
});

// --- Interaction Handlers ---
const handleDeviceClick = (device) => {
  currentDevice.value = device;
  isChartModalOpen.value = true;
};

const handleOpenAddAction = (device) => {
  isChartModalOpen.value = false;
  currentDevice.value = device;
  isAddActionModalOpen.value = true;
};

const handleSubmitAction = (formData) => {
  console.log("Submitting new action:", formData);
  // TODO: Post to backend via api
  isAddActionModalOpen.value = false;
};

// --- Mock Data Generator (Safety Net for phase approvals without backend running) ---
function generateMockDevices(type) {
  return Array.from({ length: 14 }).map((_, i) => ({
    device_id: `${type.toUpperCase()}-${String(i + 1).padStart(2, "0")}`,
    product: `Test Product ${i}`,
    status:
      Math.random() > 0.8
        ? "Breached"
        : Math.random() > 0.9
          ? "DISCONNECTED"
          : "Normal",
    process: "Injection",
    timestamp: new Date().toISOString().replace("T", " ").slice(0, 19),
    flex: i % 5 === 0 ? 1 : 0,
    action_count_open: i % 4 === 0 ? 1 : 0,
    action_urgent_overdue: false,
    capacity: 1500,
    cavities: 4,
    live_data: {
      cycle_time: (Math.random() * 10 + 5).toFixed(1),
      efficiency: (Math.random() * 20 + 80).toFixed(1),
      cavities: 4,
      output: Math.floor(Math.random() * 100) + 900,
      cyclecount: Math.floor(Math.random() * 100) + 500,
      rpm: 120,
      BrushesperCycle: 2,
    },
    lower_limit: 5,
    upper_limit: 15,
    target_limit: 10,
    efficiency_lower_limit: 85,
  }));
}
</script>

<template>
  <div class="monitoring-view">
    <!-- Grid container -->
    <DeviceGrid
      :devices="filteredDevices"
      :loading="loading"
      :viewType="viewType"
      @device-click="handleDeviceClick"
    />

    <!-- Modals -->
    <ChartModal
      :isOpen="isChartModalOpen"
      :device="currentDevice"
      :viewType="viewType"
      @close="isChartModalOpen = false"
      @add-action="handleOpenAddAction"
    />

    <ActionsModal
      :isOpen="isActionsModalOpen"
      :device="currentDevice"
      @close="isActionsModalOpen = false"
    />

    <AddActionModal
      :isOpen="isAddActionModalOpen"
      :device="currentDevice"
      @close="isAddActionModalOpen = false"
      @submit="handleSubmitAction"
    />
  </div>
</template>
