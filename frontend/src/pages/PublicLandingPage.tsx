import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import publicApi from '../services/publicApi'
import MarkdownView from '../components/MarkdownView'

type CmsPayload = { page: { title: string | null; body_markdown: string | null } | null }

export default function PublicLandingPage() {
  const { t } = useTranslation()
  const q = useQuery({
    queryKey: ['public-cms', 'landing'],
    queryFn: async () => (await publicApi.get<CmsPayload>('/public/cms/landing')).data,
  })

  if (q.isLoading) {
    return <p className="text-gray-500">{t('common.loading')}</p>
  }

  const body = q.data?.page?.body_markdown?.trim()
  const title = q.data?.page?.title?.trim()

  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-bold text-gray-900 dark:text-white">{title || t('marketing.landing_title')}</h1>
      {body ? (
        <MarkdownView markdown={body} />
      ) : (
        <p className="text-gray-600 dark:text-gray-300">{t('marketing.landing_placeholder')}</p>
      )}
    </div>
  )
}
