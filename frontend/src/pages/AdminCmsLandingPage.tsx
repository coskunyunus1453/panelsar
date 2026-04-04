import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import toast from 'react-hot-toast'
import CmsMarkdownEditor from '../components/cms/CmsMarkdownEditor'
import { CMS_LOCALES } from '../lib/cmsLocales'
import type { CmsPageDto } from '../types/cms'

const KIND = 'landing' as const

export default function AdminCmsLandingPage() {
  const { t, i18n } = useTranslation()
  const qc = useQueryClient()
  const isAdmin = useAuthStore((s) => s.user?.roles?.some((r) => r.name === 'admin'))
  const [locale, setLocale] = useState(() => (i18n.language || 'en').split('-')[0])
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')

  const q = useQuery({
    queryKey: ['admin-cms', KIND],
    queryFn: async () => (await api.get(`/admin/cms-items?kind=${KIND}`)).data as { pages: CmsPageDto[] },
    enabled: !!isAdmin,
  })

  const pageForLocale = q.data?.pages?.find((p) => p.locale === locale) ?? null

  useEffect(() => {
    if (pageForLocale) {
      setTitle(pageForLocale.title ?? '')
      setBody(pageForLocale.body_markdown ?? '')
    } else {
      setTitle('')
      setBody('')
    }
  }, [pageForLocale?.id, locale, pageForLocale])

  const saveM = useMutation({
    mutationFn: async ({ publish }: { publish: boolean }) => {
      const payload = {
        kind: KIND,
        locale,
        title,
        body_markdown: body,
        publish,
      }
      if (pageForLocale?.id) {
        return api.patch(`/admin/cms-items/${pageForLocale.id}`, payload)
      }
      return api.post('/admin/cms-items', payload)
    },
    onSuccess: () => {
      toast.success(t('cms_admin.saved'))
      qc.invalidateQueries({ queryKey: ['admin-cms', KIND] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const first = ax.response?.data?.errors
        ? Object.values(ax.response.data.errors)[0]?.[0]
        : undefined
      toast.error(first ?? ax.response?.data?.message ?? String(err))
    },
  })

  if (!isAdmin) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="card p-6">
      <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{t('cms_admin.landing_heading')}</h2>
      {q.isLoading ? (
        <p className="text-sm text-gray-500">{t('common.loading')}</p>
      ) : (
        <CmsMarkdownEditor
          locale={locale}
          localeOptions={CMS_LOCALES}
          onLocaleChange={setLocale}
          title={title}
          onTitleChange={setTitle}
          body={body}
          onBodyChange={setBody}
          status={pageForLocale?.status}
          disabled={saveM.isPending}
          onSaveDraft={() => saveM.mutate({ publish: false })}
          onPublish={() => saveM.mutate({ publish: true })}
        />
      )}
    </div>
  )
}
