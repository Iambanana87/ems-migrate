import api from './api'

// Cache to prevent redundant requests
let permissionsCache = null
let currentUsername = null

export const permissionService = {
  async fetchPermissions(username) {
    if (!username) return []
    
    // Return cached if unchanged
    if (permissionsCache && currentUsername === username) {
      return permissionsCache
    }

    try {
      const response = await api.get('', {
        params: {
          c: 'Auth', // IAM handles permission, let's just make it call me as fallback or just nothing if no endpoint
          m: 'me',
          username: username,
          _: Date.now()
        }
      })
      
      const perms = response.data?.permissions || []
      permissionsCache = perms
      currentUsername = username
      return perms
    } catch (error) {
      console.error('Failed to fetch permissions', error)
      return []
    }
  },

  clearCache() {
    permissionsCache = null
    currentUsername = null
  }
}
