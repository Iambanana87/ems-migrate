<script setup>
import { ref } from "vue";
import Modal from "../ui/Modal.vue";
import { formatNumber } from "../../utils/format";

const props = defineProps({
  isOpen: { type: Boolean, required: true },
  device: { type: Object, default: () => ({}) },
  viewType: { type: String, default: "mold" },
});
const emit = defineEmits(["close", "add-action"]);

const close = () => {
  emit("close");
};

// Chart Modal refs
const dateShift = ref(0);
const fromDate = ref("");
const toDate = ref("");
</script>

<template>
  <Modal :isOpen="isOpen" @close="close" maxWidth="max-w-7xl">
    <template #header>
      <div class="flex items-center justify-between">
        <h3 class="text-xl font-bold text-gray-800 pr-16">
          {{ device?.device_id }} Details
        </h3>
        <button
          @click="emit('add-action', device)"
          class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700"
        >
          + Add Action
        </button>
      </div>
    </template>

    <template #body>
      <!-- Ported exactly from index.html lines 259-417 -->
      <div class="grid grid-cols-12 gap-2 mt-2 h-[65vh] overflow-y-auto">
        <!-- Information Panel -->
        <div
          class="col-span-12 md:col-span-2 bg-white p-3 rounded-lg shadow h-fit"
        >
          <h4
            class="text-lg font-semibold border-b pb-2 text-white bg-blue-500 -m-3 mb-3 rounded-t-lg text-center"
          >
            Information
          </h4>
          <div class="space-y-2 text-sm">
            <p>
              <strong>ID:</strong>
              <span class="ml-2 float-right font-semibold">{{
                device?.device_id
              }}</span>
            </p>
            <p>
              <strong>Process:</strong>
              <span class="ml-2 float-right font-semibold">{{
                device?.process
              }}</span>
            </p>
            <hr />

            <!-- Mold specific -->
            <template v-if="viewType === 'mold'">
              <p>
                <strong>Mold Cavity:</strong>
                <span class="ml-2 float-right font-semibold">{{
                  device?.cavities
                }}</span>
              </p>
              <p>
                <strong>Actual Cavity:</strong>
                <span class="ml-2 float-right font-semibold">{{
                  device?.live_data?.cavities
                }}</span>
              </p>
            </template>

            <!-- Common -->
            <hr />
            <p>
              <strong>Capacity:</strong>
              <span class="ml-2 float-right font-semibold">{{
                device?.capacity
              }}</span>
            </p>
            <p>
              <strong>Efficiency:</strong
              ><span class="ml-2 float-right font-semibold"
                >{{ device?.live_data?.efficiency }}%</span
              >
            </p>
            <p>
              <strong>Eff.requirement:</strong>
              <span class="ml-2 float-right font-semibold"
                >{{ device?.efficiency_lower_limit }}%</span
              >
            </p>
            <hr />

            <!-- Mold/Tuft/Blister specific metrics -->
            <template v-if="viewType === 'mold'">
              <p>
                <strong>Current Cycle:</strong>
                <span class="ml-2 float-right font-semibold text-red-500"
                  >{{ device?.live_data?.cycle_time }}s</span
                >
              </p>
            </template>
            <template v-else-if="viewType === 'tuft'">
              <p>
                <strong>Current Output:</strong>
                <span class="ml-2 float-right font-semibold text-red-500">{{
                  device?.live_data?.output
                }}</span>
              </p>
            </template>
            <template v-else-if="viewType === 'blister'">
              <p>
                <strong>Current Cycle:</strong>
                <span class="ml-2 float-right font-semibold text-red-500">{{
                  device?.live_data?.cyclecount
                }}</span>
              </p>
            </template>

            <p>
              <strong>Target:</strong>
              <span class="ml-2 float-right font-semibold">{{
                device?.target_limit
              }}</span>
            </p>
            <p>
              <strong>Upper Limit:</strong>
              <span class="ml-2 float-right font-semibold">{{
                device?.upper_limit
              }}</span>
            </p>
            <p>
              <strong>Lower Limit:</strong>
              <span class="ml-2 float-right font-semibold">{{
                device?.lower_limit
              }}</span>
            </p>
            <hr />

            <p>
              <strong>Total lost pcs:</strong>
              <span class="ml-2 float-right font-semibold">--</span>
            </p>
            <p>
              <strong>Lost time:</strong>
              <span class="ml-2 float-right font-semibold">--</span>
            </p>

            <template v-if="viewType === 'tuft'">
              <p>
                <strong>Total RPM</strong
                ><span class="ml-2 float-right font-semibold">--</span>
              </p>
            </template>
            <template v-if="viewType === 'blister'">
              <p>
                <strong>Total Cycle</strong
                ><span class="ml-2 float-right font-semibold">--</span>
              </p>
            </template>

            <p>
              <strong>Total Count</strong
              ><span class="ml-2 float-right font-semibold">--</span>
            </p>
            <template v-if="viewType === 'mold'">
              <p>
                <strong>Total Output</strong
                ><span class="ml-2 float-right font-semibold">--</span>
              </p>
            </template>
          </div>
        </div>

        <!-- Chart Area -->
        <div
          class="col-span-12 lg:col-span-7 bg-white p-3 rounded-lg shadow flex flex-col min-h-0"
        >
          <h4
            class="text-lg font-semibold text-gray-700 text-center mb-2 flex-shrink-0"
          >
            Hourly Efficiency
          </h4>
          <div
            class="flex justify-center items-center space-x-2 mb-2 flex-shrink-0"
          >
            <button
              @click="dateShift = -2"
              :class="[
                'px-3 py-1 text-sm rounded',
                dateShift === -2 ? 'bg-blue-600 text-white' : 'bg-gray-200',
              ]"
            >
              2 day ago
            </button>
            <button
              @click="dateShift = -1"
              :class="[
                'px-3 py-1 text-sm rounded',
                dateShift === -1 ? 'bg-blue-600 text-white' : 'bg-gray-200',
              ]"
            >
              Yesterday
            </button>
            <button
              @click="dateShift = 0"
              :class="[
                'px-3 py-1 text-sm rounded',
                dateShift === 0 ? 'bg-blue-600 text-white' : 'bg-gray-200',
              ]"
            >
              Today
            </button>
          </div>
          <!-- Chart canvas container -->
          <div
            class="relative w-full flex-grow bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-400"
          >
            <span>[Chart.js Canvas - Phase 5 / Phase 6 implementations]</span>
          </div>
        </div>

        <!-- Search & Report Panel -->
        <div
          class="col-span-12 md:col-span-3 bg-white p-3 rounded-lg shadow flex flex-col min-h-0 h-fit"
        >
          <div class="flex gap-3">
            <div class="flex-grow space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700"
                  >From dateline</label
                >
                <input
                  type="text"
                  v-model="fromDate"
                  placeholder="yyyy/mm/dd --:--"
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm text-sm px-2 py-1"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700"
                  >To dateline</label
                >
                <input
                  type="text"
                  v-model="toDate"
                  placeholder="yyyy/mm/dd --:--"
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm text-sm px-2 py-1"
                />
              </div>
            </div>
            <div class="flex flex-col space-y-2 justify-end">
              <button
                class="px-4 bg-blue-600 text-white py-1 rounded text-sm hover:bg-blue-700"
              >
                SEARCH
              </button>
              <button
                class="px-4 bg-gray-300 text-gray-800 py-1 rounded text-sm hover:bg-gray-400"
              >
                RESET
              </button>
            </div>
          </div>
          <hr class="my-4" />
          <div class="flex flex-col flex-grow">
            <div class="space-y text-sm">
              <div class="flex justify-between items-center">
                <span class="text-gray-600">Total Output</span
                ><strong class="text-lg">0</strong>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-gray-600">Average Cycle</span
                ><strong class="text-lg">0.00</strong>
              </div>
            </div>
            <div class="flex justify-end mt-2">
              <button
                class="text-green-700 hover:text-green-900 focus:outline-none"
                title="Export to Excel"
              >
                <img src="/image/excel.png" alt="Excel" class="h-5 w-5" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </Modal>
</template>
