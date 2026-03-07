<script setup>
import { ref } from "vue";
import { useMonitoringDevices } from "../composables/useMonitoringDevices";
import { usePolling } from "../composables/usePolling";
import MoldMonitoring from "../components/monitoring/MoldMonitoring.vue";
import TuftMonitoring from "../components/monitoring/TuftMonitoring.vue";
import BlisterMonitoring from "../components/monitoring/BlisterMonitoring.vue";
import ChartModal from "../components/monitoring/ChartModal.vue";
import ActionsModal from "../components/monitoring/ActionsModal.vue";
import AddActionModal from "../components/monitoring/AddActionModal.vue";
import { useViewState } from "../composables/useViewState";

// --- State (API Layer via Composables) ---
const { 
  moldDevices, 
  tuftDevices, 
  blisterDevices, 
  loading, 
  error, 
  refreshDevices 
} = useMonitoringDevices();

// --- Polling (10s interval) ---
usePolling(refreshDevices, 10000);

// --- Modals State ---
const isChartModalOpen = ref(false);
const isActionsModalOpen = ref(false);
const isAddActionModalOpen = ref(false);
const currentDevice = ref(null);
const { currentView } = useViewState();
const viewType = ref("mold");

// --- Interaction Handlers ---
const handleDeviceClick = (device) => {
  currentDevice.value = device;
  viewType.value = device.display_type || "mold";
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

</script>

<template>
  <template v-if="loading && (!moldDevices.length && !tuftDevices.length && !blisterDevices.length)">
    <div class="py-10 text-center text-gray-500">
      Loading devices...
    </div>
  </template>
  <template v-else-if="error">
    <div class="py-10 text-center text-red-500">
      {{ error }}
      <button @click="refreshDevices" class="ml-4 mt-2 px-4 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200">
        Retry
      </button>
    </div>
  </template>
  <template v-else>
    <MoldMonitoring v-if="currentView === 'mold'" :devices="moldDevices" @device-click="handleDeviceClick" />
    <TuftMonitoring v-if="currentView === 'tuft'" :devices="tuftDevices" @device-click="handleDeviceClick" />
    <BlisterMonitoring v-if="currentView === 'blister'" :devices="blisterDevices" @device-click="handleDeviceClick" />
  </template>

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
</template>
