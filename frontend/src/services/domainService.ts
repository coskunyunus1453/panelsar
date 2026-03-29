import api from './api'
import type { Domain, PaginatedResponse } from '../types'

export const domainService = {
  list: async (page = 1): Promise<PaginatedResponse<Domain>> => {
    const { data } = await api.get(`/domains?page=${page}`)
    return data
  },

  get: async (id: number): Promise<{ domain: Domain }> => {
    const { data } = await api.get(`/domains/${id}`)
    return data
  },

  create: async (payload: {
    name: string
    php_version?: string
    server_type?: string
  }): Promise<{ domain: Domain; message: string }> => {
    const { data } = await api.post('/domains', payload)
    return data
  },

  delete: async (id: number): Promise<{ message: string }> => {
    const { data } = await api.delete(`/domains/${id}`)
    return data
  },

  switchPhp: async (id: number, php_version: string): Promise<{ domain: Domain }> => {
    const { data } = await api.post(`/domains/${id}/php`, { php_version })
    return data
  },
}
