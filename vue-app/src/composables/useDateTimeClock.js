import { ref, onMounted, onBeforeUnmount } from 'vue'

export function useDateTimeClock() {
  const currentTime = ref('')
  let timerId = null

  const updateTime = () => {
    const now = new Date()
    const dd = String(now.getDate()).padStart(2, '0')
    const mo = String(now.getMonth() + 1).padStart(2, '0')
    const yy = now.getFullYear()
    const hh = String(now.getHours()).padStart(2, '0')
    const mi = String(now.getMinutes()).padStart(2, '0')
    const ss = String(now.getSeconds()).padStart(2, '0')
    
    currentTime.value = `${dd}/${mo}/${yy}, ${hh}:${mi}:${ss}`
  }

  onMounted(() => {
    updateTime()
    timerId = setInterval(updateTime, 1000)
  })

  onBeforeUnmount(() => {
    if (timerId) clearInterval(timerId)
  })

  return { currentTime }
}
