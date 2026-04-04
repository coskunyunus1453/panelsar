import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import publicApi from '../services/publicApi'

type Item = {
  id: number
  slug: string
  title: string | null
  excerpt: string | null
  published_at: string | null
}

export default function BlogPage() {
  const { t } = useTranslation()
  const q = useQuery({
    queryKey: ['public-blog'],
    queryFn: async () => (await publicApi.get<{ items: Item[] }>('/public/blog')).data,
  })

  const items = q.data?.items ?? []

  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-bold text-gray-900 dark:text-white">{t('marketing.blog_title')}</h1>
      {q.isLoading ? (
        <p className="text-gray-500">{t('common.loading')}</p>
      ) : items.length === 0 ? (
        <p className="text-gray-600 dark:text-gray-400">{t('common.no_data')}</p>
      ) : (
        <ul className="space-y-4">
          {items.map((it) => (
            <li key={it.id}>
              <Link
                to={`/blog/${encodeURIComponent(it.slug)}`}
                className="block rounded-lg border border-gray-200 bg-white p-4 transition hover:border-primary-300 dark:border-gray-800 dark:bg-gray-900"
              >
                <span className="text-lg font-semibold text-gray-900 dark:text-white">{it.title || it.slug}</span>
                {it.published_at && (
                  <span className="ml-2 text-xs text-gray-500">{new Date(it.published_at).toLocaleDateString()}</span>
                )}
                {it.excerpt && <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{it.excerpt}</p>}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
