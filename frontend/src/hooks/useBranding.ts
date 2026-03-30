import { useQuery } from '@tanstack/react-query'
import axios from 'axios'

export interface BrandingPayload {
  logo_customer_url: string | null
  logo_admin_url: string | null
}

export function useBranding() {
  return useQuery({
    queryKey: ['branding'],
    queryFn: async () => {
      const { data } = await axios.get<BrandingPayload>('/api/branding', {
        headers: { Accept: 'application/json' },
      })
      return data
    },
    staleTime: 5 * 60 * 1000,
  })
}
