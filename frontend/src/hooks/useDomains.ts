import { useQuery } from '@tanstack/react-query'
import api from '../services/api'

export type DomainOption = { id: number; name: string }

export function useDomainsList() {
  return useQuery({
    queryKey: ['domains'],
    queryFn: async () => {
      const { data } = await api.get('/domains')
      return (data?.data ?? []) as DomainOption[]
    },
  })
}
