import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import publicApi from '../services/publicApi'

type Item = {
  id: number
  slug: string
  locale: string
  title: string | null
  excerpt: string | null
  section: string | null
}

export default function DocsPage() {
  const { t } = useTranslation()
  const q = useQuery({
    queryKey: ['public-docs'],
    queryFn: async () => (await publicApi.get<{ items: Item[] }>('/public/docs')).data,
  })

  const items = q.data?.items ?? []

  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-bold text-gray-900 dark:text-white">{t('marketing.docs_title')}</h1>
      {q.isLoading ? (
        <p className="text-gray-500">{t('common.loading')}</p>
      ) : items.length === 0 ? (
        <p className="text-gray-600 dark:text-gray-400">{t('common.no_data')}</p>
      ) : (
        <ul className="space-y-3">
          {items.map((it) => (
            <li key={it.id}>
              <Link
                to={`/docs/${encodeURIComponent(it.slug)}`}
                className="block rounded-lg border border-gray-200 bg-white p-4 transition hover:border-primary-300 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-700"
              >
                <span className="font-medium text-gray-900 dark:text-white">{it.title || it.slug}</span>
                {it.section && (
                  <span className="ml-2 text-xs text-gray-500 dark:text-gray-500">· {it.section}</span>
                )}
                {it.excerpt && <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{it.excerpt}</p>}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
