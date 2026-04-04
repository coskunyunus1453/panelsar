import { useEffect, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery } from '@tanstack/react-query'
import api from '../services/api'
import { CreditCard } from 'lucide-react'
import toast from 'react-hot-toast'
import { safeExternalHttpUrl } from '../lib/urlSafety'

type PackageRow = {
  id: number
  name: string
  description: string | null
  price_monthly: string | number
  price_yearly: string | number
  currency: string
  is_active: boolean
}

type SubRow = {
  id: number
  status: string
  billing_cycle: string
  amount: string | number
  currency: string
  hosting_package?: { name: string }
}

export default function BillingPage() {
  const { t } = useTranslation()
  const [searchParams] = useSearchParams()
  const autoCheckoutStarted = useRef(false)

  const isAllowedCheckoutHost = (url: string): boolean => {
    try {
      const u = new URL(url)
      const host = u.hostname.toLowerCase()
      const appHost = window.location.hostname.toLowerCase()
      return host === appHost || host === 'checkout.stripe.com' || host.endsWith('.stripe.com')
    } catch {
      return false
    }
  }

  const pkgs = useQuery({
    queryKey: ['billing-packages'],
    queryFn: async () => (await api.get('/billing/packages')).data as { packages: PackageRow[] },
  })

  const subs = useQuery({
    queryKey: ['billing-subs'],
    queryFn: async () => (await api.get('/billing/subscriptions')).data,
  })

  const checkoutM = useMutation({
    mutationFn: async (payload: { package_id: number; billing_cycle: 'monthly' | 'yearly' }) =>
      api.post('/billing/checkout', {
        ...payload,
        success_url: `${window.location.origin}/billing`,
        cancel_url: `${window.location.origin}/billing`,
      }),
    onSuccess: (res) => {
      const raw = (res.data as { url?: string })?.url
      const url = raw ? safeExternalHttpUrl(raw) : null
      if (url && isAllowedCheckoutHost(url)) {
        window.location.href = url
      } else if (raw) {
        toast.error('Güvensiz checkout URL engellendi')
      } else {
        toast.success('Demo: Stripe yapılandırılmadı')
      }
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; demo?: boolean } } }
      const msg = ax.response?.data?.message
      if (ax.response?.data?.demo) toast(msg ?? t('billing.demo_mode'))
      else toast.error(msg ?? String(err))
    },
  })

  useEffect(() => {
    if (searchParams.get('autoCheckout') !== '1' || autoCheckoutStarted.current) {
      return
    }
    const raw = sessionStorage.getItem('pendingCheckout')
    if (!raw) {
      return
    }
    autoCheckoutStarted.current = true
    sessionStorage.removeItem('pendingCheckout')
    try {
      const p = JSON.parse(raw) as { package_id: number; billing_cycle: 'monthly' | 'yearly' }
      if (typeof p.package_id === 'number' && (p.billing_cycle === 'monthly' || p.billing_cycle === 'yearly')) {
        checkoutM.mutate(p)
      }
    } catch {
      /* ignore */
    }
  }, [searchParams, checkoutM])

  const packages = pkgs.data?.packages ?? []
  const subRows: SubRow[] = subs.data?.data ?? []

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <CreditCard className="h-8 w-8 text-emerald-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('billing.title')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('billing.subtitle')}</p>
        </div>
      </div>

      <div>
        <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-3">{t('billing.packages')}</h2>
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {packages.map((p) => (
            <div key={p.id} className="card p-5 flex flex-col gap-3">
              <div>
                <h3 className="font-semibold text-gray-900 dark:text-white">{p.name}</h3>
                {p.description && (
                  <p className="text-sm text-gray-500 mt-1 line-clamp-3">{p.description}</p>
                )}
              </div>
              <div className="text-sm text-gray-600 dark:text-gray-400">
                <p>
                  Aylık: {p.price_monthly} {p.currency}
                </p>
                <p>
                  Yıllık: {p.price_yearly} {p.currency}
                </p>
              </div>
              <div className="flex gap-2 mt-auto">
                <button
                  type="button"
                  className="btn-primary text-sm flex-1"
                  disabled={!p.is_active || checkoutM.isPending}
                  onClick={() => checkoutM.mutate({ package_id: p.id, billing_cycle: 'monthly' })}
                >
                  Aylık
                </button>
                <button
                  type="button"
                  className="btn-secondary text-sm flex-1"
                  disabled={!p.is_active || checkoutM.isPending}
                  onClick={() => checkoutM.mutate({ package_id: p.id, billing_cycle: 'yearly' })}
                >
                  Yıllık
                </button>
              </div>
            </div>
          ))}
        </div>
        {pkgs.isLoading && <p className="text-gray-500 py-4">{t('common.loading')}</p>}
        {!pkgs.isLoading && packages.length === 0 && (
          <p className="text-gray-500 py-4">{t('common.no_data')}</p>
        )}
      </div>

      <div className="card overflow-hidden">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-white px-4 py-3 border-b border-gray-100 dark:border-gray-800">
          {t('billing.subscriptions')}
        </h2>
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-800/80">
            <tr>
              <th className="text-left px-4 py-2">Paket</th>
              <th className="text-left px-4 py-2">Döngü</th>
              <th className="text-left px-4 py-2">Tutar</th>
              <th className="text-left px-4 py-2">{t('common.status')}</th>
            </tr>
          </thead>
          <tbody>
            {subRows.map((s) => (
              <tr key={s.id} className="border-t border-gray-100 dark:border-gray-800">
                <td className="px-4 py-2">{s.hosting_package?.name ?? '—'}</td>
                <td className="px-4 py-2">{s.billing_cycle}</td>
                <td className="px-4 py-2">
                  {s.amount} {s.currency}
                </td>
                <td className="px-4 py-2">{s.status}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {subs.isLoading && <p className="p-4 text-gray-500">{t('common.loading')}</p>}
        {!subs.isLoading && subRows.length === 0 && (
          <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
        )}
      </div>
    </div>
  )
}
