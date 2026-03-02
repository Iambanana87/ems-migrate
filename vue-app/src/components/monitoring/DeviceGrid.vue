<script setup>
import { computed } from "vue";
import DeviceCard from "./DeviceCard.vue";
import SkeletonCard from "./SkeletonCard.vue";

const props = defineProps({
  devices: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  viewType: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(["device-click"]);

const handleDeviceClick = (device) => {
  emit("device-click", device);
};
</script>

<template>
  <div
    class="device-grid grid gap-3 grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7 pt-2"
  >
    <template v-if="loading">
      <SkeletonCard v-for="n in 12" :key="n" />
    </template>
    <template v-else-if="devices.length > 0">
      <DeviceCard
        v-for="device in devices"
        :key="device.device_id"
        :device="device"
        :viewType="viewType"
        @click="handleDeviceClick"
      />
    </template>
    <template v-else>
      <div class="col-span-full py-10 text-center text-gray-500 text-lg">
        No devices found.
      </div>
    </template>
  </div>
</template>

<style scoped>
/* Grid handled by Tailwind */
</style>
