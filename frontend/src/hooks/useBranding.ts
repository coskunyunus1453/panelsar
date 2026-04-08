import { useQuery } from '@tanstack/react-query'
import publicApi from '../services/publicApi'

/** Oturum boyunca ?wl= ile seçilen bayi (aynı origin üzerinde white-label). */
export const WL_SESSION_KEY = 'hostvim-wl-slug'

export interface BrandingPayload {
  logo_customer_url: string | null
  logo_admin_url: string | null
  primary_color?: string | null
  secondary_color?: string | null
  login_title?: string | null
  login_subtitle?: string | null
  white_label_slug?: string | null
}

export function useBranding() {
  const wl =
    typeof window !== 'undefined' ? sessionStorage.getItem(WL_SESSION_KEY)?.trim() || '' : ''

  return useQuery({
    queryKey: ['branding', wl],
    queryFn: async () => {
      const { data } = await publicApi.get<BrandingPayload>('/branding', {
        params: wl ? { wl } : {},
      })
      return data
    },
    staleTime: 5 * 60 * 1000,
  })
}
