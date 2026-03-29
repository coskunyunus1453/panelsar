import api from './api'
import type { DashboardData, ServiceInfo, SystemStats } from '../types'

export const dashboardService = {
  getData: async (): Promise<{ dashboard: DashboardData }> => {
    const { data } = await api.get('/dashboard')
    return data
  },

  getSystemStats: async (): Promise<{ stats: SystemStats }> => {
    const { data } = await api.get('/system/stats')
    return data
  },

  getServices: async (): Promise<{ services: ServiceInfo[] }> => {
    const { data } = await api.get('/system/services')
    return data
  },

  controlService: async (name: string, action: string): Promise<{ message: string }> => {
    const { data } = await api.post(`/system/services/${name}`, { action })
    return data
  },
}
