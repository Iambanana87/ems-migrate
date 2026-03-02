<script setup>
import { ref, onMounted, onUnmounted } from "vue";

const isOpen = ref(false);
const menuRef = ref(null);

// Current time mock (to be replaced by useDateTimeClock composable)
const currentTime = ref(new Date().toLocaleString());
let timer;

onMounted(() => {
  timer = setInterval(() => {
    currentTime.value = new Date().toLocaleString();
  }, 1000);

  // Close when clicking outside
  document.addEventListener("click", (e) => {
    if (menuRef.value && !menuRef.value.contains(e.target)) {
      isOpen.value = false;
    }
  });
});

onUnmounted(() => {
  clearInterval(timer);
});

const toggleMenu = () => {
  isOpen.value = !isOpen.value;
};
</script>

<template>
  <div
    class="header-right flex items-center space-x-3 relative z-[1001]"
    ref="menuRef"
  >
    <span class="text-sm font-medium text-gray-700 hidden sm:block">{{
      currentTime
    }}</span>
    <button
      @click="toggleMenu"
      class="p-2 text-gray-600 hover:text-gray-900 focus:outline-none"
    >
      <svg
        class="w-6 h-6"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M4 6h16M4 12h16M4 18h16"
        ></path>
      </svg>
    </button>

    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="transform scale-95 opacity-0"
      enter-to-class="transform scale-100 opacity-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="transform scale-100 opacity-100"
      leave-to-class="transform scale-95 opacity-0"
    >
      <ul
        v-if="isOpen"
        class="absolute right-0 top-full mt-2 w-48 bg-white border border-gray-200 rounded-md shadow-lg py-1 z-[1100]"
      >
        <li>
          <router-link
            to="/admin"
            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >System Settings</router-link
          >
        </li>
        <li>
          <router-link
            to="/family"
            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >Family</router-link
          >
        </li>
        <li>
          <router-link
            to="/dashboard"
            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >Dashboard</router-link
          >
        </li>
        <li>
          <router-link
            to="/audit"
            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >Audit Trail</router-link
          >
        </li>
        <li class="border-t my-1"></li>
        <li>
          <router-link
            to="/login"
            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >Login</router-link
          >
        </li>
        <li>
          <button
            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
          >
            Logout
          </button>
        </li>
      </ul>
    </Transition>
  </div>
</template>
