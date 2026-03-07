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
const redStyle = 'color: #E74C3C; font-weight: bold;';

const cycleStyle = computed(() => {
  const d = props.device;
  const l = d.live_data || {};
  if (props.viewType === 'mold') {
    const cycleTimeFloat = parseFloat(l.cycle_time);
    if (!isNaN(cycleTimeFloat) && (!isNaN(parseFloat(d.lower_limit)) && cycleTimeFloat < parseFloat(d.lower_limit) || !isNaN(parseFloat(d.upper_limit)) && cycleTimeFloat > parseFloat(d.upper_limit))) return redStyle;
  } else if (props.viewType === 'tuft') {
    const outputFloat = parseFloat(l.output);
    if (!isNaN(outputFloat) && (!isNaN(parseFloat(d.lower_limit)) && outputFloat < parseFloat(d.lower_limit) || !isNaN(parseFloat(d.upper_limit)) && outputFloat > parseFloat(d.upper_limit))) return redStyle;
  } else if (props.viewType === 'blister') {
    const cycleCountFloat = parseFloat(l.cyclecount);
    if (!isNaN(cycleCountFloat) && (!isNaN(parseFloat(d.lower_limit)) && cycleCountFloat < parseFloat(d.lower_limit) || !isNaN(parseFloat(d.upper_limit)) && cycleCountFloat > parseFloat(d.upper_limit))) return redStyle;
  }
  return '';
});

const efficiencyStyle = computed(() => {
  const d = props.device;
  const l = d.live_data || {};
  const effLowerLimitFloat = parseFloat(d.efficiency_lower_limit);
  const efficiencyFloat = parseFloat(l.efficiency);
  if (!isNaN(efficiencyFloat) && !isNaN(effLowerLimitFloat) && efficiencyFloat < effLowerLimitFloat) return redStyle;
  return '';
});

const actualCavityStyle = computed(() => {
  if (props.viewType !== 'mold') return '';
  const d = props.device;
  const l = d.live_data || {};
  if (parseInt(l.cavities) < parseInt(d.cavities)) return redStyle;
  return '';
});

const moldCavityStyle = computed(() => {
  if (props.viewType !== 'mold') return '';
  const d = props.device;
  const l = d.live_data || {};
  if (parseInt(l.cavities) < parseInt(d.cavities)) return redStyle;
  return '';
});

const llStyle = computed(() => {
  const d = props.device;
  const l = d.live_data || {};
  const lowerLimit = parseFloat(d.lower_limit);
  if (isNaN(lowerLimit)) return '';
  let value;
  if (props.viewType === 'mold') value = parseFloat(l.cycle_time);
  else if (props.viewType === 'tuft') value = parseFloat(l.output);
  else if (props.viewType === 'blister') value = parseFloat(l.cyclecount);
  return !isNaN(value) && value < lowerLimit ? redStyle : '';
});

const ulStyle = computed(() => {
  const d = props.device;
  const l = d.live_data || {};
  const upperLimit = parseFloat(d.upper_limit);
  if (isNaN(upperLimit)) return '';
  let value;
  if (props.viewType === 'mold') value = parseFloat(l.cycle_time);
  else if (props.viewType === 'tuft') value = parseFloat(l.output);
  else if (props.viewType === 'blister') value = parseFloat(l.cyclecount);
  return !isNaN(value) && value > upperLimit ? redStyle : '';
});

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
      <div class="vt-code">{{ device.device_id }}</div><div class="product-name">{{ device.product }}</div>
    </div>

    <!-- No Data State -->
    <template v-if="!device.live_data">
      <div class="card-quantity" style="color: #b3b3b3; font-size: 3em" data-translate="NO DATA">NO DATA</div>
    </template>

    <!-- Mold Body -->
    <template v-else-if="viewType === 'mold'">
      <div class="flex flex-col flex-grow">
        <div class="flex w-full" style="flex-grow: 1.5; border-bottom: 1px solid #eeeeee;">
          <div class="w-1/2 flex flex-col items-center justify-center">
            <span class="font-bold text-lg" :style="cycleStyle">{{ device.live_data?.cycle_time ?? 'N/A' }}s</span>
            <span class="font-size:0.2em text-gray-500" data-translate="Current Cycle">Current Cycle</span>
          </div>
          <div class="w-1/2 flex flex-col items-center justify-center border-l border-gray-200">
            <span class="font-bold text-lg" :style="efficiencyStyle">{{ device.live_data?.efficiency ?? 'N/A' }}%</span>
            <span class="font-size:0.2em text-gray-500" data-translate="Efficiency">Efficiency</span>
          </div>
        </div>
        <div class="card-specs bg-white">
          <div class="card-spec-col"><span class="value" :style="ulStyle">{{ device.upper_limit ?? 'N/A' }}</span><br><span class="label">UL</span></div>
          <div class="card-spec-col"><span class="value">{{ device.target_limit ?? 'N/A' }}</span><br><span class="label" data-translate="Target">Target</span></div>
          <div class="card-spec-col"><span class="value" :style="llStyle">{{ device.lower_limit ?? 'N/A' }}</span><br><span class="label">LL</span></div>
        </div>
        <div class="card-details">
          <div class="card-spec-col"><span class="value">{{ device.process ?? 'N/A' }}</span><br><span class="label" data-translate="Process">Process</span></div>
          <div class="card-spec-col"><span class="value" :style="actualCavityStyle">{{ device.live_data?.cavities ?? 'N/A' }}</span><br><span class="label" data-translate="Actual Cavity">Actual Cavity</span></div>
          <div class="card-spec-col"><span class="value" :style="moldCavityStyle">{{ device.cavities ?? 'N/A' }}</span><br><span class="label" data-translate="Mold Cavity">Mold Cavity</span></div>
        </div>
      </div>
    </template>

    <!-- Tuft Body -->
    <template v-else-if="viewType === 'tuft'">
      <div class="flex flex-col flex-grow">
        <div class="flex w-full" style="flex-grow: 1.5; border-bottom: 1px solid #eeeeee;">
          <div class="w-1/2 flex flex-col items-center justify-center">
            <span class="font-bold text-lg" :style="cycleStyle">{{ device.live_data?.output ?? 'N/A' }}pcs</span>
            <span class="font-size:0.2em text-gray-500" data-translate="Current Cycle">Current Cycle</span>
          </div>
          <div class="w-1/2 flex flex-col items-center justify-center border-l border-gray-200">
            <span class="font-bold text-lg" :style="efficiencyStyle">{{ device.live_data?.efficiency ?? 'N/A' }}%</span>
            <span class="font-size:0.2em text-gray-500" data-translate="Efficiency">Efficiency</span>
          </div>
        </div>
        <div class="card-specs bg-white">
          <div class="card-spec-col"><span class="value" :style="ulStyle">{{ device.upper_limit ?? 'N/A' }}</span><br><span class="label">UL</span></div>
          <div class="card-spec-col"><span class="value">{{ device.target_limit ?? 'N/A' }}</span><br><span class="label" data-translate="Target">Target</span></div>
          <div class="card-spec-col"><span class="value" :style="llStyle">{{ device.lower_limit ?? 'N/A' }}</span><br><span class="label">LL</span></div>
        </div>
        <div class="card-details">
          <div class="card-spec-col"><span class="value">{{ device.process ?? 'N/A' }}</span><br><span class="label" data-translate="Process">Process</span></div>
          <div class="card-spec-col"><span class="value">{{ device.live_data?.rpm ?? 'N/A' }}</span><br><span class="label" data-translate="rpm">RPM</span></div>
        </div>
      </div>
    </template>

    <!-- Blister Body -->
    <template v-else-if="viewType === 'blister'">
      <div class="flex flex-col flex-grow">
        <div class="flex w-full" style="flex-grow: 1.5; border-bottom: 1px solid #eeeeee;">
          <div class="w-1/2 flex flex-col items-center justify-center">
            <span class="font-bold text-lg" :style="cycleStyle">{{ device.live_data?.cyclecount ?? 'N/A' }}cycles</span>
            <span class="font-size:0.2em text-gray-500" data-translate="Current Cycle">Current Cycle</span>
          </div>
          <div class="w-1/2 flex flex-col items-center justify-center border-l border-gray-200">
            <span class="font-bold text-lg" :style="efficiencyStyle">{{ device.live_data?.efficiency ?? 'N/A' }}%</span>
            <span class="font-size:0.2em text-gray-500" data-translate="Efficiency">Efficiency</span>
          </div>
        </div>
        <div class="card-specs bg-white">
          <div class="card-spec-col"><span class="value" :style="ulStyle">{{ device.upper_limit ?? 'N/A' }}</span><br><span class="label">UL</span></div>
          <div class="card-spec-col"><span class="value">{{ device.target_limit ?? 'N/A' }}</span><br><span class="label" data-translate="Target">Target</span></div>
          <div class="card-spec-col"><span class="value" :style="llStyle">{{ device.lower_limit ?? 'N/A' }}</span><br><span class="label">LL</span></div>
        </div>
        <div class="card-details">
          <div class="card-spec-col"><span class="value">{{ device.live_data?.BrushesperCycle ?? 'N/A' }}</span><br><span class="label" data-translate="brushes_per_cycle">Brushes/cycle</span></div>
          <div class="card-spec-col"><span class="value">{{ device.live_data?.output ?? 'N/A' }}</span><br><span class="label" data-translate="pcs_per_minute">Pcs/minute</span></div>
        </div>
      </div>
    </template>

    <div class="card-footer">{{ formatDbDate(device.timestamp) || "N/A" }}</div>
  </div>
</template>
