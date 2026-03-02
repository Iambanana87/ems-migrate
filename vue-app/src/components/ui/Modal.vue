<script setup>
import { onMounted, onUnmounted } from "vue";

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  title: {
    type: String,
    default: "",
  },
  maxWidth: {
    type: String,
    default: "max-w-4xl", // Support max-w-8xl for ChartModal later
  },
});

const emit = defineEmits(["close"]);

const close = () => {
  emit("close");
};

// Close on Escape key
const handleKeydown = (e) => {
  if (e.key === "Escape" && props.isOpen) {
    close();
  }
};

onMounted(() => {
  document.addEventListener("keydown", handleKeydown);
});

onUnmounted(() => {
  document.removeEventListener("keydown", handleKeydown);
});
</script>

<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
    >
      <!-- Overlay -->
      <div
        class="fixed inset-0 bg-black bg-opacity-60 transition-opacity"
        @click="close"
      ></div>

      <!-- Modal Panel -->
      <div
        class="bg-gray-100 rounded-lg shadow-xl w-full flex flex-col p-4 relative z-[10000] max-h-[90vh] overflow-hidden"
        :class="maxWidth"
        @click.stop
      >
        <button
          @click="close"
          class="absolute top-2 right-4 text-gray-500 hover:text-gray-800 text-4xl font-bold leading-none z-10 focus:outline-none"
        >
          &times;
        </button>

        <div class="flex-shrink-0 mb-4 pr-12">
          <slot name="header">
            <h3 v-if="title" class="text-xl font-bold text-gray-800">
              {{ title }}
            </h3>
          </slot>
        </div>

        <div class="flex-grow overflow-y-auto">
          <slot name="body"></slot>
        </div>

        <div v-if="$slots.footer" class="flex-shrink-0 mt-4 border-t pt-4">
          <slot name="footer"></slot>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
/* Scoped styles minimal, handled by Tailwind */
</style>
