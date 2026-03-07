import { onMounted, onUnmounted, ref } from 'vue'

export function usePolling(callback, intervalMs) {
  let intervalId = null
  const isFetching = ref(false)

  const wrappedCallback = async () => {
    if (isFetching.value) return // Prevent overlapping requests
    
    isFetching.value = true
    try {
      await callback()
    } finally {
      isFetching.value = false
    }
  }

  const start = () => {
    if (intervalId) return
    wrappedCallback() // Execute immediately
    intervalId = setInterval(wrappedCallback, intervalMs)
  }

  const stop = () => {
    if (intervalId) {
      clearInterval(intervalId)
      intervalId = null
    }
  }

  onMounted(() => {
    start()
  })

  // Ensure polling stops when component unmounts
  onUnmounted(() => {
    stop()
  })

  return { start, stop, isFetching }
}
