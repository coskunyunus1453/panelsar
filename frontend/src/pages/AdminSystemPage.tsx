import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import {
  Server,
  Cpu,
  MemoryStick,
  HardDrive,
  Clock,
  RefreshCw,
  Play,
  Square,
  RotateCw,
  Activity,
  AlertCircle,
} from 'lucide-react'
import toast from 'react-hot-toast'
import clsx from 'clsx'

type SystemStats = {
  cpu_usage?: number
  memory_total?: number
  memory_used?: number
  memory_percent?: number
  disk_total?: number
  disk_used?: number
  disk_percent?: number
  uptime?: number
  hostname?: string
  os?: string
}

type ServiceRow = {
  name: string
  status: string
  enabled?: boolean
  pid?: number
  memory?: number
  cpu?: number
}

function formatBytes(n?: number): string {
  if (n == null || Number.isNaN(n)) return '—'
  const u = ['B', 'KB', 'MB', 'GB', 'TB']
  let v = n
  let i = 0
  while (v >= 1024 && i < u.length - 1) {
    v /= 1024
    i++
  }
  return `${v < 10 && i > 0 ? v.toFixed(1) : Math.round(v)} ${u[i]}`
}

function formatUptime(sec?: number): string {
  if (sec == null) return '—'
  const d = Math.floor(sec / 86400)
  const h = Math.floor((sec % 86400) / 3600)
  const m = Math.floor((sec % 3600) / 60)
  if (d > 0) return `${d}g ${h}s`
  if (h > 0) return `${h}s ${m}d`
  return `${m} dk`
}

export default function AdminSystemPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')

  const statsQ = useQuery({
    queryKey: ['admin-system-stats'],
    queryFn: async () => {
      const { data } = await api.get('/system/stats')
      return (data?.stats ?? {}) as SystemStats
    },
    enabled: !!isAdmin,
    refetchInterval: 12_000,
  })

  const servicesQ = useQuery({
    queryKey: ['admin-system-services'],
    queryFn: async () => {
      const { data } = await api.get('/system/services')
      return (data?.services ?? []) as ServiceRow[]
    },
    enabled: !!isAdmin,
    refetchInterval: 15_000,
  })

  const serviceM = useMutation({
    mutationFn: async ({ name, action }: { name: string; action: string }) =>
      api.post(`/system/services/${encodeURIComponent(name)}`, { action }),
    onSuccess: (_, v) => {
      toast.success(t(`system.service_${v.action}`))
      qc.invalidateQueries({ queryKey: ['admin-system-services'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const nginxM = useMutation({
    mutationFn: async () => api.post('/system/nginx/reload'),
    onSuccess: () => {
      toast.success(t('system.nginx_reloaded'))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  if (!isAdmin) {
    return <Navigate to="/dashboard" replace />
  }

  const s = statsQ.data
  const statsError = statsQ.isError
  const servicesError = servicesQ.isError

  const statusClass = (status: string) =>
    clsx(
      'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium',
      status === 'running' && 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
      status === 'stopped' && 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
      status === 'error' && 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
      status === 'unknown' && 'bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-200',
    )

  return (
    <div className="space-y-8">
      <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div className="flex items-start gap-4">
          <div className="p-3 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 text-white shadow-lg shadow-primary-500/25">
            <Server className="h-8 w-8" />
          </div>
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
              {t('system.title')}
            </h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm mt-0.5">{t('system.subtitle')}</p>
            {s?.hostname && (
              <p className="text-xs font-mono text-primary-600 dark:text-primary-400 mt-2">
                {s.hostname}
                {s.os ? ` · ${s.os}` : ''}
              </p>
            )}
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            className="btn-secondary text-sm inline-flex items-center gap-2"
            onClick={() => {
              void statsQ.refetch()
              void servicesQ.refetch()
            }}
            disabled={statsQ.isFetching || servicesQ.isFetching}
          >
            <RefreshCw
              className={clsx('h-4 w-4', (statsQ.isFetching || servicesQ.isFetching) && 'animate-spin')}
            />
            {t('common.refresh')}
          </button>
          <button
            type="button"
            className="btn-primary text-sm inline-flex items-center gap-2"
            onClick={() => {
              if (window.confirm(t('system.nginx_confirm'))) nginxM.mutate()
            }}
            disabled={nginxM.isPending}
          >
            <Activity className="h-4 w-4" />
            {t('system.reload_nginx')}
          </button>
        </div>
      </div>

      {(statsError || servicesError) && (
        <div className="flex items-start gap-3 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-950/30 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
          <AlertCircle className="h-5 w-5 flex-shrink-0 mt-0.5" />
          <div>
            <p className="font-medium">{t('system.engine_unreachable')}</p>
            <p className="text-amber-800/80 dark:text-amber-300/80 mt-1">
              ENGINE_API_URL ve ENGINE_INTERNAL_KEY değerlerini kontrol edin; engine sürecinin çalıştığından emin olun.
            </p>
          </div>
        </div>
      )}

      <section>
        <h2 className="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">
          {t('system.resources')}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
          <div className="card p-5 border-t-4 border-t-blue-500">
            <div className="flex items-center justify-between mb-3">
              <span className="text-sm text-gray-500 dark:text-gray-400">{t('system.cpu')}</span>
              <Cpu className="h-5 w-5 text-blue-500" />
            </div>
            <p className="text-3xl font-bold tabular-nums text-gray-900 dark:text-white">
              {statsQ.isLoading ? '…' : `${Math.round(s?.cpu_usage ?? 0)}%`}
            </p>
            <div className="mt-3 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
              <div
                className="h-full rounded-full bg-blue-500 transition-all duration-500"
                style={{ width: `${Math.min(100, Math.round(s?.cpu_usage ?? 0))}%` }}
              />
            </div>
          </div>

          <div className="card p-5 border-t-4 border-t-violet-500">
            <div className="flex items-center justify-between mb-3">
              <span className="text-sm text-gray-500 dark:text-gray-400">{t('system.memory')}</span>
              <MemoryStick className="h-5 w-5 text-violet-500" />
            </div>
            <p className="text-3xl font-bold tabular-nums text-gray-900 dark:text-white">
              {statsQ.isLoading ? '…' : `${Math.round(s?.memory_percent ?? 0)}%`}
            </p>
            <p className="text-xs text-gray-500 mt-2 font-mono">
              {formatBytes(s?.memory_used)} / {formatBytes(s?.memory_total)}
            </p>
            <div className="mt-2 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
              <div
                className="h-full rounded-full bg-violet-500 transition-all duration-500"
                style={{ width: `${Math.min(100, Math.round(s?.memory_percent ?? 0))}%` }}
              />
            </div>
          </div>

          <div className="card p-5 border-t-4 border-t-orange-500">
            <div className="flex items-center justify-between mb-3">
              <span className="text-sm text-gray-500 dark:text-gray-400">{t('system.disk')}</span>
              <HardDrive className="h-5 w-5 text-orange-500" />
            </div>
            <p className="text-3xl font-bold tabular-nums text-gray-900 dark:text-white">
              {statsQ.isLoading ? '…' : `${Math.round(s?.disk_percent ?? 0)}%`}
            </p>
            <p className="text-xs text-gray-500 mt-2 font-mono">
              {formatBytes(s?.disk_used)} / {formatBytes(s?.disk_total)}
            </p>
            <div className="mt-2 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
              <div
                className="h-full rounded-full bg-orange-500 transition-all duration-500"
                style={{ width: `${Math.min(100, Math.round(s?.disk_percent ?? 0))}%` }}
              />
            </div>
          </div>

          <div className="card p-5 border-t-4 border-t-teal-500">
            <div className="flex items-center justify-between mb-3">
              <span className="text-sm text-gray-500 dark:text-gray-400">{t('system.uptime')}</span>
              <Clock className="h-5 w-5 text-teal-500" />
            </div>
            <p className="text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
              {statsQ.isLoading ? '…' : formatUptime(s?.uptime != null ? Number(s.uptime) : undefined)}
            </p>
            <p className="text-xs text-gray-500 mt-3">{t('system.uptime_hint')}</p>
          </div>
        </div>
      </section>

      <section>
        <h2 className="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">
          {t('system.daemons')}
        </h2>
        <div className="card overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-200 dark:border-panel-border bg-gray-50/80 dark:bg-gray-800/50">
                  <th className="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-300">
                    {t('system.service_name')}
                  </th>
                  <th className="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-300">
                    {t('common.status')}
                  </th>
                  <th className="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-300 hidden md:table-cell">
                    PID
                  </th>
                  <th className="text-right py-3 px-4 font-semibold text-gray-600 dark:text-gray-300">
                    {t('common.actions')}
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {servicesQ.isLoading && (
                  <tr>
                    <td colSpan={4} className="py-10 text-center text-gray-500">
                      {t('common.loading')}
                    </td>
                  </tr>
                )}
                {!servicesQ.isLoading &&
                  (servicesQ.data ?? []).map((svc) => (
                    <tr key={svc.name} className="hover:bg-gray-50/80 dark:hover:bg-gray-800/30">
                      <td className="py-3 px-4">
                        <span className="font-medium text-gray-900 dark:text-white">{svc.name}</span>
                        {svc.enabled === false && (
                          <span className="ml-2 text-xs text-gray-400">({t('system.disabled')})</span>
                        )}
                      </td>
                      <td className="py-3 px-4">
                        <span className={statusClass(svc.status)}>{svc.status}</span>
                      </td>
                      <td className="py-3 px-4 font-mono text-xs text-gray-500 hidden md:table-cell">
                        {svc.pid ?? '—'}
                      </td>
                      <td className="py-3 px-4 text-right">
                        <div className="inline-flex flex-wrap justify-end gap-1">
                          <button
                            type="button"
                            className="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-600 px-2 py-1 text-xs font-medium hover:bg-green-50 dark:hover:bg-green-900/20 text-green-700 dark:text-green-400 disabled:opacity-40"
                            disabled={serviceM.isPending}
                            onClick={() => serviceM.mutate({ name: svc.name, action: 'start' })}
                            title={t('system.action_start')}
                          >
                            <Play className="h-3 w-3" />
                            <span className="hidden sm:inline">{t('system.action_start')}</span>
                          </button>
                          <button
                            type="button"
                            className="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-600 px-2 py-1 text-xs font-medium hover:bg-red-50 dark:hover:bg-red-900/20 text-red-700 dark:text-red-400 disabled:opacity-40"
                            disabled={serviceM.isPending}
                            onClick={() => serviceM.mutate({ name: svc.name, action: 'stop' })}
                            title={t('system.action_stop')}
                          >
                            <Square className="h-3 w-3" />
                            <span className="hidden sm:inline">{t('system.action_stop')}</span>
                          </button>
                          <button
                            type="button"
                            className="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-600 px-2 py-1 text-xs font-medium hover:bg-blue-50 dark:hover:bg-blue-900/20 text-blue-700 dark:text-blue-400 disabled:opacity-40"
                            disabled={serviceM.isPending}
                            onClick={() => serviceM.mutate({ name: svc.name, action: 'restart' })}
                            title={t('system.action_restart')}
                          >
                            <RotateCw className="h-3 w-3" />
                            <span className="hidden sm:inline">{t('system.action_restart')}</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
              </tbody>
            </table>
          </div>
          {!servicesQ.isLoading && (servicesQ.data?.length ?? 0) === 0 && !servicesError && (
            <p className="py-10 text-center text-gray-500">{t('common.no_data')}</p>
          )}
        </div>
      </section>
    </div>
  )
}
