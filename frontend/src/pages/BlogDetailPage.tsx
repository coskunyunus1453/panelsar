import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import publicApi from '../services/publicApi'
import MarkdownView from '../components/MarkdownView'

type Page = {
  title: string | null
  body_markdown: string | null
  published_at: string | null
}

export default function BlogDetailPage() {
  const { slug } = useParams<{ slug: string }>()
  const { t } = useTranslation()
  const q = useQuery({
    queryKey: ['public-blog-post', slug],
    queryFn: async () =>
      (await publicApi.get<{ page: Page | null }>(`/public/blog/${encodeURIComponent(slug ?? '')}`)).data,
    enabled: !!slug,
  })

  if (!slug) {
    return <p className="text-red-600">{t('marketing.invalid_slug')}</p>
  }

  if (q.isLoading) {
    return <p className="text-gray-500">{t('common.loading')}</p>
  }

  if (q.isError || !q.data?.page) {
    return (
      <div className="space-y-4">
        <p className="text-gray-600 dark:text-gray-400">{t('marketing.not_found')}</p>
        <Link to="/blog" className="text-primary-600 hover:underline dark:text-primary-400">
          ← {t('marketing.back_blog')}
        </Link>
      </div>
    )
  }

  const page = q.data.page
  const body = page.body_markdown?.trim() ?? ''

  return (
    <div className="space-y-6">
      <Link to="/blog" className="text-sm text-primary-600 hover:underline dark:text-primary-400">
        ← {t('marketing.back_blog')}
      </Link>
      <h1 className="text-3xl font-bold text-gray-900 dark:text-white">{page.title || slug}</h1>
      {page.published_at && (
        <p className="text-sm text-gray-500">{new Date(page.published_at).toLocaleString()}</p>
      )}
      {body ? <MarkdownView markdown={body} /> : <p className="text-gray-600">{t('marketing.empty_body')}</p>}
    </div>
  )
}
