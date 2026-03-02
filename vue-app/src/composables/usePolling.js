import { onMounted, onBeforeUnmount } from 'vue'

export function usePolling(callback, intervalMs) {
  let intervalId = null

  const start = () => {
    if (intervalId) return
    callback() // Execute immediately
    intervalId = setInterval(callback, intervalMs)
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

  onBeforeUnmount(() => {
    stop()
  })

  return { start, stop }
}
