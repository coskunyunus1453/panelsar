import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { Layers, Download, CheckCircle2, AlertCircle, Loader2 } from 'lucide-react'
import toast from 'react-hot-toast'
import clsx from 'clsx'
import { useState } from 'react'

type StackModule = {
  id: string
  category: string
  title: string
  description: string
  check_package: string
  installed: boolean
}
type StackRun = {
  id: number
  bundle_id: string
  status: 'queued' | 'running' | 'success' | 'failed'
  progress?: number
  cancel_requested?: boolean
  message?: string
  output?: string
}

function groupByCategory(mods: StackModule[]): Record<string, StackModule[]> {
  return mods.reduce<Record<string, StackModule[]>>((acc, m) => {
    const c = m.category || 'other'
    if (!acc[c]) acc[c] = []
    acc[c].push(m)
    return acc
  }, {})
}

export default function AdminStackPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')
  const [activeRunId, setActiveRunId] = useState<number | null>(null)

  const modulesQ = useQuery({
    queryKey: ['admin-stack-modules'],
    queryFn: async () => {
      const { data } = await api.get('/admin/stack/modules')
      return (data?.modules ?? []) as StackModule[]
    },
    enabled: !!isAdmin,
    refetchInterval: 60_000,
  })

  const installM = useMutation({
    mutationFn: async (bundleId: string) => {
      const { data } = await api.post('/admin/stack/install', { bundle_id: bundleId })
      return data as { message?: string; run_id?: number; background?: boolean }
    },
    onSuccess: (data) => {
      if (typeof data.run_id === 'number') setActiveRunId(data.run_id)
      toast.success(data.message ?? t('stack.install_ok'))
      qc.invalidateQueries({ queryKey: ['admin-stack-runs'] })
    },
    onError: (err: unknown) => {
      const ax = err as {
        response?: { data?: { message?: string; hint?: string; output?: string } }
      }
      const d = ax.response?.data
      const msg = d?.message ?? String(err)
      toast.error([msg, d?.hint, d?.output].filter(Boolean).join(' — '), { duration: 12_000 })
    },
  })
  const runsQ = useQuery({
    queryKey: ['admin-stack-runs'],
    queryFn: async () => (await api.get('/admin/stack/runs')).data as { runs: StackRun[] },
    enabled: !!isAdmin,
    refetchInterval: 3000,
  })
  const runDetailQ = useQuery({
    queryKey: ['admin-stack-run', activeRunId],
    queryFn: async () => (await api.get(`/admin/stack/runs/${activeRunId}`)).data as { run: StackRun },
    enabled: activeRunId !== null,
    refetchInterval: 3000,
  })
  const cancelRunM = useMutation({
    mutationFn: async (id: number) => (await api.post(`/admin/stack/runs/${id}/cancel`)).data as { message?: string },
    onSuccess: (d) => {
      toast.success(d?.message ?? 'İptal talebi gönderildi')
      qc.invalidateQueries({ queryKey: ['admin-stack-runs'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  if (!isAdmin) {
    return <Navigate to="/dashboard" replace />
  }

  const mods = modulesQ.data ?? []
  const activeRun = activeRunId !== null ? (runsQ.data?.runs ?? []).find((r) => r.id === activeRunId) : null
  const grouped = groupByCategory(mods)
  const catOrder = ['php', 'mail', 'other']
  const cats = catOrder.filter((c) => grouped[c]?.length)

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Layers className="h-8 w-8 text-primary-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('stack.title')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('stack.subtitle')}</p>
        </div>
      </div>

      <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
        {t('stack.warning')}
      </div>

      {modulesQ.isError && (
        <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
          <AlertCircle className="h-5 w-5 shrink-0" />
          {t('stack.load_error')}
        </div>
      )}

      {modulesQ.isSuccess && mods.length === 0 && (
        <div className="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300">
          <AlertCircle className="h-5 w-5 shrink-0" />
          {t('stack.empty_hint')}
        </div>
      )}

      {modulesQ.isLoading && (
        <p className="text-gray-500 dark:text-gray-400">{t('common.loading')}</p>
      )}

      {activeRun && (
        <div className="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm dark:border-indigo-900/40 dark:bg-indigo-950/20">
          <div className="flex items-center gap-2">
            <Loader2 className="h-4 w-4 animate-spin" />
            <span>{activeRun.status === 'running' || activeRun.status === 'queued' ? 'Paket kuruluyor, lütfen bekleyin...' : 'Kurulum bitti.'}</span>
            {(activeRun.status === 'queued' || activeRun.status === 'running') && (
              <button
                type="button"
                className="btn-secondary py-1 px-2 text-xs ml-auto"
                onClick={() => cancelRunM.mutate(activeRun.id)}
                disabled={cancelRunM.isPending}
              >
                İptal Et
              </button>
            )}
          </div>
          <div className="mt-2 h-2 w-full rounded bg-gray-200 dark:bg-gray-700 overflow-hidden">
            <div
              className="h-full bg-primary-600 transition-all"
              style={{ width: `${Math.max(0, Math.min(100, Number(activeRun.progress ?? 0)))}%` }}
            />
          </div>
          {activeRun.message && <p className="mt-1 text-xs">{activeRun.message}</p>}
        </div>
      )}

      {cats.map((cat) => (
        <section key={cat} className="space-y-3">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            {t(`stack.category_${cat}`)}
          </h2>
          <ul className="space-y-3">
            {(grouped[cat] ?? []).map((m) => (
              <li
                key={m.id}
                className="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium text-gray-900 dark:text-white">{m.title}</span>
                    {m.installed ? (
                      <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-200">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        {t('stack.installed')}
                      </span>
                    ) : (
                      <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                        {t('stack.not_installed')}
                      </span>
                    )}
                  </div>
                  <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{m.description}</p>
                  <p className="mt-1 font-mono text-xs text-gray-400 dark:text-gray-500">
                    {m.id}
                  </p>
                </div>
                <button
                  type="button"
                  disabled={m.installed || installM.isPending || !!activeRun}
                  onClick={() => installM.mutate(m.id)}
                  className={clsx(
                    'inline-flex shrink-0 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-colors',
                    m.installed || installM.isPending
                      ? 'cursor-not-allowed bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500'
                      : 'bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600',
                  )}
                >
                  {installM.isPending && !m.installed ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
                  {installM.isPending && !m.installed ? 'Kuruluyor...' : t('stack.install')}
                </button>
              </li>
            ))}
          </ul>
        </section>
      ))}

      <div className="card p-4">
        <h3 className="text-sm font-semibold text-gray-900 dark:text-white mb-3">Son paket kurulumları</h3>
        <div className="space-y-2">
          {(runsQ.data?.runs ?? []).map((r) => (
            <div key={r.id} className="rounded-md border border-gray-200 dark:border-gray-700 p-2 text-xs">
              <div className="flex items-center gap-2">
                <span className="font-mono">{r.bundle_id}</span>
                <span className="text-gray-500">#{r.id}</span>
                <span className="ml-auto">{r.status} ({Number(r.progress ?? 0)}%)</span>
                {(r.status === 'queued' || r.status === 'running') && (
                  <button
                    type="button"
                    className="btn-secondary py-1 px-2 text-[10px]"
                    onClick={() => cancelRunM.mutate(r.id)}
                    disabled={cancelRunM.isPending}
                  >
                    İptal
                  </button>
                )}
                <button type="button" className="btn-secondary py-1 px-2 text-[10px]" onClick={() => setActiveRunId(r.id)}>
                  Detay
                </button>
              </div>
              {r.message && <p className="mt-1 text-gray-500">{r.message}</p>}
            </div>
          ))}
        </div>
      </div>

      {activeRunId !== null && runDetailQ.data?.run && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-2xl rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                Paket kurulum detayı #{activeRunId}
              </h3>
              <button type="button" className="btn-secondary py-1 px-2 text-xs" onClick={() => setActiveRunId(null)}>
                Kapat
              </button>
            </div>
            <p className="text-xs text-gray-500 mb-2">{runDetailQ.data.run.message}</p>
            <pre className="max-h-[360px] overflow-auto rounded-md bg-gray-50 dark:bg-gray-800 p-3 text-[11px] whitespace-pre-wrap">
{runDetailQ.data.run.output || '-'}
            </pre>
          </div>
        </div>
      )}
    </div>
  )
}
