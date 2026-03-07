<script setup>
import DeviceGrid from './DeviceGrid.vue';

defineProps({
  devices: {
    type: Array,
    required: true,
  },
});
defineEmits(["device-click"]);
</script>

<template>
  <DeviceGrid 
    :devices="devices" 
    viewType="mold" 
    @device-click="$emit('device-click', $event)" 
  />
</template>
