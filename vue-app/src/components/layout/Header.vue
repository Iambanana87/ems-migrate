<script setup>
import { onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { useViewState } from "../../composables/useViewState";
import { useMonitoringDevices } from "../../composables/useMonitoringDevices";
import api from "../../services/api";
import AutoSwitchToggle from "../ui/AutoSwitchToggle.vue";
import DeviceStatsBadge from "../ui/DeviceStatsBadge.vue";
import DropdownMenu from "./DropdownMenu.vue";
import { ref, computed, watch } from 'vue';

const router = useRouter();
const { currentView, setView } = useViewState();
const { moldStats, tuftStats, blisterStats } = useMonitoringDevices();

const stats = computed(() => {
  if (currentView.value === 'mold') return moldStats.value;
  if (currentView.value === 'tuft') return tuftStats.value;
  if (currentView.value === 'blister') return blisterStats.value;
  return { total: 0, connected: 0, breached: 0, disconnected: 0, flexible: 0, action: 0 };
});

const connectedDevices = computed(() => stats.value.connected);
const breachedDevices = computed(() => stats.value.breached);
const disconnectedDevices = computed(() => stats.value.disconnected);
const totalDevices = computed(() => stats.value.total);
const flexibleDevices = computed(() => stats.value.flexible);
const actionOpen = computed(() => stats.value.action);

// Removed fetchStats and intervalId as we now use shared reactive state
onMounted(() => {
  // fetchStats(); // No longer needed
});

onUnmounted(() => {
  // if (intervalId) clearInterval(intervalId); // No longer needed
});

</script>

<template>
  <header class="page-header">
    <div class="logo-container">
      <router-link to="/" class="flex items-center space-x-3">
        <img src="/image/icon2.png" alt="Logo" style="height: 35px;">
        <img src="/image/ems_.png" alt="EMS Logo" style="height: 32px; width: auto;">
      </router-link>
      <div class="flex items-center bg-gray-100 rounded-full border border-gray-200">
        <AutoSwitchToggle />
      </div>
      <button @click="router.push('/dashboard')" class="btn">Dashboard</button>
      <span id="dashboard-title" class="text-lg text-gray-600 font-semibold">{{ router.currentRoute.value.name === "dashboard" ? "EMS Dashboard" : "EMS Monitoring" }}</span>
    </div>

    <div v-if="router.currentRoute.value.name === 'home'" class="flex items-center gap-4">
      <div class="relative">
        <select id="client-filter" class="appearance-none bg-white border border-gray-300 text-gray-700 py-1 px-3 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500 text-sm font-semibold">
          <option value="all">All Clients</option>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
          <i class="fas fa-filter text-xs"></i>
        </div>
      </div>

      <a href="#" class="page-nav-link" @click.prevent="setView('mold')" :class="{ 'active': currentView === 'mold' }">
        <i class="fas fa-chart-line"></i><span>Mold</span>
      </a>
      <a href="#" class="page-nav-link" @click.prevent="setView('tuft')" :class="{ 'active': currentView === 'tuft' }">
        <i class="fas fa-chart-line"></i><span>Tufting</span>
      </a>
      <a href="#" class="page-nav-link" @click.prevent="setView('blister')" :class="{ 'active': currentView === 'blister' }">
        <i class="fas fa-chart-line"></i><span>Blister</span>
      </a>
    </div>

    <div class="flex items-center space-x-4">
      <div v-if="router.currentRoute.value.name === 'home'" class="flex items-center space-x-2">
        <div class="space-y-1">
          <div class="flex justify-between items-center text-green-600 bg-green-100 px-3 py-1 rounded-full border border-green-700 w-26" title="Devices online">
            <span class="font-medium text-xs">Online:</span>
            <span class="font-medium text-xs">{{ connectedDevices }}</span>
          </div>
          <div class="flex justify-between items-center text-red-600 bg-red-100 px-3 py-1 rounded-full border border-red-400 w-24" title="Devices breached thresholds">
            <span class="font-medium text-xs">Warning:</span>
            <span class="font-medium text-xs">{{ breachedDevices }}</span>
          </div>
        </div>

        <div class="space-y-1">
          <div class="flex justify-between items-center text-gray-600 bg-gray-100 px-3 py-1 rounded-full border border-gray-400 w-30" title="Devices disconnected">
            <span class="font-medium text-xs">Offline:</span>
            <span class="font-medium text-xs">{{ disconnectedDevices }}</span>
          </div>
          <div class="flex justify-between items-center text-blue-600 bg-blue-100 px-3 py-1 rounded-full border border-blue-400 w-30" title="Devices total">
            <span class="font-medium text-xs">Total:</span>
            <span class="font-medium text-xs">{{ totalDevices }}</span>
          </div>
        </div>

        <div class="space-y-1">
          <div class="flex justify-between items-center text-purple-600 bg-purple-100 px-3 py-1 rounded-full border border-purple-400 w-32" title="Flexible Devices">
            <span class="font-medium text-xs">Flexible:</span>
            <span class="font-medium text-xs">{{ flexibleDevices }}</span>
          </div>
          <div class="flex justify-between items-center text-orange-600 bg-orange-100 px-3 py-1 rounded-full border border-orange-400 w-32" title="Actions Pending">
            <span class="font-medium text-xs">Action:</span>
            <span class="font-medium text-xs">{{ actionOpen }}</span>
          </div>
        </div>
      </div>
      <DropdownMenu />
    </div>
  </header>
</template>
