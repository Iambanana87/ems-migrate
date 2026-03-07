<script setup>
defineProps({
  devices: {
    type: Array,
    required: true,
  },
  columns: {
    type: Array,
    required: true,
    // Expected format: [{ key: 'device_id', label: 'Device ID' }, ...]
  },
  machineType: {
    type: String,
    required: true,
  }
});
defineEmits(["device-click"]);
</script>

<template>
  <div class="monitoring-table-wrapper">
    <h3 class="text-lg font-semibold mb-3 text-gray-800">{{ machineType }} Devices</h3>
    <div class="overflow-x-auto rounded-lg border border-gray-200">
      <table class="min-w-full divide-y divide-gray-200 shadow-sm">
        <thead class="bg-gray-50">
          <tr>
            <th 
              v-for="col in columns" 
              :key="col.key"
              class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
          <tr 
            v-for="device in devices" 
            :key="device.device_id" 
            class="cursor-pointer hover:bg-gray-50 transition duration-150" 
            @click="$emit('device-click', device)"
          >
            <td 
              v-for="col in columns" 
              :key="col.key"
              class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"
            >
              {{ device[col.key] }}
            </td>
          </tr>
          <tr v-if="!devices || devices.length === 0">
            <td :colspan="columns.length" class="px-6 py-8 whitespace-nowrap text-center text-sm text-gray-500">
              No {{ machineType.toLowerCase() }} devices found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles for high performance */
table {
  table-layout: fixed;
}
td {
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
