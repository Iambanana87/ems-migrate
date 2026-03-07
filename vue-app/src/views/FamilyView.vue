<script setup>
import { ref, onMounted, computed } from "vue";
import api from "../services/api";
import { usePolling } from "../composables/usePolling";

// --- State ---
const loading = ref(false);
const families = ref([]);
const selectedFamily = ref("");
const tableData = ref([]);
const error = ref(null);

// Date formatting utility for the local input fallback
const toLocalInputValue = (d) => {
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const now = new Date();
const today7 = new Date(
  now.getFullYear(),
  now.getMonth(),
  now.getDate(),
  7,
  0,
  0,
);
const fromDateInitial = new Date(today7.getTime() - 24 * 3600 * 1000);

const fromDate = ref(toLocalInputValue(fromDateInitial));
const toDate = ref(toLocalInputValue(today7));

// --- API ---
const fetchData = async () => {
  loading.value = true;
  error.value = null;
  try {
    const response = await api.get("", { params: { c: "Family", m: "index" } });
    tableData.value = response.data?.data || response.data || [];
    const uniqueFamilies = [...new Set(tableData.value.map((row) => row.family))];
    families.value = uniqueFamilies;
  } catch (err) {
    console.error("Failed to load family data", err);
    error.value = err.message || "Failed to load family data.";
    tableData.value = [];
    families.value = [];
  } finally {
    loading.value = false;
  }
};

// Fetch on mount
onMounted(() => {
  fetchData();
});

const handleApply = () => {
  fetchData();
};

const handleClear = () => {
  selectedFamily.value = "";
  fetchData();
};

// --- Computed (Filters and Totals) ---
const filteredData = computed(() => {
  if (!selectedFamily.value) return tableData.value;
  return tableData.value.filter((row) => row.family === selectedFamily.value);
});

const totals = computed(() => {
  const t = {
    output: { mold: 0, tuft: 0, blister: 0 },
    cap: { mold: 0, tuft: 0, blister: 0 },
    eff: { mold: 0, tuft: 0, blister: 0 },
  };
  let effCount = { mold: 0, tuft: 0, blister: 0 };

  filteredData.value.forEach((row) => {
    t.output.mold += row.mold.output || 0;
    t.output.tuft += row.tuft.output || 0;
    t.output.blister += row.blister.output || 0;

    // Using simple averaging for mock, legacy calculated via (output / (cap * hoursTotal))
    if (row.mold.eff > 0) {
      t.eff.mold += row.mold.eff;
      effCount.mold++;
    }
    if (row.tuft.eff > 0) {
      t.eff.tuft += row.tuft.eff;
      effCount.tuft++;
    }
    if (row.blister.eff > 0) {
      t.eff.blister += row.blister.eff;
      effCount.blister++;
    }
  });

  t.eff.mold = effCount.mold ? (t.eff.mold / effCount.mold).toFixed(2) : 0;
  t.eff.tuft = effCount.tuft ? (t.eff.tuft / effCount.tuft).toFixed(2) : 0;
  t.eff.blister = effCount.blister
    ? (t.eff.blister / effCount.blister).toFixed(2)
    : 0;

  return t;
});

// Helpers
const formatNum = (num) => {
  const n = Number(num || 0);
  return !isFinite(n) || n === 0 ? "-" : n.toLocaleString("en-US");
};
const formatEff = (num) => {
  const n = Number(num || 0);
  return !isFinite(n) || n === 0 ? "-" : n.toFixed(2) + "%";
};
const effClass = (v) => {
  const n = Number(v || 0);
  if (!isFinite(n) || n <= 0) return "opacity-0"; // Hide empty chip
  if (n >= 85) return "bg-[#e7f7ee] border-[#10b981] text-[#065f46]";
  if (n >= 50) return "bg-[#fff4e5] border-[#e4940a] text-[#92400e]";
  return "bg-[#fdecec] border-[#ef4444] text-[#7f1d1d]";
};

</script>

<template>
  <div
    class="family-view w-full max-w-[1280px] mx-auto p-4 bg-[#f7f8fb] min-h-[calc(100vh-64px)] space-y-4"
  >
    <!-- Toolbar -->
    <div class="toolbar flex flex-wrap gap-4 items-end mb-6">
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500 font-medium">From (VN)</label>
        <input
          type="datetime-local"
          v-model="fromDate"
          class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm focus:outline-blue-500"
        />
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500 font-medium">To (VN)</label>
        <input
          type="datetime-local"
          v-model="toDate"
          class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm focus:outline-blue-500"
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="text-xs text-gray-500 font-medium">Family</label>
        <div class="flex items-center gap-2">
          <button
            @click="handleClear"
            class="pill-btn !py-2 !px-4 text-xs tracking-wider border rounded-full font-semibold shadow-sm hover:translate-y-[-2px] hover:shadow-md transition-all"
          >
            All
          </button>
          <select
            v-model="selectedFamily"
            @change="fetchData"
            class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm min-w-[220px] focus:outline-blue-500"
          >
            <option value="">— Select a family —</option>
            <option v-for="fam in families" :key="fam" :value="fam">
              {{ fam }}
            </option>
          </select>
        </div>
      </div>

      <div class="flex gap-2 ml-auto">
        <button
          @click="handleApply"
          :disabled="loading"
          class="pill-btn apply-btn !py-2 !px-6 text-xs tracking-wider rounded-full font-semibold shadow-sm transition-all text-white bg-gray-900 border-none hover:bg-green-500 hover:shadow-green-500/40 hover:-translate-y-1 relative"
        >
          <span :class="{ 'opacity-0': loading }">APPLY</span>
          <span
            v-if="loading"
            class="absolute inset-0 flex items-center justify-center"
          >
            <div
              class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"
            ></div>
          </span>
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
      <span class="block sm:inline">{{ error }}</span>
    </div>

    <!-- Tables Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Output Card -->
      <section
        class="card bg-white border border-gray-200 rounded-2xl shadow-[0_2px_6px_rgba(0,0,0,0.04)] overflow-hidden flex flex-col"
      >
        <header class="p-3 border-b border-gray-200 bg-gray-50">
          <h2 class="m-0 text-base font-semibold text-gray-800 tracking-wide">
            Output
          </h2>
        </header>
        <div class="p-3 flex-1 overflow-auto">
          <table class="w-full border-collapse text-sm">
            <colgroup>
              <col style="width: 40%" />
              <col style="width: 20%" />
              <col style="width: 20%" />
              <col style="width: 20%" />
            </colgroup>
            <thead class="bg-gray-50">
              <tr>
                <th
                  class="border border-gray-400 p-2 text-left font-bold text-gray-700"
                >
                  Family / Process
                </th>
                <th
                  class="border border-gray-400 p-2 text-center font-bold text-gray-700"
                >
                  Molding
                </th>
                <th
                  class="border border-gray-400 p-2 text-center font-bold text-gray-700"
                >
                  Tufting
                </th>
                <th
                  class="border border-gray-400 p-2 text-center font-bold text-gray-700"
                >
                  Blistering
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in filteredData"
                :key="row.family"
                class="hover:bg-gray-50"
              >
                <td
                  class="border border-gray-300 p-2 font-semibold text-gray-800 text-left"
                >
                  {{ row.family }}
                </td>
                <td
                  class="border border-gray-300 p-2 text-center text-gray-600 font-medium"
                >
                  {{ formatNum(row.mold.output) }}
                </td>
                <td
                  class="border border-gray-300 p-2 text-center text-gray-600 font-medium"
                >
                  {{ formatNum(row.tuft.output) }}
                </td>
                <td
                  class="border border-gray-300 p-2 text-center text-gray-600 font-medium"
                >
                  {{ formatNum(row.blister.output) }}
                </td>
              </tr>
              <tr v-if="filteredData.length === 0 && !loading">
                <td colspan="4" class="text-center p-4 text-gray-500">
                  No data found
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-100 font-bold text-gray-800">
              <tr>
                <td class="border border-gray-300 p-2 text-left">Total</td>
                <td class="border border-gray-300 p-2 text-center">
                  {{ formatNum(totals.output.mold) }}
                </td>
                <td class="border border-gray-300 p-2 text-center">
                  {{ formatNum(totals.output.tuft) }}
                </td>
                <td class="border border-gray-300 p-2 text-center">
                  {{ formatNum(totals.output.blister) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

      <!-- Efficiency Card -->
      <section
        class="card bg-white border border-gray-200 rounded-2xl shadow-[0_2px_6px_rgba(0,0,0,0.04)] overflow-hidden flex flex-col"
      >
        <header class="p-3 border-b border-gray-200 bg-gray-50">
          <h2 class="m-0 text-base font-semibold text-gray-800 tracking-wide">
            Efficiency
          </h2>
        </header>
        <div class="p-3 flex-1 overflow-auto">
          <table class="w-full border-collapse text-sm">
            <colgroup>
              <col style="width: 40%" />
              <col style="width: 20%" />
              <col style="width: 20%" />
              <col style="width: 20%" />
            </colgroup>
            <thead class="bg-gray-50">
              <tr>
                <th
                  class="border border-gray-400 p-2 text-left font-bold text-gray-700"
                >
                  Family / Process
                </th>
                <th
                  class="border border-gray-400 p-2 text-center font-bold text-gray-700"
                >
                  Molding
                </th>
                <th
                  class="border border-gray-400 p-2 text-center font-bold text-gray-700"
                >
                  Tufting
                </th>
                <th
                  class="border border-gray-400 p-2 text-center font-bold text-gray-700"
                >
                  Blistering
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in filteredData"
                :key="row.family"
                class="hover:bg-gray-50"
              >
                <td
                  class="border border-gray-300 p-2 font-semibold text-gray-800 text-left"
                >
                  {{ row.family }}
                </td>
                <td class="border border-gray-300 p-2 text-center">
                  <span
                    v-if="row.mold.eff > 0"
                    :class="[
                      'inline-block px-2 py-0.5 rounded-full border font-semibold text-xs',
                      effClass(row.mold.eff),
                    ]"
                    >{{ formatEff(row.mold.eff) }}</span
                  >
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="border border-gray-300 p-2 text-center">
                  <span
                    v-if="row.tuft.eff > 0"
                    :class="[
                      'inline-block px-2 py-0.5 rounded-full border font-semibold text-xs',
                      effClass(row.tuft.eff),
                    ]"
                    >{{ formatEff(row.tuft.eff) }}</span
                  >
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="border border-gray-300 p-2 text-center">
                  <span
                    v-if="row.blister.eff > 0"
                    :class="[
                      'inline-block px-2 py-0.5 rounded-full border font-semibold text-xs',
                      effClass(row.blister.eff),
                    ]"
                    >{{ formatEff(row.blister.eff) }}</span
                  >
                  <span v-else class="text-gray-400">-</span>
                </td>
              </tr>
              <tr v-if="filteredData.length === 0 && !loading">
                <td colspan="4" class="text-center p-4 text-gray-500">
                  No data found
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-100 font-bold text-gray-800">
              <tr>
                <td class="border border-gray-300 p-2 text-left">
                  Avg Efficiency
                </td>
                <td class="border border-gray-300 p-2 text-center">
                  <span
                    v-if="totals.eff.mold > 0"
                    :class="[
                      'inline-block px-2 py-0.5 rounded-full border font-semibold text-xs',
                      effClass(totals.eff.mold),
                    ]"
                    >{{ formatEff(totals.eff.mold) }}</span
                  >
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="border border-gray-300 p-2 text-center">
                  <span
                    v-if="totals.eff.tuft > 0"
                    :class="[
                      'inline-block px-2 py-0.5 rounded-full border font-semibold text-xs',
                      effClass(totals.eff.tuft),
                    ]"
                    >{{ formatEff(totals.eff.tuft) }}</span
                  >
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="border border-gray-300 p-2 text-center">
                  <span
                    v-if="totals.eff.blister > 0"
                    :class="[
                      'inline-block px-2 py-0.5 rounded-full border font-semibold text-xs',
                      effClass(totals.eff.blister),
                    ]"
                    >{{ formatEff(totals.eff.blister) }}</span
                  >
                  <span v-else class="text-gray-400">-</span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
/* Scoped css mapped from legacy family.html */
table,
th,
td {
  font-variant-numeric: tabular-nums;
}
</style>
