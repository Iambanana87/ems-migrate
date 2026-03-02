import { ref, onMounted } from 'vue'
import { permissionService } from '../services/permissionService'
import { authService } from '../services/authService'

export function usePermissions() {
  const permissions = ref([])
  const isReady = ref(false)

  const init = async () => {
    const user = await authService.whoami()
    if (user?.username && user?.logged_in) {
      permissions.value = await permissionService.fetchPermissions(user.username)
    }
    isReady.value = true
  }

  const can = (code) => {
    return permissions.value.includes(code)
  }

  const canAny = (codesArray) => {
    return codesArray.some(code => permissions.value.includes(code))
  }

  const canAll = (codesArray) => {
    return codesArray.every(code => permissions.value.includes(code))
  }

  onMounted(() => {
    if (!isReady.value) {
      init()
    }
  })

  return {
    permissions,
    isReady,
    can,
    canAny,
    canAll,
    init
  }
}
