<script setup>
import { ref, computed, onMounted } from "vue";

const loading = ref(false);
const error = ref(null);
const actions = ref([]);
const activeTab = ref("timeline");
const sidebarVisible = ref(false);
const modalVisible = ref(false);
const selectedAction = ref(null);

// Filters
const filterAction = ref("");
const searchQuery = ref("");
const specificDate = ref(true);
const dateSingle = ref("");
const dateFrom = ref("");
const dateTo = ref("");

// Limit rows
const MAX_RENDER = 200;

const loadData = async () => {
  loading.value = true;
  error.value = null;
  try {
    const response = await api.get("", { params: { c: "Report", m: "actionsBoard" } });
    actions.value = response.data?.data || response.data || [];
  } catch (err) {
    console.error("Failed to load audit data", err);
    error.value = err.message || "Failed to load audit data.";
    actions.value = [];
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadData();
});

// Compute Filters
const filteredActions = computed(() => {
  let list = actions.value;

  // Type Filter
  if (filterAction.value) {
    list = list.filter((a) => a.type === filterAction.value);
  }

  // Date Filter
  if (specificDate.value && dateSingle.value) {
    const targetDate = dateSingle.value;
    list = list.filter((a) => String(a.created_at).startsWith(targetDate));
  } else if (!specificDate.value && dateFrom.value && dateTo.value) {
    list = list.filter((a) => {
      const d = String(a.created_at).slice(0, 10);
      return d >= dateFrom.value && d <= dateTo.value;
    });
  }

  // Search
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(
      (a) =>
        (a.reason || "").toLowerCase().includes(q) ||
        (a.who || "").toLowerCase().includes(q) ||
        String(a.id || "")
          .toLowerCase()
          .includes(q),
    );
  }

  return list
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
    .slice(0, MAX_RENDER);
});

// Stats
const stats = computed(() => {
  const creates = filteredActions.value.filter(
    (a) => a.type === "create",
  ).length;
  const updates = filteredActions.value.filter(
    (a) => a.type === "update",
  ).length;
  const deletes = filteredActions.value.filter(
    (a) => a.type === "delete",
  ).length;
  return { creates, updates, deletes, total: filteredActions.value.length };
});

const groupedByDate = computed(() => {
  const groups = {};
  filteredActions.value.forEach((a) => {
    const key = String(a.created_at).slice(0, 10);
    groups[key] = groups[key] || [];
    groups[key].push(a);
  });
  return Object.keys(groups)
    .sort((a, b) => new Date(b) - new Date(a))
    .reduce((acc, key) => {
      acc[key] = groups[key];
      return acc;
    }, {});
});

// Actions
const toggleSidebar = () => {
  sidebarVisible.value = !sidebarVisible.value;
};
const applyFilters = () => {
  toggleSidebar();
};
const resetFilters = () => {
  filterAction.value = "";
  searchQuery.value = "";
  specificDate.value = true;
  dateSingle.value = "";
  dateFrom.value = "";
  dateTo.value = "";
  toggleSidebar();
};

const showModal = (action) => {
  selectedAction.value = action;
  modalVisible.value = true;
};

const exportCSV = () => {
  const headers = ["ID", "Type", "Reason", "User", "Created At"];
  const rows = filteredActions.value.map((a) => [
    a.id,
    a.type,
    (a.reason || "N/A").replace(/"/g, '""'),
    a.who,
    a.created_at,
  ]);
  const csvContent = [
    headers.join(","),
    ...rows.map((row) => row.map((c) => `"${c}"`).join(",")),
  ].join("\n");
  const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = `audit_trail_${Date.now()}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

// Helpers
const shortId = (id) => String(id);
const formatTime = (ts) => {
  const d = new Date(ts);
  return isNaN(d)
    ? ""
    : d.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" });
};
const formatDate = (ts) => {
  const d = new Date(ts);
  return isNaN(d)
    ? ts
    : d.toLocaleDateString("en-US", {
        weekday: "long",
        year: "numeric",
        month: "short",
        day: "numeric",
      });
};
const capitalize = (str) =>
  str ? str.charAt(0).toUpperCase() + str.slice(1) : "";

</script>

<template>
  <div
    class="audit-trail-container min-h-[calc(100vh-64px)] bg-gradient-to-br from-[#f5f7fa] to-[#e8eef5] p-4 md:p-6 lg:p-8"
  >
    <!-- Header -->
    <div
      class="header flex flex-col md:flex-row justify-between md:items-center mb-8 gap-4"
    >
      <div class="flex items-center gap-4">
        <div
          class="w-14 h-14 bg-gradient-to-br from-[#1677ff] to-[#005adc] rounded-2xl flex items-center justify-center text-white shadow-[0_8px_16px_rgba(22,119,255,0.3)] shrink-0"
        >
          <svg
            width="32"
            height="32"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path
              d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
            ></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
        </div>
        <div>
          <h1
            class="m-0 text-2xl md:text-3xl font-bold text-gray-900 tracking-tight"
          >
            Audit Trail
          </h1>
          <p class="m-0 mt-1 text-sm text-gray-500">
            Track all system changes and modifications
          </p>
        </div>
      </div>
      <div class="flex gap-3">
        <button
          @click="exportCSV"
          class="flex-1 md:flex-none px-6 py-3 bg-white text-[#1677ff] border-2 border-[#1677ff] rounded-xl text-sm font-semibold flex items-center justify-center gap-2 hover:bg-[#1677ff] hover:text-white transition-all shadow-sm hover:-translate-y-0.5"
          type="button"
        >
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          Export CSV
        </button>
        <button
          @click="toggleSidebar"
          class="flex-1 md:flex-none px-6 py-3 bg-gradient-to-br from-[#1677ff] to-[#005adc] text-white rounded-xl text-sm font-semibold flex items-center justify-center gap-2 hover:shadow-[0_8px_16px_rgba(22,119,255,0.4)] transition-all shadow-sm hover:-translate-y-0.5"
          type="button"
        >
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <line x1="4" y1="21" x2="4" y2="14"></line>
            <line x1="4" y1="10" x2="4" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12" y2="3"></line>
            <line x1="20" y1="21" x2="20" y2="16"></line>
            <line x1="20" y1="12" x2="20" y2="3"></line>
            <line x1="1" y1="14" x2="7" y2="14"></line>
            <line x1="9" y1="8" x2="15" y2="8"></line>
            <line x1="17" y1="16" x2="23" y2="16"></line>
          </svg>
          Filters
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
      <span class="block sm:inline">{{ error }}</span>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
      <div
        class="bg-white p-6 rounded-2xl flex items-center gap-4 shadow-[0_2px_12px_rgba(0,0,0,0.06)] hover:shadow-[0_8px_24px_rgba(0,0,0,0.12)] hover:-translate-y-1 transition-all"
      >
        <div
          class="w-12 h-12 rounded-xl flex items-center justify-center text-white bg-gradient-to-br from-[#10b981] to-[#059669]"
        >
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
        </div>
        <div>
          <div class="text-3xl font-bold text-gray-900 leading-none">
            {{ stats.creates }}
          </div>
          <div class="text-sm text-gray-500 mt-1">Creates</div>
        </div>
      </div>
      <div
        class="bg-white p-6 rounded-2xl flex items-center gap-4 shadow-[0_2px_12px_rgba(0,0,0,0.06)] hover:shadow-[0_8px_24px_rgba(0,0,0,0.12)] hover:-translate-y-1 transition-all"
      >
        <div
          class="w-12 h-12 rounded-xl flex items-center justify-center text-white bg-gradient-to-br from-[#f59e0b] to-[#d97706]"
        >
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <polyline points="23 4 23 10 17 10"></polyline>
            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
          </svg>
        </div>
        <div>
          <div class="text-3xl font-bold text-gray-900 leading-none">
            {{ stats.updates }}
          </div>
          <div class="text-sm text-gray-500 mt-1">Updates</div>
        </div>
      </div>
      <div
        class="bg-white p-6 rounded-2xl flex items-center gap-4 shadow-[0_2px_12px_rgba(0,0,0,0.06)] hover:shadow-[0_8px_24px_rgba(0,0,0,0.12)] hover:-translate-y-1 transition-all"
      >
        <div
          class="w-12 h-12 rounded-xl flex items-center justify-center text-white bg-gradient-to-br from-[#ef4444] to-[#dc2626]"
        >
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <polyline points="3 6 5 6 21 6"></polyline>
            <path
              d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
            ></path>
          </svg>
        </div>
        <div>
          <div class="text-3xl font-bold text-gray-900 leading-none">
            {{ stats.deletes }}
          </div>
          <div class="text-sm text-gray-500 mt-1">Deletes</div>
        </div>
      </div>
      <div
        class="bg-white p-6 rounded-2xl flex items-center gap-4 shadow-[0_2px_12px_rgba(0,0,0,0.06)] hover:shadow-[0_8px_24px_rgba(0,0,0,0.12)] hover:-translate-y-1 transition-all"
      >
        <div
          class="w-12 h-12 rounded-xl flex items-center justify-center text-white bg-gradient-to-br from-[#1677ff] to-[#005adc]"
        >
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"></path>
          </svg>
        </div>
        <div>
          <div class="text-3xl font-bold text-gray-900 leading-none">
            {{ stats.total }}
          </div>
          <div class="text-sm text-gray-500 mt-1">Total Actions</div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div
      class="flex gap-2 mb-6 bg-white p-1.5 rounded-xl shadow-[0_2px_8px_rgba(0,0,0,0.06)] w-fit"
    >
      <button
        @click="activeTab = 'timeline'"
        :class="[
          'px-6 py-2.5 rounded-lg text-sm font-semibold flex items-center gap-2 transition-all',
          activeTab === 'timeline'
            ? 'bg-gradient-to-br from-[#1677ff] to-[#005adc] text-white shadow-[0_4px_12px_rgba(22,119,255,0.3)]'
            : 'text-gray-500 hover:text-[#1677ff] hover:bg-blue-50',
        ]"
      >
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
        Timeline View
      </button>
      <button
        @click="activeTab = 'table'"
        :class="[
          'px-6 py-2.5 rounded-lg text-sm font-semibold flex items-center gap-2 transition-all',
          activeTab === 'table'
            ? 'bg-gradient-to-br from-[#1677ff] to-[#005adc] text-white shadow-[0_4px_12px_rgba(22,119,255,0.3)]'
            : 'text-gray-500 hover:text-[#1677ff] hover:bg-blue-50',
        ]"
      >
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="3" y1="9" x2="21" y2="9"></line>
          <line x1="9" y1="21" x2="9" y2="9"></line>
        </svg>
        Table View
      </button>
    </div>

    <!-- Content Area: Timeline -->
    <div
      v-show="activeTab === 'timeline'"
      class="bg-white rounded-2xl p-6 shadow-[0_2px_12px_rgba(0,0,0,0.06)] min-h-[500px]"
      :class="{ 'opacity-60 pointer-events-none': loading }"
    >
      <div
        v-if="filteredActions.length === 0"
        class="text-center p-8 text-gray-500"
      >
        No audit records found.
      </div>
      <div v-for="(group, date) in groupedByDate" :key="date" class="mb-10">
        <div class="mb-5">
          <span
            class="inline-block px-5 py-2 bg-gradient-to-br from-[#1677ff] to-[#005adc] text-white rounded-full text-sm font-semibold shadow-[0_4px_12px_rgba(22,119,255,0.3)]"
            >{{ formatDate(date) }}</span
          >
        </div>
        <div class="relative">
          <div
            v-for="(action, index) in group"
            :key="action.id"
            class="flex flex-col md:flex-row gap-4 mb-4 cursor-pointer group hover:translate-x-2 transition-all"
            @click="showModal(action)"
          >
            <div
              class="flex md:flex-col items-center relative w-full md:w-auto"
            >
              <!-- Dot Indicator -->
              <div
                class="w-4 h-4 rounded-full border-[3px] border-white shadow-[0_2px_8px_rgba(0,0,0,0.15)] z-10 shrink-0"
                :class="{
                  'bg-[#10b981]': action.type === 'create',
                  'bg-[#f59e0b]': action.type === 'update',
                  'bg-[#ef4444]': action.type === 'delete',
                }"
              ></div>
              <!-- Vertical Line for md+ -->
              <div
                v-if="index !== group.length - 1"
                class="hidden md:block w-[2px] flex-1 bg-slate-200 mt-1"
              ></div>
              <!-- Horizontal line for mobile -->
              <div class="md:hidden w-full h-[2px] bg-slate-200 mx-3"></div>
            </div>

            <div
              class="flex-1 bg-slate-50 p-5 rounded-xl border-l-4 transition-all group-hover:bg-white group-hover:shadow-[0_4px_16px_rgba(0,0,0,0.1)]"
              :class="{
                'border-l-[#10b981]': action.type === 'create',
                'border-l-[#f59e0b]': action.type === 'update',
                'border-l-[#ef4444]': action.type === 'delete',
              }"
            >
              <div class="flex justify-between items-center mb-3">
                <div class="flex items-center gap-3">
                  <span
                    class="px-3 py-1 rounded-md text-xs font-semibold uppercase tracking-wider"
                    :class="{
                      'bg-[#d1fae5] text-[#065f46]': action.type === 'create',
                      'bg-[#fef3c7] text-[#92400e]': action.type === 'update',
                      'bg-[#fee2e2] text-[#991b1b]': action.type === 'delete',
                    }"
                    >{{ capitalize(action.type) }}</span
                  >
                  <span class="text-sm text-slate-500 font-medium">{{
                    formatTime(action.created_at)
                  }}</span>
                </div>
                <button
                  class="px-3 py-1.5 bg-[#1677ff] text-white border-none rounded-md cursor-pointer flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-all hover:bg-[#005adc] hover:scale-105 text-sm"
                  @click.stop="showModal(action)"
                >
                  <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path
                      d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                    ></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  View
                </button>
              </div>
              <div class="text-[15px] text-slate-700 mb-3 leading-relaxed">
                {{ action.reason }}
              </div>
              <div class="flex items-center gap-1.5 text-sm text-slate-500">
                <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
                {{ action.who }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Content Area: Table View -->
    <div
      v-show="activeTab === 'table'"
      class="bg-white rounded-2xl p-6 shadow-[0_2px_12px_rgba(0,0,0,0.06)] min-h-[500px]"
      :class="{ 'opacity-60 pointer-events-none': loading }"
    >
      <div class="overflow-x-auto">
        <table class="w-full border-collapse">
          <thead>
            <tr>
              <th
                class="bg-slate-50 p-3 text-left font-semibold text-slate-600 text-sm uppercase tracking-wider border-b-2 border-slate-200"
              >
                ID
              </th>
              <th
                class="bg-slate-50 p-3 text-left font-semibold text-slate-600 text-sm uppercase tracking-wider border-b-2 border-slate-200"
              >
                Type
              </th>
              <th
                class="bg-slate-50 p-3 text-left font-semibold text-slate-600 text-sm uppercase tracking-wider border-b-2 border-slate-200"
              >
                Reason
              </th>
              <th
                class="bg-slate-50 p-3 text-left font-semibold text-slate-600 text-sm uppercase tracking-wider border-b-2 border-slate-200"
              >
                User
              </th>
              <th
                class="bg-slate-50 p-3 text-left font-semibold text-slate-600 text-sm uppercase tracking-wider border-b-2 border-slate-200"
              >
                Timestamp
              </th>
              <th
                class="bg-slate-50 p-3 text-left font-semibold text-slate-600 text-sm uppercase tracking-wider border-b-2 border-slate-200"
              >
                Actions
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredActions.length === 0">
              <td colspan="6" class="text-center p-8 text-gray-500">
                No audit records found
              </td>
            </tr>
            <tr
              v-for="action in filteredActions"
              :key="action.id"
              class="hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-0"
            >
              <td class="p-3 font-mono font-semibold text-[#1677ff]">
                #{{ shortId(action.id) }}
              </td>
              <td class="p-3">
                <span
                  class="px-3 py-1 rounded-md text-xs font-semibold uppercase tracking-wider"
                  :class="{
                    'bg-[#d1fae5] text-[#065f46]': action.type === 'create',
                    'bg-[#fef3c7] text-[#92400e]': action.type === 'update',
                    'bg-[#fee2e2] text-[#991b1b]': action.type === 'delete',
                  }"
                  >{{ capitalize(action.type) }}</span
                >
              </td>
              <td class="p-3 max-w-[300px] truncate text-slate-700">
                {{ action.reason }}
              </td>
              <td class="p-3 text-slate-600 font-medium">{{ action.who }}</td>
              <td class="p-3 text-sm whitespace-nowrap">
                {{ formatDate(action.created_at) }}
                <span class="text-slate-400">{{
                  formatTime(action.created_at)
                }}</span>
              </td>
              <td class="p-3">
                <button
                  @click="showModal(action)"
                  class="px-4 py-2 bg-[#1677ff] text-white rounded-lg font-semibold text-sm flex items-center gap-1.5 hover:bg-[#005adc] hover:-translate-y-0.5 hover:shadow-[0_4px_12px_rgba(22,119,255,0.3)] transition-all"
                >
                  <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path
                      d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                    ></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  View Diff
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Sidebar Filters Overlay -->
    <div
      v-if="sidebarVisible"
      class="fixed inset-0 bg-black/50 z-[1000] flex justify-end backdrop-blur-sm"
      @click.self="toggleSidebar"
    >
      <div
        class="w-[380px] max-w-[90vw] bg-white h-full overflow-y-auto p-8 shadow-[-4px_0_24px_rgba(0,0,0,0.15)] transform transition-transform animate-slideInRight"
      >
        <div
          class="flex justify-between items-center mb-8 pb-4 border-b-2 border-slate-100"
        >
          <h3
            class="m-0 text-2xl font-bold text-gray-900 flex items-center gap-3"
          >
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <line x1="4" y1="21" x2="4" y2="14"></line>
              <line x1="4" y1="10" x2="4" y2="3"></line>
              <line x1="12" y1="21" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12" y2="3"></line>
              <line x1="20" y1="21" x2="20" y2="16"></line>
              <line x1="20" y1="12" x2="20" y2="3"></line>
            </svg>
            Filters
          </h3>
          <button
            @click="toggleSidebar"
            class="w-9 h-9 bg-slate-100 text-slate-500 rounded-lg flex items-center justify-center hover:bg-slate-200 hover:text-slate-800 transition-colors"
          >
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>

        <div class="space-y-6">
          <!-- Action Type -->
          <div>
            <label
              class="block text-[13px] font-semibold text-slate-600 mb-2 uppercase tracking-wide"
              >Action Type</label
            >
            <select
              v-model="filterAction"
              class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl text-sm text-slate-700 bg-white focus:outline-none focus:border-[#1677ff] focus:ring-4 focus:ring-[#1677ff]/10 transition-all"
            >
              <option value="">All Actions</option>
              <option value="create">Create</option>
              <option value="update">Update</option>
              <option value="delete">Delete</option>
            </select>
          </div>

          <!-- Search -->
          <div>
            <label
              class="block text-[13px] font-semibold text-slate-600 mb-2 uppercase tracking-wide"
              >Search</label
            >
            <div class="relative flex items-center">
              <svg
                class="absolute left-4 text-slate-400"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
              </svg>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search by reason, user, ID..."
                class="w-full py-3 pr-4 pl-11 border-2 border-slate-200 rounded-xl text-sm text-slate-700 bg-white focus:outline-none focus:border-[#1677ff] focus:ring-4 focus:ring-[#1677ff]/10 transition-all"
              />
            </div>
          </div>

          <!-- Date Toggle -->
          <div>
            <label class="flex items-center gap-3 cursor-pointer py-3">
              <!-- Custom Toggle inspired by layout -->
              <input type="checkbox" v-model="specificDate" class="hidden" />
              <div
                class="w-12 h-[26px] rounded-full relative transition-colors duration-300"
                :class="specificDate ? 'bg-[#1677ff]' : 'bg-slate-300'"
              >
                <div
                  class="w-5 h-5 bg-white rounded-full absolute top-[3px] left-[3px] shadow-[0_2px_4px_rgba(0,0,0,0.2)] transition-transform duration-300"
                  :class="specificDate ? 'translate-x-[22px]' : 'translate-x-0'"
                ></div>
              </div>
              <span>Change date type</span>
            </label>
          </div>

          <!-- Date Inputs -->
          <div v-show="specificDate">
            <label
              class="block text-[13px] font-semibold text-slate-600 mb-2 uppercase tracking-wide"
              >Date</label
            >
            <input
              v-model="dateSingle"
              type="date"
              class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl text-sm text-slate-700 bg-white focus:outline-none focus:border-[#1677ff] focus:ring-4 focus:ring-[#1677ff]/10 transition-all"
            />
          </div>

          <div v-show="!specificDate" class="space-y-2">
            <label
              class="block text-[13px] font-semibold text-slate-600 mb-2 uppercase tracking-wide"
              >Date Range</label
            >
            <input
              v-model="dateFrom"
              type="date"
              placeholder="From"
              class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl text-sm text-slate-700 bg-white focus:outline-none focus:border-[#1677ff] focus:ring-4 focus:ring-[#1677ff]/10 transition-all"
            />
            <input
              v-model="dateTo"
              type="date"
              placeholder="To"
              class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl text-sm text-slate-700 bg-white focus:outline-none focus:border-[#1677ff] focus:ring-4 focus:ring-[#1677ff]/10 transition-all"
            />
          </div>

          <!-- Buttons -->
          <div class="pt-4 space-y-3">
            <button
              @click="applyFilters"
              class="w-full py-3.5 bg-gradient-to-br from-[#1677ff] to-[#005adc] text-white rounded-xl font-semibold flex items-center justify-center gap-2 shadow-[0_4px_12px_rgba(22,119,255,0.3)] hover:-translate-y-0.5 hover:shadow-[0_8px_20px_rgba(22,119,255,0.4)] transition-all"
            >
              <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              Apply Filters
            </button>
            <button
              @click="resetFilters"
              class="w-full py-3.5 bg-white border-2 border-slate-200 text-slate-500 rounded-xl font-semibold flex items-center justify-center gap-2 hover:bg-slate-50 hover:border-slate-300 transition-all"
            >
              <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <polyline points="1 4 1 10 7 10"></polyline>
                <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
              </svg>
              Reset Filters
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Details Modal Overlay -->
    <div
      v-if="modalVisible && selectedAction"
      class="fixed inset-0 bg-black/60 z-[2000] flex items-center justify-center p-6 backdrop-blur-sm"
      @click.self="modalVisible = false"
    >
      <div
        class="bg-white rounded-[20px] w-full max-w-[900px] max-h-[90vh] flex flex-col shadow-[0_20px_60px_rgba(0,0,0,0.3)] animate-scaleIn"
      >
        <div
          class="flex justify-between items-center p-6 md:px-8 border-b-2 border-slate-100 bg-slate-50 rounded-t-[20px]"
        >
          <h3
            class="m-0 text-xl font-bold text-gray-900 flex items-center gap-3"
          >
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"
              ></path>
            </svg>
            Audit Diff - Record #{{ shortId(selectedAction.id) }}
          </h3>
          <button
            @click="modalVisible = false"
            class="w-10 h-10 bg-white border-none shadow-[0_2px_8px_rgba(0,0,0,0.1)] text-slate-500 rounded-xl flex items-center justify-center hover:bg-red-50 hover:text-red-600 hover:rotate-90 transition-all cursor-pointer"
          >
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>

        <div class="p-8 overflow-y-auto w-full">
          <!-- Overview -->
          <div
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-5 bg-slate-50 rounded-xl mb-8"
          >
            <div class="flex flex-col gap-1.5">
              <strong class="text-xs text-slate-500 uppercase tracking-wider"
                >Type</strong
              >
              <span
                class="w-fit px-3 py-1 rounded-md text-xs font-semibold uppercase tracking-wider bg-white border"
                :class="{
                  'border-[#10b981] text-[#065f46]':
                    selectedAction.type === 'create',
                  'border-[#f59e0b] text-[#92400e]':
                    selectedAction.type === 'update',
                  'border-[#ef4444] text-[#991b1b]':
                    selectedAction.type === 'delete',
                }"
                >{{ capitalize(selectedAction.type) }}</span
              >
            </div>
            <div class="flex flex-col gap-1.5">
              <strong class="text-xs text-slate-500 uppercase tracking-wider"
                >Reason</strong
              >
              <span class="text-sm text-slate-700">{{
                selectedAction.reason
              }}</span>
            </div>
            <div class="flex flex-col gap-1.5">
              <strong class="text-xs text-slate-500 uppercase tracking-wider"
                >User</strong
              >
              <span class="text-sm text-slate-700 font-medium">{{
                selectedAction.who
              }}</span>
            </div>
            <div class="flex flex-col gap-1.5">
              <strong class="text-xs text-slate-500 uppercase tracking-wider"
                >Created</strong
              >
              <span class="text-sm text-slate-700">{{
                selectedAction.created_at
              }}</span>
            </div>
          </div>

          <!-- Raw Diff Representation (Simulated) -->
          <div class="mb-8 w-full">
            <h4
              class="text-lg font-bold text-gray-900 m-0 mb-5 pb-3 border-b-2 border-slate-200 flex items-center gap-2"
            >
              Field Changes
            </h4>
            <div
              class="border border-slate-200 rounded-xl overflow-hidden shadow-[0_2px_8px_rgba(0,0,0,0.08)] w-full w-max-full"
            >
              <table class="w-full border-collapse">
                <thead>
                  <tr class="bg-[#1677ff] text-white">
                    <th
                      class="p-4 text-left font-semibold text-[13px] uppercase tracking-wider"
                    >
                      Field
                    </th>
                    <th
                      class="p-4 text-left font-semibold text-[13px] uppercase tracking-wider"
                    >
                      Before
                    </th>
                    <th
                      class="p-4 text-left font-semibold text-[13px] uppercase tracking-wider"
                    >
                      After
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white">
                  <tr>
                    <td
                      class="p-4 border-b border-slate-100 font-bold text-slate-700"
                    >
                      status
                    </td>
                    <td
                      class="p-4 border-b border-slate-100 font-mono text-sm text-red-600 bg-red-50"
                    >
                      - offline
                    </td>
                    <td
                      class="p-4 border-b border-slate-100 font-mono text-sm text-green-600 bg-green-50"
                    >
                      + online
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes slideInRight {
  from {
    transform: translateX(100%);
  }
  to {
    transform: translateX(0);
  }
}
.animate-slideInRight {
  animation: slideInRight 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes scaleIn {
  from {
    transform: scale(0.95);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}
.animate-scaleIn {
  animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
