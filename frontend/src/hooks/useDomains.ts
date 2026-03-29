import { useQuery } from '@tanstack/react-query'
import api from '../services/api'

export type DomainOption = { id: number; name: string }

/** API sayfalı cevabı veya düz dizi; cache karışımına karşı güvenli. */
function normalizeDomainOptions(raw: unknown): DomainOption[] {
  if (Array.isArray(raw)) return raw as DomainOption[]
  if (raw && typeof raw === 'object' && 'data' in raw) {
    const inner = (raw as { data: unknown }).data
    if (Array.isArray(inner)) return inner as DomainOption[]
  }
  return []
}

export function useDomainsList() {
  return useQuery({
    queryKey: ['domains', 'options'],
    queryFn: async () => (await api.get('/domains')).data,
    select: (raw) => normalizeDomainOptions(raw),
  })
}
