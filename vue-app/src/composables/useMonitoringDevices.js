import { ref, computed } from 'vue'
import { deviceApi } from '../api/deviceApi'

// --- Singleton State ---
const moldDevices = ref([])
const tuftDevices = ref([])
const blisterDevices = ref([])
const loading = ref(true)
const error = ref(null)

const calculateStats = (devices) => {
  return {
    total: devices.length,
    connected: devices.filter(d => d.status === 'Normal' || d.status === 'Breached').length,
    breached: devices.filter(d => d.status === 'Breached').length,
    disconnected: devices.filter(d => d.status === 'DISCONNECTED').length,
    flexible: devices.filter(d => d.flex === '1').length,
    action: devices.reduce((acc, d) => acc + (parseInt(d.action_count_open) || 0), 0)
  }
}

const moldStats = computed(() => calculateStats(moldDevices.value))
const tuftStats = computed(() => calculateStats(tuftDevices.value))
const blisterStats = computed(() => calculateStats(blisterDevices.value))

function patchArray(targetRef, newArray) {
  if (!targetRef.value.length) {
    targetRef.value = newArray || []
    return
  }
  const incoming = newArray || []
  const incomingIds = new Set(incoming.map(d => d.device_id))

  // Remove elements no longer present
  for (let i = targetRef.value.length - 1; i >= 0; i--) {
    if (!incomingIds.has(targetRef.value[i].device_id)) {
      targetRef.value.splice(i, 1)
    }
  }

  // Update existing or add new
  incoming.forEach((newItem, incomingIndex) => {
    const existingIndex = targetRef.value.findIndex(d => d.device_id === newItem.device_id)
    if (existingIndex !== -1) {
      const existingItem = targetRef.value[existingIndex]
      for (const key in newItem) {
        if (key === 'live_data' && existingItem.live_data && newItem.live_data) {
          Object.assign(existingItem.live_data, newItem.live_data)
        } else {
          existingItem[key] = newItem[key]
        }
      }
      if (existingIndex !== incomingIndex) {
        const [movedItem] = targetRef.value.splice(existingIndex, 1)
        targetRef.value.splice(incomingIndex, 0, movedItem)
      }
    } else {
      targetRef.value.splice(incomingIndex, 0, newItem)
    }
  })
}

export function useMonitoringDevices() {
  const refreshDevices = async () => {
    if (!moldDevices.value.length && !tuftDevices.value.length && !blisterDevices.value.length) {
      loading.value = true
    }
    error.value = null
    try {
      const response = await deviceApi.getDevices()
      const devicesData = response.data?.devices || {}
      patchArray(moldDevices, devicesData.mold || [])
      patchArray(tuftDevices, devicesData.tuft || [])
      patchArray(blisterDevices, devicesData.blister || [])
    } catch (err) {
      console.error('Failed to fetch devices', err)
      error.value = err.message || 'Failed to load devices.'
    } finally {
      loading.value = false
    }
  }

  return {
    moldDevices,
    tuftDevices,
    blisterDevices,
    moldStats,
    tuftStats,
    blisterStats,
    loading,
    error,
    refreshDevices
  }
}
