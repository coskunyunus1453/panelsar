import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import toast from 'react-hot-toast'
import { Plus, Pencil, Trash2 } from 'lucide-react'
import CmsMarkdownEditor from '../components/cms/CmsMarkdownEditor'
import { CMS_LOCALES } from '../lib/cmsLocales'
import type { CmsPageDto } from '../types/cms'

const KIND = 'blog' as const

export default function AdminCmsBlogPage() {
  const { t, i18n } = useTranslation()
  const qc = useQueryClient()
  const isAdmin = useAuthStore((s) => s.user?.roles?.some((r) => r.name === 'admin'))
  const [locale, setLocale] = useState(() => (i18n.language || 'en').split('-')[0])
  const [slug, setSlug] = useState('')
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [editing, setEditing] = useState<CmsPageDto | null>(null)
  const [creating, setCreating] = useState(false)

  const q = useQuery({
    queryKey: ['admin-cms', KIND],
    queryFn: async () => (await api.get(`/admin/cms-items?kind=${KIND}`)).data as { pages: CmsPageDto[] },
    enabled: !!isAdmin,
  })

  const pages = q.data?.pages ?? []

  useEffect(() => {
    if (creating) {
      setTitle('')
      setBody('')
      setSlug('')
      return
    }
    if (editing) {
      setLocale(editing.locale)
      setSlug(editing.slug)
      setTitle(editing.title ?? '')
      setBody(editing.body_markdown ?? '')
    }
  }, [creating, editing])

  const saveM = useMutation({
    mutationFn: async ({ publish }: { publish: boolean }) => {
      if (!slug.trim()) {
        throw new Error(t('cms_admin.slug_required'))
      }
      const payload = {
        kind: KIND,
        locale,
        slug: slug.trim().toLowerCase().replace(/\s+/g, '-'),
        title,
        body_markdown: body,
        publish,
      }
      if (editing?.id && !creating) {
        return api.patch(`/admin/cms-items/${editing.id}`, payload)
      }
      return api.post('/admin/cms-items', payload)
    },
    onSuccess: () => {
      toast.success(t('cms_admin.saved'))
      qc.invalidateQueries({ queryKey: ['admin-cms', KIND] })
      setCreating(false)
      setEditing(null)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const first = ax.response?.data?.errors
        ? Object.values(ax.response.data.errors)[0]?.[0]
        : undefined
      toast.error(first ?? (err instanceof Error ? err.message : ax.response?.data?.message) ?? String(err))
    },
  })

  const deleteM = useMutation({
    mutationFn: async (id: number) => api.delete(`/admin/cms-items/${id}`),
    onSuccess: () => {
      toast.success(t('cms_admin.deleted'))
      qc.invalidateQueries({ queryKey: ['admin-cms', KIND] })
      setEditing(null)
      setCreating(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  if (!isAdmin) {
    return <Navigate to="/dashboard" replace />
  }

  const startNew = () => {
    setCreating(true)
    setEditing(null)
  }

  const selectRow = (p: CmsPageDto) => {
    setCreating(false)
    setEditing(p)
  }

  return (
    <div className="space-y-6">
      <div className="card overflow-hidden p-0">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('cms_admin.blog_heading')}</h2>
          <button type="button" className="btn-primary inline-flex items-center gap-2" onClick={startNew}>
            <Plus className="h-4 w-4" />
            {t('cms_admin.new_post')}
          </button>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-gray-50 text-gray-600 dark:bg-gray-900/50 dark:text-gray-400">
              <tr>
                <th className="px-4 py-2 font-medium">{t('cms_admin.slug')}</th>
                <th className="px-4 py-2 font-medium">{t('cms_admin.locale')}</th>
                <th className="px-4 py-2 font-medium">{t('cms_admin.title')}</th>
                <th className="px-4 py-2 font-medium">{t('common.status')}</th>
                <th className="px-4 py-2 font-medium">{t('common.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {pages.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-gray-500">
                    {t('common.no_data')}
                  </td>
                </tr>
              )}
              {pages.map((p) => (
                <tr
                  key={p.id}
                  className={`border-t border-gray-100 dark:border-gray-800 ${
                    editing?.id === p.id ? 'bg-primary-50/50 dark:bg-primary-950/20' : ''
                  }`}
                >
                  <td className="px-4 py-2 font-mono text-xs">{p.slug}</td>
                  <td className="px-4 py-2">{p.locale}</td>
                  <td className="px-4 py-2">{p.title || '—'}</td>
                  <td className="px-4 py-2 text-xs">{p.status}</td>
                  <td className="px-4 py-2">
                    <div className="flex gap-2">
                      <button
                        type="button"
                        className="rounded p-1 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-950/40"
                        onClick={() => selectRow(p)}
                        title={t('common.edit')}
                      >
                        <Pencil className="h-4 w-4" />
                      </button>
                      <button
                        type="button"
                        className="rounded p-1 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                        onClick={() => {
                          if (window.confirm(t('common.confirm_delete'))) {
                            deleteM.mutate(p.id)
                          }
                        }}
                        title={t('common.delete')}
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {(creating || editing) && (
        <div className="card p-6">
          <h3 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">
            {creating ? t('cms_admin.new_blog') : t('cms_admin.edit_blog')}
          </h3>
          <CmsMarkdownEditor
            locale={locale}
            localeOptions={CMS_LOCALES}
            onLocaleChange={setLocale}
            title={title}
            onTitleChange={setTitle}
            body={body}
            onBodyChange={setBody}
            slug={slug}
            onSlugChange={setSlug}
            showSlug
            status={creating ? undefined : editing?.status}
            disabled={saveM.isPending}
            onSaveDraft={() => saveM.mutate({ publish: false })}
            onPublish={() => saveM.mutate({ publish: true })}
          />
        </div>
      )}
    </div>
  )
}
