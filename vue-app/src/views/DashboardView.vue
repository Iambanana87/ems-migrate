<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import api from "../services/api";
import { usePolling } from "../composables/usePolling";

// --- State ---
const loading = ref(true);
const error = ref(null);
const dashboardData = ref({
  mold: createEmptyProcessData(),
  tuft: createEmptyProcessData(),
  blister: createEmptyProcessData(),
});

function createEmptyProcessData() {
  return {
    status: { total: 0, running: 0, breakdown: 0, warning: 0 },
    efficiency: { day: 0, night: 0, average: 0 },
    output: {
      day: 0,
      night: 0,
      total: 0,
      day_lost_pcs: 0,
      day_loss_percent: 0,
      night_lost_pcs: 0,
      night_loss_percent: 0,
      total_lost_pcs: 0,
      total_loss_percent: 0,
    },
  };
}

// --- API ---
const fetchData = async () => {
  loading.value = true;
  error.value = null;
  try {
    const response = await api.get("", { params: { c: "Report", m: "summary" } });
    if (response.data) {
      dashboardData.value = {
        mold: response.data.mold || createEmptyProcessData(),
        tuft: response.data.tuft || createEmptyProcessData(),
        blister: response.data.blister || createEmptyProcessData()
      };
    }
  } catch (err) {
    console.error("Failed to load dashboard data", err);
    error.value = err.message || "Failed to load dashboard data.";
    dashboardData.value = {
      mold: createEmptyProcessData(),
      tuft: createEmptyProcessData(),
      blister: createEmptyProcessData()
    };
  } finally {
    loading.value = false;
  }
};

const { start, stop } = usePolling(fetchData, 30000);

// --- Interaction Handlers (Modals stubbed for Phase 6 focus on layout) ---
const openDetailsPopup = (process, statusFilter) => {
  console.log(`Open Details: ${process}, Filter: ${statusFilter}`);
  // TODO: Modal implementation
};

const openEfficiencyTablePopup = (process, slot) => {
  console.log(`Open Efficiency: ${process}, Slot: ${slot}`);
  // TODO: Modal implementation
};

const openOutputPopup = (process, slot) => {
  console.log(`Open Output: ${process}, Slot: ${slot}`);
  // TODO: Modal implementation
};

// Helpers
const formatNum = (num) => Number(num).toLocaleString("en-US");
const formatEff = (num) => Number(num).toFixed(2);

</script>

<template>
  <div class="dashboard-view">
    <div class="flex justify-start mb-4">
      <router-link
        to="/"
        class="custom-btn"
        style="text-decoration: none; text-align: center"
        >Overall</router-link
      >
      <router-link
        to="/family"
        class="custom-btn"
        style="text-decoration: none; text-align: center"
        >Family</router-link
      >
    </div>

    <div v-if="loading" class="flex justify-center p-10">
      <div
        class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"
      ></div>
    </div>
    
    <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mx-4" role="alert">
      <span class="block sm:inline">{{ error }}</span>
    </div>

    <table
      v-else
      class="summary-table border-separate border-spacing-x-4 border-spacing-y-4 w-full"
    >
      <thead>
        <tr>
          <th
            rowspan="2"
            class="th-box bg-white border border-gray-200 rounded-md shadow-sm text-gray-600 font-semibold px-3 py-2 text-center align-middle whitespace-nowrap"
          >
            Process
          </th>
          <th
            colspan="4"
            class="th-box bg-white border border-gray-200 rounded-md shadow-sm text-gray-600 font-semibold px-3 py-2 text-center align-middle whitespace-nowrap"
          >
            Status
          </th>
          <th
            colspan="3"
            class="th-box bg-white border border-gray-200 rounded-md shadow-sm text-gray-600 font-semibold px-3 py-2 text-center align-middle whitespace-nowrap"
          >
            Efficiency
          </th>
          <th
            colspan="3"
            class="th-box bg-white border border-gray-200 rounded-md shadow-sm text-gray-600 font-semibold px-3 py-2 text-center align-middle whitespace-nowrap"
          >
            Output
          </th>
        </tr>
        <tr>
          <th class="th-box text-sm font-medium">Total</th>
          <th class="th-box text-sm font-medium">Running</th>
          <th class="th-box text-sm font-medium">Break Down</th>
          <th class="th-box text-sm font-medium">Warning</th>
          <th class="th-box text-sm font-medium">07:00 ~ 19:00</th>
          <th class="th-box text-sm font-medium">19:00 ~ 07:00</th>
          <th class="th-box text-sm font-medium">Average</th>
          <th class="th-box text-sm font-medium">07:00 ~ 19:00</th>
          <th class="th-box text-sm font-medium">19:00 ~ 07:00</th>
          <th class="th-box text-sm font-medium">Total</th>
        </tr>
      </thead>
      <tbody>
        <template
          v-for="(processData, processKey) in dashboardData"
          :key="processKey"
        >
          <tr
            class="bg-white rounded-lg transition-transform hover:-translate-y-1 hover:bg-white cursor-pointer"
          >
            <td
              class="process-name font-bold text-2xl text-gray-800 pl-8 capitalize"
              @click="openDetailsPopup(processKey, 'total')"
            >
              {{
                processKey === "mold"
                  ? "Molding"
                  : processKey === "tuft"
                    ? "Tufting"
                    : "Blistering"
              }}
            </td>

            <!-- Status -->
            <td
              @click="openDetailsPopup(processKey, 'total')"
              class="hover:text-blue-600 text-center py-6"
            >
              <div class="flex flex-col items-center">
                <span class="text-3xl font-bold text-[#3498db]">{{
                  formatNum(processData.status?.total)
                }}</span>
              </div>
            </td>
            <td
              @click="openDetailsPopup(processKey, 'running')"
              class="hover:text-blue-600 text-center py-6"
            >
              <div class="flex flex-col items-center">
                <span class="text-3xl font-bold text-[#2ecc71]">{{
                  formatNum(processData.status?.running)
                }}</span>
              </div>
            </td>
            <td
              @click="openDetailsPopup(processKey, 'breakdown')"
              class="hover:text-blue-600 text-center py-6"
            >
              <div class="flex flex-col items-center">
                <span class="text-3xl font-bold text-[#f39c12]">{{
                  formatNum(processData.status?.breakdown)
                }}</span>
              </div>
            </td>
            <td
              @click="openDetailsPopup(processKey, 'warning')"
              class="hover:text-blue-600 text-center py-6"
            >
              <div class="flex flex-col items-center">
                <span class="text-3xl font-bold text-[#e74c3c]">{{
                  formatNum(processData.status?.warning)
                }}</span>
              </div>
            </td>

            <!-- Efficiency -->
            <td
              @click="openEfficiencyTablePopup(processKey, 'day')"
              class="text-center py-6"
            >
              <div class="flex flex-col items-center">
                <div
                  class="pie mx-auto"
                  :style="`--p: ${processData.efficiency?.day || 0}`"
                >
                  <span>{{ formatEff(processData.efficiency?.day) }}%</span>
                </div>
              </div>
            </td>
            <td
              @click="openEfficiencyTablePopup(processKey, 'night')"
              class="text-center py-6"
            >
              <div class="flex flex-col items-center">
                <div
                  class="pie mx-auto"
                  :style="`--p: ${processData.efficiency?.night || 0}`"
                >
                  <span>{{ formatEff(processData.efficiency?.night) }}%</span>
                </div>
              </div>
            </td>
            <td
              @click="openEfficiencyTablePopup(processKey, 'avg')"
              class="text-center py-6"
            >
              <div class="flex flex-col items-center">
                <div
                  class="pie mx-auto"
                  :style="`--p: ${processData.efficiency?.average || 0}`"
                >
                  <span>{{ formatEff(processData.efficiency?.average) }}%</span>
                </div>
              </div>
            </td>

            <!-- Output -->
            <td
              @click="openOutputPopup(processKey, 'day')"
              class="text-center py-6 relative group"
            >
              <div class="flex flex-col items-center">
                <span class="text-2xl font-bold text-gray-800">{{
                  formatNum(processData.output?.day)
                }}</span>
                <span class="text-sm font-normal text-gray-800">pcs</span>
                <div
                  v-if="processData.output?.day_lost_pcs > 0"
                  class="text-xs font-medium text-[#e74c3c] mt-1 hidden group-hover:block absolute bottom-0"
                >
                  {{ formatNum(processData.output?.day_lost_pcs) }} <br />({{
                    formatEff(processData.output?.day_loss_percent)
                  }}%) <br /><span class="text-black font-normal">pcs</span>
                </div>
              </div>
            </td>
            <td
              @click="openOutputPopup(processKey, 'night')"
              class="text-center py-6 relative group"
            >
              <div class="flex flex-col items-center">
                <span class="text-2xl font-bold text-gray-800">{{
                  formatNum(processData.output?.night)
                }}</span>
                <span class="text-sm font-normal text-gray-800">pcs</span>
                <div
                  v-if="processData.output?.night_lost_pcs > 0"
                  class="text-xs font-medium text-[#e74c3c] mt-1 hidden group-hover:block absolute bottom-0"
                >
                  {{ formatNum(processData.output?.night_lost_pcs) }} <br />({{
                    formatEff(processData.output?.night_loss_percent)
                  }}%) <br /><span class="text-black font-normal">pcs</span>
                </div>
              </div>
            </td>
            <td
              @click="openOutputPopup(processKey, 'total')"
              class="text-center py-6 relative group"
            >
              <div class="flex flex-col items-center">
                <span class="text-2xl font-bold text-gray-800">{{
                  formatNum(processData.output?.total)
                }}</span>
                <span class="text-sm font-normal text-gray-800">pcs</span>
                <div
                  v-if="processData.output?.total_lost_pcs > 0"
                  class="text-xs font-medium text-[#e74c3c] mt-1 hidden group-hover:block absolute bottom-0"
                >
                  {{ formatNum(processData.output?.total_lost_pcs) }} <br />({{
                    formatEff(processData.output?.total_loss_percent)
                  }}%) <br /><span class="text-black font-normal">pcs</span>
                </div>
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
/* Scoped CSS based precisely on the legacy dashboard.html styles */
.summary-table {
  max-width: 1400px;
  margin: 1rem auto;
}
@media (min-width: 1920px) {
  .summary-table {
    max-width: clamp(1200px, 92vw, 2200px);
  }
}
@media (min-width: 2560px) {
  .summary-table {
    max-width: clamp(1200px, 90vw, 2400px);
  }
}

.custom-btn {
  padding: 1.3em 3em;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 2.5px;
  font-weight: 500;
  color: #000;
  background-color: #fff;
  border: none;
  border-radius: 45px;
  box-shadow: 0px 8px 15px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease 0s;
  cursor: pointer;
  outline: none;
  margin: 0 10px;
}
.custom-btn:hover {
  background-color: #268fda;
  box-shadow: 0px 15px 20px rgba(46, 189, 229, 0.4);
  color: #fff;
  transform: translateY(-7px);
}
.custom-btn.router-link-active {
  background-color: #268fda;
  color: #fff;
  box-shadow: 0px 15px 20px rgba(46, 189, 229, 0.4);
}

/* Pie chart CSS Trick from legacy */
@property --p {
  syntax: "<number>";
  inherits: true;
  initial-value: 0;
}
.pie {
  --p: 0;
  --b: 10px;
  --c: #2ecc71;
  --w: 100px;
  width: var(--w);
  aspect-ratio: 1;
  position: relative;
  display: inline-grid;
  place-content: center;
  font-size: 1rem;
  font-weight: bold;
  color: var(--c);
  margin: 5px;
  transition: --p 1s;
  white-space: nowrap;
}
.pie:before,
.pie:after {
  content: "";
  position: absolute;
  border-radius: 50%;
}
.pie:before {
  inset: 0;
  background:
    radial-gradient(farthest-side, var(--c) 98%, #0000) top/var(--b) var(--b)
      no-repeat,
    conic-gradient(var(--c) calc(var(--p) * 1%), #0000 0);
  -webkit-mask: radial-gradient(
    farthest-side,
    #0000 calc(99% - var(--b)),
    #000 calc(100% - var(--b))
  );
  mask: radial-gradient(
    farthest-side,
    #0000 calc(99% - var(--b)),
    #000 calc(100% - var(--b))
  );
}
.pie:after {
  inset: calc(50% - var(--b) / 2);
  background: var(--c);
  transform: rotate(calc(var(--p) * 3.6deg))
    translateY(calc(50% - var(--w) / 2));
}
@media (max-width: 1536px) {
  .pie {
    --w: 72px;
  }
}
@media (max-width: 1366px) {
  .pie {
    --w: 64px;
  }
}
@media (min-width: 1920px) {
  .pie {
    --w: clamp(90px, 5.5vw, 160px);
    --b: clamp(8px, 0.7vw, 16px);
    font-size: clamp(1rem, 0.9rem + 0.25vw, 1.3rem);
  }
}
@media (min-width: 2560px) {
  .pie {
    --w: clamp(110px, 6vw, 180px);
    --b: clamp(10px, 0.8vw, 18px);
  }
}
</style>
