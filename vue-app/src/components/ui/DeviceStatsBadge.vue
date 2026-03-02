<script setup>
import { computed } from "vue";

const props = defineProps({
  type: {
    type: String,
    required: true,
    validator: (value) =>
      ["online", "warning", "offline", "total", "flexible", "action"].includes(
        value,
      ),
  },
  label: {
    type: String,
    required: true,
  },
  count: {
    type: Number,
    required: true,
  },
});

const badgeConfig = computed(() => {
  switch (props.type) {
    case "online":
      return {
        class: "text-green-600 bg-green-100 border-green-700 w-26",
        title: "Devices online",
      };
    case "warning":
      return {
        class: "text-red-600 bg-red-100 border-red-400 w-24",
        title: "Devices breached thresholds",
      };
    case "offline":
      return {
        class: "text-gray-600 bg-gray-100 border-gray-400 w-30",
        title: "Devices disconnected",
      };
    case "total":
      return {
        class: "text-blue-600 bg-blue-100 border-blue-400 w-30",
        title: "Devices total",
      };
    case "flexible":
      return {
        class: "text-sky-700 bg-sky-100 border-sky-300 w-26",
        title: "Devices flexible",
      };
    case "action":
      return {
        class: "text-purple-700 bg-purple-100 border-purple-300 w-26",
        title: "Action total",
      };
    default:
      return {
        class: "text-gray-600 bg-gray-100 border-gray-400 w-24",
        title: "",
      };
  }
});
</script>

<template>
  <div
    class="flex justify-between items-center px-3 py-1 rounded-full border"
    :class="badgeConfig.class"
    :title="badgeConfig.title"
  >
    <span class="font-medium text-xs">{{ label }}:</span>
    <span class="font-medium text-xs">{{ count }}</span>
  </div>
</template>
