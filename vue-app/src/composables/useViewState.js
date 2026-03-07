import { ref } from 'vue';

const currentView = ref('mold');

export function useViewState() {
  const setView = (view) => {
    currentView.value = view;
  };

  return {
    currentView,
    setView,
  };
}
