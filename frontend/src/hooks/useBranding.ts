import { useQuery } from '@tanstack/react-query'
import publicApi from '../services/publicApi'

export interface BrandingPayload {
  logo_customer_url: string | null
  logo_admin_url: string | null
}

export function useBranding() {
  return useQuery({
    queryKey: ['branding'],
    queryFn: async () => {
      // Panel alt dizinde (ör. `/proje/panel/public/*`) çalışabilir; mutlak `/api/*`
      // çağrıları yanlış mount’a düşebiliyor. Bu yüzden göreli path kullanıyoruz.
      const { data } = await publicApi.get<BrandingPayload>('/branding')
      return data
    },
    staleTime: 5 * 60 * 1000,
  })
}
