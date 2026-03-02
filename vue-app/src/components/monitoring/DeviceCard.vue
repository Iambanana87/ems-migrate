<script setup>
import { computed } from "vue";

const props = defineProps({
  device: {
    type: Object,
    required: true,
  },
  viewType: {
    type: String, // 'mold', 'tuft', 'blister'
    required: true,
  },
});

const emit = defineEmits(["click"]);

const isDisconnected = computed(() => props.device.status === "DISCONNECTED");

const isBreached = computed(() => {
  if (isDisconnected.value) return false;
  const d = props.device;
  const l = d.live_data || {};

  if (props.viewType === "mold") {
    const isCycleOut =
      parseFloat(l.cycle_time) < parseFloat(d.lower_limit) ||
      parseFloat(l.cycle_time) > parseFloat(d.upper_limit);
    const isCavityOut = parseInt(l.cavities) < parseInt(d.cavities);
    return isCycleOut || isCavityOut;
  } else if (props.viewType === "tuft") {
    return (
      parseFloat(l.output) < parseFloat(d.lower_limit) ||
      parseFloat(l.output) > parseFloat(d.upper_limit)
    );
  } else if (props.viewType === "blister") {
    return (
      parseFloat(l.cyclecount) < parseFloat(d.lower_limit) ||
      parseFloat(l.cyclecount) > parseFloat(d.upper_limit)
    );
  }
  return false;
});

const headerBgColor = computed(() => {
  if (isDisconnected.value) return "#999";
  if (isBreached.value) return "#E74C3C";
  return "#2ECC71";
});

const cardClasses = computed(() => [
  "info-card",
  `status-${(props.device.status || "").toLowerCase()}`,
  { "status-breached": !isDisconnected.value && isBreached.value },
]);

const actionIndicatorHtml = computed(() => {
  if (props.device.action_count_open > 0) {
    return {
      class: `action-indicator ${props.device.action_urgent_overdue ? "urgent" : ""}`,
      title: `${props.device.action_count_open} active action(s)`,
      text: "A",
    };
  }
  return null;
});

function formatDbDate(timestamp) {
  if (!timestamp) return "";
  // Assume simple format or standard presentation, fallback to string if complex
  return new Date(timestamp.replace(" ", "T") + "Z").toLocaleString();
}
</script>

<template>
  <div :class="cardClasses" @click="emit('click', device)">
    <div v-if="device.flex == 1" class="flex-indicator">F</div>
    <div
      v-if="actionIndicatorHtml"
      :class="actionIndicatorHtml.class"
      :title="actionIndicatorHtml.title"
    >
      {{ actionIndicatorHtml.text }}
    </div>

    <div class="card-header" :style="{ backgroundColor: headerBgColor }">
      <div class="vt-code">{{ device.device_id }}</div>
      <div class="product-name">{{ device.product }}</div>
    </div>

    <!-- No Data State -->
    <div
      v-if="!device.live_data"
      class="flex flex-col flex-grow items-center justify-center p-4"
    >
      <div class="card-quantity" style="color: #b3b3b3; font-size: 3em">
        NO DATA
      </div>
    </div>

    <!-- Mold Body -->
    <div v-else-if="viewType === 'mold'" class="flex flex-col flex-grow">
      <div
        class="flex w-full"
        style="flex-grow: 1.5; border-bottom: 1px solid #eeeeee"
      >
        <div class="w-1/2 flex flex-col items-center justify-center">
          <span
            class="font-bold text-lg"
            :class="{
              'text-[#E74C3C] font-bold':
                parseFloat(device.live_data?.cycle_time) <
                  parseFloat(device.lower_limit) ||
                parseFloat(device.live_data?.cycle_time) >
                  parseFloat(device.upper_limit),
            }"
          >
            {{ device.live_data?.cycle_time ?? "N/A" }}s
          </span>
          <span class="text-[0.6rem] text-gray-500">Current Cycle</span>
        </div>
        <div
          class="w-1/2 flex flex-col items-center justify-center border-l border-gray-200"
        >
          <span
            class="font-bold text-lg"
            :class="{
              'text-[#E74C3C] font-bold':
                parseFloat(device.live_data?.efficiency) <
                parseFloat(device.efficiency_lower_limit),
            }"
          >
            {{ device.live_data?.efficiency ?? "N/A" }}%
          </span>
          <span class="text-[0.6rem] text-gray-500">Efficiency</span>
        </div>
      </div>
      <div
        class="card-specs bg-white flex justify-around p-2 text-center text-xs"
      >
        <div class="card-spec-col">
          <span
            class="font-semibold"
            :class="{
              'text-[#E74C3C]':
                parseFloat(device.live_data?.cycle_time) >
                parseFloat(device.upper_limit),
            }"
            >{{ device.upper_limit ?? "N/A" }}</span
          ><br /><span class="text-gray-400">UL</span>
        </div>
        <div class="card-spec-col">
          <span class="font-semibold">{{ device.target_limit ?? "N/A" }}</span
          ><br /><span class="text-gray-400">Target</span>
        </div>
        <div class="card-spec-col">
          <span
            class="font-semibold"
            :class="{
              'text-[#E74C3C]':
                parseFloat(device.live_data?.cycle_time) <
                parseFloat(device.lower_limit),
            }"
            >{{ device.lower_limit ?? "N/A" }}</span
          ><br /><span class="text-gray-400">LL</span>
        </div>
      </div>
      <div
        class="card-details flex justify-around p-2 bg-gray-50 text-center text-xs border-t"
      >
        <div class="card-spec-col">
          <span class="font-semibold">{{ device.process ?? "N/A" }}</span
          ><br /><span class="text-gray-400">Process</span>
        </div>
        <div class="card-spec-col">
          <span
            class="font-semibold"
            :class="{
              'text-[#E74C3C] font-bold':
                parseInt(device.live_data?.cavities) <
                parseInt(device.cavities),
            }"
            >{{ device.live_data?.cavities ?? "N/A" }}</span
          ><br /><span class="text-gray-400">Actual Cavity</span>
        </div>
        <div class="card-spec-col">
          <span
            class="font-semibold"
            :class="{
              'text-[#E74C3C] font-bold':
                parseInt(device.live_data?.cavities) <
                parseInt(device.cavities),
            }"
            >{{ device.cavities ?? "N/A" }}</span
          ><br /><span class="text-gray-400">Mold Cavity</span>
        </div>
      </div>
    </div>

    <!-- Tuft Body -->
    <div v-else-if="viewType === 'tuft'" class="flex flex-col flex-grow">
      <div
        class="flex w-full"
        style="flex-grow: 1.5; border-bottom: 1px solid #eeeeee"
      >
        <div class="w-1/2 flex flex-col items-center justify-center">
          <span
            class="font-bold text-lg"
            :class="{
              'text-[#E74C3C] font-bold':
                parseFloat(device.live_data?.output) <
                  parseFloat(device.lower_limit) ||
                parseFloat(device.live_data?.output) >
                  parseFloat(device.upper_limit),
            }"
          >
            {{ device.live_data?.output ?? "N/A" }}pcs
          </span>
          <span class="text-[0.6rem] text-gray-500">Current Cycle</span>
        </div>
        <div
          class="w-1/2 flex flex-col items-center justify-center border-l border-gray-200"
        >
          <span
            class="font-bold text-lg"
            :class="{
              'text-[#E74C3C] font-bold':
                parseFloat(device.live_data?.efficiency) <
                parseFloat(device.efficiency_lower_limit),
            }"
          >
            {{ device.live_data?.efficiency ?? "N/A" }}%
          </span>
          <span class="text-[0.6rem] text-gray-500">Efficiency</span>
        </div>
      </div>
      <div
        class="card-specs bg-white flex justify-around p-2 text-center text-xs"
      >
        <div class="card-spec-col">
          <span
            class="font-semibold"
            :class="{
              'text-[#E74C3C]':
                parseFloat(device.live_data?.output) >
                parseFloat(device.upper_limit),
            }"
            >{{ device.upper_limit ?? "N/A" }}</span
          ><br /><span class="text-gray-400">UL</span>
        </div>
        <div class="card-spec-col">
          <span class="font-semibold">{{ device.target_limit ?? "N/A" }}</span
          ><br /><span class="text-gray-400">Target</span>
        </div>
        <div class="card-spec-col">
          <span
            class="font-semibold"
            :class="{
              'text-[#E74C3C]':
                parseFloat(device.live_data?.output) <
                parseFloat(device.lower_limit),
            }"
            >{{ device.lower_limit ?? "N/A" }}</span
          ><br /><span class="text-gray-400">LL</span>
        </div>
      </div>
      <div
        class="card-details flex justify-around p-2 bg-gray-50 text-center text-xs border-t"
      >
        <div class="card-spec-col">
          <span class="font-semibold">{{ device.process ?? "N/A" }}</span
          ><br /><span class="text-gray-400">Process</span>
        </div>
        <div class="card-spec-col">
          <span class="font-semibold">{{ device.live_data?.rpm ?? "N/A" }}</span
          ><br /><span class="text-gray-400">RPM</span>
        </div>
      </div>
    </div>

    <!-- Blister Body -->
    <div v-else-if="viewType === 'blister'" class="flex flex-col flex-grow">
      <div
        class="flex w-full"
        style="flex-grow: 1.5; border-bottom: 1px solid #eeeeee"
      >
        <div class="w-1/2 flex flex-col items-center justify-center">
          <span
            class="font-bold text-lg"
            :class="{
              'text-[#E74C3C] font-bold':
                parseFloat(device.live_data?.cyclecount) <
                  parseFloat(device.lower_limit) ||
                parseFloat(device.live_data?.cyclecount) >
                  parseFloat(device.upper_limit),
            }"
          >
            {{ device.live_data?.cyclecount ?? "N/A" }}cycles
          </span>
          <span class="text-[0.6rem] text-gray-500">Current Cycle</span>
        </div>
        <div
          class="w-1/2 flex flex-col items-center justify-center border-l border-gray-200"
        >
          <span
            class="font-bold text-lg"
            :class="{
              'text-[#E74C3C] font-bold':
                parseFloat(device.live_data?.efficiency) <
                parseFloat(device.efficiency_lower_limit),
            }"
          >
            {{ device.live_data?.efficiency ?? "N/A" }}%
          </span>
          <span class="text-[0.6rem] text-gray-500">Efficiency</span>
        </div>
      </div>
      <div
        class="card-specs bg-white flex justify-around p-2 text-center text-xs"
      >
        <div class="card-spec-col">
          <span
            class="font-semibold"
            :class="{
              'text-[#E74C3C]':
                parseFloat(device.live_data?.cyclecount) >
                parseFloat(device.upper_limit),
            }"
            >{{ device.upper_limit ?? "N/A" }}</span
          ><br /><span class="text-gray-400">UL</span>
        </div>
        <div class="card-spec-col">
          <span class="font-semibold">{{ device.target_limit ?? "N/A" }}</span
          ><br /><span class="text-gray-400">Target</span>
        </div>
        <div class="card-spec-col">
          <span
            class="font-semibold"
            :class="{
              'text-[#E74C3C]':
                parseFloat(device.live_data?.cyclecount) <
                parseFloat(device.lower_limit),
            }"
            >{{ device.lower_limit ?? "N/A" }}</span
          ><br /><span class="text-gray-400">LL</span>
        </div>
      </div>
      <div
        class="card-details flex justify-around p-2 bg-gray-50 text-center text-xs border-t"
      >
        <div class="card-spec-col">
          <span class="font-semibold">{{
            device.live_data?.BrushesperCycle ?? "N/A"
          }}</span
          ><br /><span class="text-gray-400">Brushes/cycle</span>
        </div>
        <div class="card-spec-col">
          <span class="font-semibold">{{
            device.live_data?.output ?? "N/A"
          }}</span
          ><br /><span class="text-gray-400">Pcs/minute</span>
        </div>
      </div>
    </div>

    <div
      class="card-footer bg-gray-100 text-center text-[0.65rem] text-gray-500 py-1 border-t"
    >
      {{ formatDbDate(device.timestamp) || "N/A" }}
    </div>
  </div>
</template>

<style scoped>
.info-card {
  position: relative;
  background-color: #fff;
  border-radius: 8px;
  box-shadow:
    0 4px 6px -1px rgba(0, 0, 0, 0.1),
    0 2px 4px -1px rgba(0, 0, 0, 0.06);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  cursor: pointer;
  transition:
    transform 0.2s,
    box-shadow 0.2s;
  min-height: 180px;
}
.info-card:hover {
  transform: translateY(-2px);
  box-shadow:
    0 10px 15px -3px rgba(0, 0, 0, 0.1),
    0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
.card-header {
  padding: 8px 12px;
  color: white;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.vt-code {
  font-weight: bold;
  font-size: 0.85rem;
}
.product-name {
  font-size: 0.8rem;
  opacity: 0.9;
  text-align: right;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 60%;
}

.flex-indicator {
  position: absolute;
  top: 0;
  left: 0;
  width: 26px;
  height: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 15px;
  line-height: 1;
  border-radius: 6px 0 8px 0;
  border: 2px solid #fff;
  background: #3b82f6;
  color: #fff;
  z-index: 5;
}
.action-indicator {
  position: absolute;
  top: 0;
  right: 0;
  width: 26px;
  height: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 15px;
  line-height: 1;
  border-radius: 0 6px 0 8px;
  border: 2px solid #fff;
  background: #7763d2;
  color: #ffffff;
  z-index: 5;
}
.action-indicator.urgent {
  background: #f3e8ff;
  color: #6d28d9;
  border-color: #c4b5fd;
}
.status-breached {
  border: 2px solid #e74c3c;
  animation: pulse-red var(--anim-offset, 0s) 2s infinite;
}
.status-disconnected {
  opacity: 0.6;
}
</style>
