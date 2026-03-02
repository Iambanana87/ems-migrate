import api from './api'

const STORAGE_KEY = 'ems_token'

export const authService = {
  getToken() {
    return localStorage.getItem(STORAGE_KEY) || ''
  },

  setToken(token) {
    if (token) {
      localStorage.setItem(STORAGE_KEY, token)
    } else {
      localStorage.removeItem(STORAGE_KEY)
    }
  },

  clearToken() {
    this.setToken('')
  },

  async whoami() {
    try {
      const response = await api.get('', { 
        params: { 
          c: 'Auth',
          m: 'me',
          _: Date.now() 
        } 
      })
      return response.data
    } catch (error) {
      console.error('whoami fetch error:', error)
      return { logged_in: false }
    }
  },

  async login(username, password) {
    const response = await api.post('', { 
      username, 
      password 
    }, {
      params: { c: 'Auth', m: 'login' }
    })
    
    if (response.data?.token) {
      this.setToken(response.data.token)
    }
    return response.data
  },

  logout() {
    this.clearToken()
    // Optionally signal backend
    api.post('', null, { params: { c: 'Auth', m: 'logout' } }).catch(() => {})
  }
}
