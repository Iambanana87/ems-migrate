import api from '../services/api'

export const deviceApi = {
  getDevices() {
    return api.get('', {
      params: {
        c: 'Device',
        m: 'getDevices'
      }
    })
  }
}
