<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import AutoSwitchToggle from "../ui/AutoSwitchToggle.vue";
import DeviceStatsBadge from "../ui/DeviceStatsBadge.vue";
import DropdownMenu from "./DropdownMenu.vue";

const router = useRouter();
const currentView = ref("mold"); // Default view, will tie to Pinia/Router later

// Mock data (Phase 2 constraint: mock data using ref)
const connectedDevices = ref(0);
const breachedDevices = ref(0);
const disconnectedDevices = ref(0);
const totalDevices = ref(0);
const flexibleDevices = ref(0);
const actionOpen = ref(0);

const setView = (view) => {
  currentView.value = view;
  // router.push({ name: view }) // Tie to router later
};
</script>

<template>
  <header
    class="page-header relative z-[1000] bg-white border-b border-gray-200 px-4 py-2 flex items-center justify-between shadow-sm"
  >
    <!-- Left: Logo & Auto Switch -->
    <div class="flex items-center space-x-4">
      <router-link to="/" class="flex items-center space-x-3">
        <img src="/image/icon2.png" alt="Logo" class="h-8 w-auto" />
        <img
          src="/image/ems_.png"
          alt="EMS Logo"
          style="height: 32px; width: auto"
        />
      </router-link>
      <AutoSwitchToggle />
      <button
        @click="router.push('/dashboard')"
        class="btn bg-[#268fda] text-white px-6 py-2 rounded-full text-xs font-medium hover:-translate-y-1 hover:shadow-lg transition-all duration-200"
      >
        Dashboard
      </button>
      <span class="text-lg text-gray-600 font-semibold hidden md:block">
        {{
          router.currentRoute.value.name === "dashboard"
            ? "EMS Dashboard"
            : "EMS Monitoring"
        }}
      </span>
    </div>

    <!-- Center: Tabs & Filters (Only on Monitoring route) -->
    <div
      v-if="router.currentRoute.value.name === 'home'"
      class="flex items-center gap-4 hidden md:flex"
    >
      <div class="relative">
        <select
          class="appearance-none bg-white border border-gray-300 text-gray-700 py-1.5 px-3 pr-8 rounded focus:outline-none focus:border-gray-500 text-sm font-semibold cursor-pointer"
        >
          <option value="all">All Clients</option>
        </select>
        <div
          class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700"
        >
          <svg class="h-3 w-3 fill-current" viewBox="0 0 20 20">
            <path d="M5 10l5 5 5-5z" />
          </svg>
        </div>
      </div>
      <button
        @click="setView('mold')"
        :class="[
          'flex items-center space-x-1 px-3 py-1.5 rounded transition-colors',
          currentView === 'mold'
            ? 'bg-blue-50 text-blue-600'
            : 'text-gray-600 hover:bg-gray-100',
        ]"
      >
        <span>Mold</span>
      </button>
      <button
        @click="setView('tuft')"
        :class="[
          'flex items-center space-x-1 px-3 py-1.5 rounded transition-colors',
          currentView === 'tuft'
            ? 'bg-blue-50 text-blue-600'
            : 'text-gray-600 hover:bg-gray-100',
        ]"
      >
        <span>Tufting</span>
      </button>
      <button
        @click="setView('blister')"
        :class="[
          'flex items-center space-x-1 px-3 py-1.5 rounded transition-colors',
          currentView === 'blister'
            ? 'bg-blue-50 text-blue-600'
            : 'text-gray-600 hover:bg-gray-100',
        ]"
      >
        <span>Blister</span>
      </button>
    </div>

    <!-- Right: Stats & Menu -->
    <div class="flex items-center space-x-4">
      <div
        v-if="router.currentRoute.value.name === 'home'"
        class="hidden lg:flex items-center space-x-2"
      >
        <div class="space-y-1">
          <DeviceStatsBadge
            type="online"
            label="Online"
            :count="connectedDevices"
          />
          <DeviceStatsBadge
            type="warning"
            label="Warning"
            :count="breachedDevices"
          />
        </div>
        <div class="space-y-1">
          <DeviceStatsBadge
            type="offline"
            label="Offline"
            :count="disconnectedDevices"
          />
          <DeviceStatsBadge type="total" label="Total" :count="totalDevices" />
        </div>
        <div class="space-y-1">
          <DeviceStatsBadge
            type="flexible"
            label="Flexible"
            :count="flexibleDevices"
          />
          <DeviceStatsBadge type="action" label="Action" :count="actionOpen" />
        </div>
      </div>
      <DropdownMenu />
    </div>
  </header>
</template>
