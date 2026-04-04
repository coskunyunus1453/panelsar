import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import publicApi from '../services/publicApi'

type Pkg = {
  id: number
  name: string
  description: string | null
  price_monthly: string | number
  price_yearly: string | number
  currency: string
}

export default function PricingPage() {
  const { t } = useTranslation()
  const q = useQuery({
    queryKey: ['public-pricing'],
    queryFn: async () => (await publicApi.get<{ packages: Pkg[] }>('/public/pricing')).data,
  })

  const packages = q.data?.packages ?? []

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-3xl font-bold text-gray-900 dark:text-white">{t('marketing.pricing_title')}</h1>
        <p className="mt-2 text-gray-600 dark:text-gray-400">{t('marketing.pricing_subtitle')}</p>
      </div>

      {q.isLoading ? (
        <p className="text-gray-500">{t('common.loading')}</p>
      ) : (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {packages.map((p) => (
            <div key={p.id} className="card flex flex-col gap-4 p-6">
              <div>
                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{p.name}</h2>
                {p.description && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{p.description}</p>}
              </div>
              <div className="text-sm text-gray-600 dark:text-gray-300">
                <p>
                  {t('marketing.price_monthly')}: {p.price_monthly} {p.currency}
                </p>
                <p>
                  {t('marketing.price_yearly')}: {p.price_yearly} {p.currency}
                </p>
              </div>
              <div className="mt-auto flex flex-wrap gap-2">
                <Link
                  to={`/login?package_id=${p.id}&billing_cycle=monthly`}
                  className="btn-primary flex-1 text-center text-sm"
                >
                  {t('marketing.cta_monthly')}
                </Link>
                <Link
                  to={`/login?package_id=${p.id}&billing_cycle=yearly`}
                  className="btn-secondary flex-1 text-center text-sm"
                >
                  {t('marketing.cta_yearly')}
                </Link>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
