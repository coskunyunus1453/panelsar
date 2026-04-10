import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import api from '../services/api'
import toast from 'react-hot-toast'
import clsx from 'clsx'
import {
  BarChart2,
  ExternalLink,
  FileText,
  Globe,
  Loader2,
  Plus,
  Search,
  ServerCog,
  Settings,
  Shield,
  ShieldCheck,
  Trash2,
} from 'lucide-react'
import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import DomainDeleteConfirmModal from '../components/domains/DomainDeleteConfirmModal'
import DomainQuickSettingsModal from '../components/domains/DomainQuickSettingsModal'
import { safeDomainUrl } from '../lib/urlSafety'

const PHP_OPTIONS = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'] as const

type DomainRow = {
  id: number
  name: string
  php_version: string
  server_type: string
  status: string
  ssl_enabled?: boolean
}

type DomainLogEntry = {
  type: string
  path: string
  exists: boolean
  content: string
  error?: string
}

type DomainHealthRow = {
  domain_id: number
  name: string
  score: number
  grade: 'excellent' | 'good' | 'warning' | 'critical'
  reasons: string[]
}

type SiteTraffic = {
  source?: string
  log_path?: string
  lines_analyzed?: number
  requests_total?: number
  bytes_total?: number
  status_2xx?: number
  status_3xx?: number
  status_4xx?: number
  status_5xx?: number
  hourly_requests?: { hour: string; count: number }[]
  requests_per_minute?: number
  window_start?: string
  window_end?: string
}

function formatTrafficBytes(n?: number): string {
  if (n == null || !Number.isFinite(n) || n < 0) return '—'
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  if (n < 1024 * 1024 * 1024) return `${(n / (1024 * 1024)).toFixed(1)} MB`
  return `${(n / (1024 * 1024 * 1024)).toFixed(2)} GB`
}

type Busy = {
  php?: boolean
  server?: boolean
  ssl?: boolean
  status?: boolean
}

type SslProgress = {
  pct: number
}

export default function DomainsPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [showAdd, setShowAdd] = useState(false)
  const [issueLeOnCreate, setIssueLeOnCreate] = useState(true)
  const [deleteTarget, setDeleteTarget] = useState<DomainRow | null>(null)
  const [quickTarget, setQuickTarget] = useState<DomainRow | null>(null)
  const [busy, setBusy] = useState<Record<number, Busy>>({})
  const [sslProgress, setSslProgress] = useState<Record<number, SslProgress>>({})
  const [logTarget, setLogTarget] = useState<DomainRow | null>(null)
  const [trafficTarget, setTrafficTarget] = useState<DomainRow | null>(null)
  const [logLines, setLogLines] = useState(200)
  const sslTimers = useRef<Record<number, number>>({})

  const domainsQ = useQuery({
    queryKey: ['domains', 'paginated'],
    queryFn: async () => (await api.get('/domains')).data,
  })
  const healthSitesQ = useQuery({
    queryKey: ['monitoring-health-sites', 50],
    queryFn: async () =>
      (await api.get('/monitoring/health/sites', { params: { limit: 50 } })).data as {
        items: DomainHealthRow[]
      },
    staleTime: 20_000,
    refetchInterval: 30_000,
  })

  const setBusyFlag = (domainId: number, key: keyof Busy, value: boolean) => {
    setBusy((prev) => ({
      ...prev,
      [domainId]: { ...(prev[domainId] ?? {}), [key]: value },
    }))
  }

  const startSslProgress = (domainId: number) => {
    setSslProgress((prev) => ({ ...prev, [domainId]: { pct: 8 } }))
    if (sslTimers.current[domainId]) {
      window.clearInterval(sslTimers.current[domainId])
    }
    sslTimers.current[domainId] = window.setInterval(() => {
      setSslProgress((prev) => {
        const cur = prev[domainId]?.pct ?? 8
        const next = Math.min(92, cur + Math.max(1, Math.round((100 - cur) / 18)))
        return { ...prev, [domainId]: { pct: next } }
      })
    }, 700)
  }

  const finishSslProgress = (domainId: number, ok: boolean) => {
    if (sslTimers.current[domainId]) {
      window.clearInterval(sslTimers.current[domainId])
      delete sslTimers.current[domainId]
    }
    setSslProgress((prev) => ({ ...prev, [domainId]: { pct: ok ? 100 : 0 } }))
    window.setTimeout(() => {
      setSslProgress((prev) => {
        const next = { ...prev }
        delete next[domainId]
        return next
      })
    }, ok ? 1200 : 500)
  }

  useEffect(() => {
    return () => {
      Object.values(sslTimers.current).forEach((id) => window.clearInterval(id))
      sslTimers.current = {}
    }
  }, [])

  type CreateDomainResponse = {
    message?: string
    ssl?: {
      ok: boolean
      message?: string
      diagnostics?: { key: string; ok: boolean; message: string }[]
    } | null
  }

  const createM = useMutation({
    mutationFn: async (payload: {
      name: string
      php_version: string
      server_type: string
      issue_lets_encrypt: boolean
      lets_encrypt_email?: string
    }) => {
      const { data } = await api.post<CreateDomainResponse>('/domains', payload)
      return data
    },
    onSuccess: (data) => {
      toast.success(t('domains.created'))
      if (data?.ssl) {
        if (data.ssl.ok) {
          toast.success(data.ssl.message?.trim() || t('domains.ssl_create_success'))
        } else {
          const detail = data.ssl.message?.trim() || t('domains.ssl_create_unknown')
          toast.error(`${t('domains.ssl_create_failed_prefix')}: ${detail}`, { duration: 8000 })
        }
      }
      qc.invalidateQueries({ queryKey: ['domains'] })
      setShowAdd(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const phpM = useMutation({
    mutationFn: async (vars: { id: number; php_version: string }) =>
      api.post(`/domains/${vars.id}/php`, { php_version: vars.php_version }),
    onMutate: (vars) => setBusyFlag(vars.id, 'php', true),
    onSuccess: (_, vars) => {
      toast.success(t('domains.php_switched'))
      qc.invalidateQueries({ queryKey: ['domains'] })
      setBusyFlag(vars.id, 'php', false)
    },
    onError: (err: unknown, vars) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
      setBusyFlag(vars.id, 'php', false)
    },
  })

  const serverM = useMutation({
    mutationFn: async (vars: { id: number; server_type: string }) =>
      api.post(`/domains/${vars.id}/server`, { server_type: vars.server_type }),
    onMutate: (vars) => setBusyFlag(vars.id, 'server', true),
    onSuccess: (_, vars) => {
      toast.success(t('domains.server_switched'))
      qc.invalidateQueries({ queryKey: ['domains'] })
      setBusyFlag(vars.id, 'server', false)
    },
    onError: (err: unknown, vars) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
      setBusyFlag(vars.id, 'server', false)
    },
  })

  const statusM = useMutation({
    mutationFn: async (vars: { id: number; status: 'active' | 'suspended' }) =>
      api.post(`/domains/${vars.id}/status`, { status: vars.status }),
    onMutate: (vars) => setBusyFlag(vars.id, 'status', true),
    onSuccess: (_, vars) => {
      toast.success(t('domains.status_updated'))
      qc.invalidateQueries({ queryKey: ['domains'] })
      setBusyFlag(vars.id, 'status', false)
    },
    onError: (err: unknown, vars) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
      setBusyFlag(vars.id, 'status', false)
    },
  })

  const sslIssueM = useMutation({
    mutationFn: async (vars: { id: number }) => api.post(`/domains/${vars.id}/ssl/issue`, {}),
    onMutate: (vars) => {
      setBusyFlag(vars.id, 'ssl', true)
      startSslProgress(vars.id)
    },
    onSuccess: (_, vars) => {
      toast.success(t('ssl.issued'))
      qc.invalidateQueries({ queryKey: ['domains'] })
      setBusyFlag(vars.id, 'ssl', false)
      finishSslProgress(vars.id, true)
    },
    onError: (err: unknown, vars) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
      setBusyFlag(vars.id, 'ssl', false)
      finishSslProgress(vars.id, false)
    },
  })

  const list: DomainRow[] = domainsQ.data?.data ?? []
  const total = (domainsQ.data?.total as number | undefined) ?? list.length
  const filtered = list.filter((d) => d.name.toLowerCase().includes(search.toLowerCase()))
  const healthByDomain = new Map<number, DomainHealthRow>(
    (healthSitesQ.data?.items ?? []).map((it) => [it.domain_id, it]),
  )
  const logsQ = useQuery({
    queryKey: ['domain-logs', logTarget?.id ?? 0, logLines],
    enabled: !!logTarget?.id,
    queryFn: async () => {
      const { data } = await api.get<{ logs: DomainLogEntry[] }>(
        `/domains/${logTarget?.id}/logs?lines=${logLines}`,
      )
      return data
    },
  })

  const trafficQ = useQuery({
    queryKey: ['domain-traffic', trafficTarget?.id ?? 0],
    enabled: !!trafficTarget?.id,
    queryFn: async () => {
      const { data } = await api.get<{ domain: string; traffic: SiteTraffic }>(
        `/domains/${trafficTarget?.id}/traffic`,
      )
      return data
    },
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('domains.title')}</h1>
          <p className="mt-1 text-gray-500 dark:text-gray-400">
            {total} {t('nav.domains').toLowerCase()}
          </p>
        </div>
        <button
          type="button"
          className="btn-primary flex items-center gap-2"
          onClick={() => setShowAdd(true)}
        >
          <Plus className="h-4 w-4" />
          {t('domains.add')}
        </button>
      </div>

      <DomainDeleteConfirmModal
        open={!!deleteTarget}
        domain={deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onDeleted={() => setDeleteTarget(null)}
      />

      <DomainQuickSettingsModal
        open={!!quickTarget}
        domain={quickTarget}
        onClose={() => setQuickTarget(null)}
      />

      {trafficTarget && (
        <div className="fixed inset-0 z-[56] flex items-center justify-center bg-black/50 p-4">
          <div className="card max-h-[92vh] w-full max-w-4xl overflow-y-auto bg-white p-6 dark:bg-gray-900">
            <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('domains.traffic_title')}</h2>
                <p className="font-mono text-xs text-gray-500">{trafficTarget.name}</p>
                <p className="mt-1 max-w-xl text-xs text-gray-500 dark:text-gray-400">{t('domains.traffic_hint')}</p>
              </div>
              <div className="flex items-center gap-2">
                <button type="button" className="btn-secondary text-sm" onClick={() => void trafficQ.refetch()}>
                  {t('domains.logs_refresh')}
                </button>
                <button type="button" className="btn-secondary text-sm" onClick={() => setTrafficTarget(null)}>
                  {t('common.cancel')}
                </button>
              </div>
            </div>

            {trafficQ.isLoading && <p className="py-6 text-sm text-gray-500">{t('common.loading')}</p>}

            {!trafficQ.isLoading && trafficQ.data && (
              <>
                {(!trafficQ.data.traffic?.lines_analyzed || trafficQ.data.traffic.lines_analyzed === 0) && (
                  <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                    {t('domains.traffic_no_log')}
                  </p>
                )}

                {!!trafficQ.data.traffic?.lines_analyzed && trafficQ.data.traffic.lines_analyzed > 0 && (
                  <>
                    <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                      <div className="rounded-xl border border-gray-200 bg-gradient-to-br from-primary-50/80 to-white p-4 dark:border-gray-700 dark:from-primary-950/30 dark:to-gray-900">
                        <p className="text-[11px] font-medium uppercase tracking-wide text-gray-500">{t('domains.traffic_requests')}</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
                          {trafficQ.data.traffic.requests_total ?? 0}
                        </p>
                      </div>
                      <div className="rounded-xl border border-gray-200 bg-gradient-to-br from-violet-50/80 to-white p-4 dark:border-gray-700 dark:from-violet-950/25 dark:to-gray-900">
                        <p className="text-[11px] font-medium uppercase tracking-wide text-gray-500">{t('domains.traffic_bytes')}</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
                          {formatTrafficBytes(trafficQ.data.traffic.bytes_total)}
                        </p>
                      </div>
                      <div className="rounded-xl border border-gray-200 bg-gradient-to-br from-emerald-50/80 to-white p-4 dark:border-gray-700 dark:from-emerald-950/25 dark:to-gray-900">
                        <p className="text-[11px] font-medium uppercase tracking-wide text-gray-500">{t('domains.traffic_rpm')}</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
                          {(trafficQ.data.traffic.requests_per_minute ?? 0).toFixed(1)}
                        </p>
                      </div>
                      <div className="rounded-xl border border-gray-200 bg-gray-50/90 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                        <p className="text-[11px] font-medium uppercase tracking-wide text-gray-500">{t('domains.traffic_source')}</p>
                        <p className="mt-1 font-mono text-sm text-gray-800 dark:text-gray-200">
                          {trafficQ.data.traffic.source ?? '—'}
                        </p>
                        <p className="mt-1 truncate font-mono text-[10px] text-gray-500" title={trafficQ.data.traffic.log_path}>
                          {trafficQ.data.traffic.log_path ?? ''}
                        </p>
                      </div>
                    </div>

                    <div className="mb-6 flex flex-wrap gap-2">
                      {[
                        { k: '2xx', v: trafficQ.data.traffic.status_2xx ?? 0, c: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' },
                        { k: '3xx', v: trafficQ.data.traffic.status_3xx ?? 0, c: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200' },
                        { k: '4xx', v: trafficQ.data.traffic.status_4xx ?? 0, c: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200' },
                        { k: '5xx', v: trafficQ.data.traffic.status_5xx ?? 0, c: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200' },
                      ].map((x) => (
                        <span key={x.k} className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ${x.c}`}>
                          HTTP {x.k}: {x.v}
                        </span>
                      ))}
                    </div>

                    {(trafficQ.data.traffic.hourly_requests?.length ?? 0) > 0 && (
                      <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <h3 className="mb-3 text-sm font-semibold text-gray-900 dark:text-white">{t('domains.traffic_hourly')}</h3>
                        <div className="h-[240px]">
                          <ResponsiveContainer width="100%" height="100%">
                            <BarChart
                              data={(trafficQ.data.traffic.hourly_requests ?? []).map((h) => ({
                                label: h.hour.slice(5, 16),
                                count: h.count,
                              }))}
                              margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
                            >
                              <CartesianGrid strokeDasharray="3 3" className="stroke-gray-200 dark:stroke-gray-700" />
                              <XAxis dataKey="label" tick={{ fontSize: 10 }} />
                              <YAxis width={36} tick={{ fontSize: 10 }} />
                              <Tooltip
                                contentStyle={{ borderRadius: 12 }}
                                formatter={(v: number) => [v, t('domains.traffic_requests')]}
                              />
                              <Bar dataKey="count" fill="#6366f1" radius={[6, 6, 0, 0]} name={t('domains.traffic_requests')} />
                            </BarChart>
                          </ResponsiveContainer>
                        </div>
                      </div>
                    )}
                  </>
                )}
              </>
            )}
          </div>
        </div>
      )}

      {logTarget && (
        <div className="fixed inset-0 z-[55] flex items-center justify-center bg-black/50 p-4">
          <div className="card max-h-[90vh] w-full max-w-6xl overflow-y-auto bg-white p-5 dark:bg-gray-900">
            <div className="mb-4 flex items-center justify-between gap-2">
              <div>
                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('domains.logs_title')}</h2>
                <p className="font-mono text-xs text-gray-500">{logTarget.name}</p>
              </div>
              <div className="flex items-center gap-2">
                <select
                  className="input h-9 text-sm"
                  value={logLines}
                  onChange={(e) => setLogLines(Number(e.target.value))}
                >
                  <option value={100}>100</option>
                  <option value={200}>200</option>
                  <option value={500}>500</option>
                </select>
                <button type="button" className="btn-secondary" onClick={() => void logsQ.refetch()}>
                  {t('domains.logs_refresh')}
                </button>
                <button type="button" className="btn-secondary" onClick={() => setLogTarget(null)}>
                  {t('common.cancel')}
                </button>
              </div>
            </div>

            {logsQ.isLoading && <p className="py-4 text-sm text-gray-500">{t('common.loading')}</p>}
            {!logsQ.isLoading && (logsQ.data?.logs ?? []).length === 0 && (
              <p className="py-4 text-sm text-gray-500">{t('domains.logs_empty')}</p>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
              {(logsQ.data?.logs ?? []).map((entry) => (
                <div key={entry.type} className="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                  <div className="flex items-center justify-between bg-gray-50 px-3 py-2 dark:bg-gray-800/70">
                    <div>
                      <p className="text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-300">
                        {entry.type}
                      </p>
                      <p className="text-[11px] text-gray-500">{entry.path}</p>
                    </div>
                    {!entry.exists && <span className="text-xs text-amber-600">{t('domains.logs_not_found')}</span>}
                  </div>
                  <pre className="max-h-[360px] overflow-auto bg-black p-3 text-xs text-green-200 whitespace-pre-wrap">
                    {entry.error
                      ? `${t('domains.logs_read_error')}: ${entry.error}`
                      : entry.content?.trim() || t('domains.logs_no_content')}
                  </pre>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {showAdd && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-md w-full space-y-4 p-6 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('domains.new_title')}</h2>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                const emailRaw = String(fd.get('lets_encrypt_email') || '').trim()
                createM.mutate({
                  name: String(fd.get('name') || '').trim(),
                  php_version: String(fd.get('php_version') || '8.2'),
                  server_type: String(fd.get('server_type') || 'nginx'),
                  issue_lets_encrypt: issueLeOnCreate,
                  lets_encrypt_email: emailRaw !== '' ? emailRaw : undefined,
                })
              }}
            >
              <div>
                <label className="label">{t('domains.name')}</label>
                <input name="name" className="input w-full" required placeholder="ornek.local" />
              </div>
              <div>
                <label className="label">{t('domains.php_version')}</label>
                <select name="php_version" className="input w-full" defaultValue="8.2">
                  {PHP_OPTIONS.map((v) => (
                    <option key={v} value={v}>
                      PHP {v}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">{t('domains.server_type')}</label>
                <select name="server_type" className="input w-full" defaultValue="nginx">
                  <option value="nginx">nginx</option>
                  <option value="apache">Apache</option>
                  <option value="openlitespeed">{t('domains.server_openlitespeed')}</option>
                </select>
              </div>
              <label className="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                <input
                  type="checkbox"
                  className="mt-0.5"
                  checked={issueLeOnCreate}
                  onChange={(e) => setIssueLeOnCreate(e.target.checked)}
                />
                <span>
                  <span className="block text-sm font-medium text-gray-900 dark:text-white">
                    {t('domains.issue_lets_encrypt')}
                  </span>
                  <span className="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                    {t('domains.issue_lets_encrypt_hint')}
                  </span>
                </span>
              </label>
              {issueLeOnCreate && (
                <div>
                  <label className="label">{t('domains.lets_encrypt_email_optional')}</label>
                  <input
                    name="lets_encrypt_email"
                    type="email"
                    className="input w-full"
                    placeholder={t('domains.lets_encrypt_email_placeholder')}
                    autoComplete="email"
                  />
                </div>
              )}
              <div className="flex justify-end gap-2 pt-2">
                <button type="button" className="btn-secondary" onClick={() => setShowAdd(false)}>
                  {t('common.cancel')}
                </button>
                <button type="submit" className="btn-primary" disabled={createM.isPending}>
                  {createM.isPending
                    ? issueLeOnCreate
                      ? t('domains.creating_with_ssl')
                      : t('domains.creating')
                    : t('common.create')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <div className="card">
        <div className="p-4 border-b border-gray-200 dark:border-panel-border">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={t('common.search')}
              className="input pl-10"
            />
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-200 dark:border-panel-border">
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  {t('domains.name')}
                </th>
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  {t('domains.php_version')}
                </th>
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  {t('domains.ssl_status')}
                </th>
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  {t('domains.server_type')}
                </th>
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  {t('common.status')}
                </th>
                <th className="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  {t('common.actions')}
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-panel-border">
              {domainsQ.isLoading && (
                <tr>
                  <td colSpan={6} className="px-6 py-8 text-center text-gray-500">
                    {t('common.loading')}
                  </td>
                </tr>
              )}

              {!domainsQ.isLoading &&
                filtered.map((domain) => {
                  const b = busy[domain.id] ?? {}
                  const sslEnabled = !!domain.ssl_enabled
                  const canToggle = domain.status === 'active' || domain.status === 'suspended'
                  const health = healthByDomain.get(domain.id)
                  const score = Math.max(0, Math.min(100, health?.score ?? 0))
                  const grade = health?.grade ?? 'critical'
                  const ringClass =
                    grade === 'excellent'
                      ? 'text-emerald-500'
                      : grade === 'good'
                        ? 'text-sky-500'
                        : grade === 'warning'
                          ? 'text-amber-500'
                          : 'text-rose-500'
                  const healthHint =
                    health && health.reasons.length > 0
                      ? `Health ${score}/100 - ${health.reasons.join(' | ')}`
                      : `Health ${score}/100`

                  const statusBadge = clsx(
                    'px-2.5 py-1 text-xs font-medium rounded-full',
                    domain.status === 'active' &&
                      'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400',
                    domain.status === 'suspended' &&
                      'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
                    domain.status === 'pending' &&
                      'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400',
                  )

                  const nextStatus = domain.status === 'active' ? 'suspended' : 'active'
                  const nextStatusLabel =
                    nextStatus === 'suspended' ? t('domains.suspended') : t('common.active')

                  return (
                    <tr
                      key={domain.id}
                      className="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                    >
                      <td className="px-6 py-4">
                        <Link
                          to={`/files?domain=${domain.id}`}
                          className="flex items-center gap-3"
                          title={healthHint}
                        >
                          <Globe className="h-5 w-5 text-primary-500" />
                          <div className="flex min-w-0 items-center gap-2">
                            <span className="truncate font-medium text-gray-900 dark:text-white">
                              {domain.name}
                            </span>
                            <span
                              className={clsx(
                                'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                                grade === 'excellent' &&
                                  'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-700/40 dark:bg-emerald-900/20 dark:text-emerald-300',
                                grade === 'good' &&
                                  'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-700/40 dark:bg-sky-900/20 dark:text-sky-300',
                                grade === 'warning' &&
                                  'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-700/40 dark:bg-amber-900/20 dark:text-amber-300',
                                grade === 'critical' &&
                                  'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-700/40 dark:bg-rose-900/20 dark:text-rose-300',
                              )}
                              title={healthHint}
                            >
                              <span
                                className={clsx(
                                  'h-4 w-4 rounded-full',
                                  ringClass,
                                )}
                                style={{
                                  background: `conic-gradient(currentColor ${Math.round((score / 100) * 360)}deg, rgba(156, 163, 175, 0.25) 0deg)`,
                                }}
                                aria-hidden
                              />
                              {score}
                            </span>
                          </div>
                        </Link>
                      </td>

                      <td className="px-6 py-4">
                        <select
                          className="input w-[120px]"
                          value={domain.php_version}
                          disabled={!!b.php}
                          onChange={(e) => {
                            const next = e.target.value
                            if (next === domain.php_version) return
                            if (
                              !window.confirm(
                                t('domains.confirm_php_change', { php: next }),
                              )
                            ) {
                              return
                            }
                            phpM.mutate({ id: domain.id, php_version: next })
                          }}
                        >
                          {PHP_OPTIONS.map((v) => (
                            <option key={v} value={v}>
                              PHP {v}
                            </option>
                          ))}
                        </select>
                      </td>

                      <td className="px-6 py-4">
                        {sslEnabled ? (
                          <div className="flex items-center gap-1.5 text-green-600 dark:text-green-400">
                            <ShieldCheck className="h-4 w-4" />
                            <span className="text-sm">{t('domains.ssl_active')}</span>
                          </div>
                        ) : (
                          <div className="space-y-2">
                            <div className="flex items-center gap-2">
                              <div className="flex items-center gap-1.5 text-gray-400">
                              <Shield className="h-4 w-4" />
                              <span className="text-sm">{t('domains.ssl_none')}</span>
                              </div>
                              <button
                                type="button"
                                className="btn-secondary px-2.5 py-1.5 text-xs disabled:opacity-70"
                                disabled={!!b.ssl}
                                onClick={() => {
                                  if (!window.confirm(t('domains.confirm_ssl_issue'))) {
                                    return
                                  }
                                  sslIssueM.mutate({ id: domain.id })
                                }}
                              >
                                {b.ssl ? <Loader2 className="h-4 w-4 animate-spin" /> : t('domains.ssl_add_letsencrypt')}
                              </button>
                            </div>
                            {b.ssl && (
                              <div className="w-52 max-w-full">
                                <div className="h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                  <div
                                    className="h-1.5 rounded-full bg-primary-500 transition-all duration-700"
                                    style={{ width: `${sslProgress[domain.id]?.pct ?? 8}%` }}
                                  />
                                </div>
                                <p className="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                  {t('domains.ssl_loading_hint')}
                                </p>
                              </div>
                            )}
                          </div>
                        )}
                      </td>

                      <td className="px-6 py-4">
                        <div className="flex items-center gap-1">
                          <select
                            className="input min-w-[130px] max-w-[180px] flex-1"
                            value={domain.server_type}
                            disabled={!!b.server}
                            onChange={(e) => {
                              const next = e.target.value
                              if (next === domain.server_type) return
                              const nextLabel =
                                next === 'apache'
                                  ? 'Apache'
                                  : next === 'openlitespeed'
                                    ? t('domains.server_openlitespeed')
                                    : 'Nginx'
                              if (
                                !window.confirm(
                                  t('domains.confirm_server_change', { server: nextLabel }),
                                )
                              ) {
                                return
                              }
                              serverM.mutate({ id: domain.id, server_type: next })
                            }}
                          >
                            <option value="nginx">nginx</option>
                            <option value="apache">Apache</option>
                            <option value="openlitespeed">{t('domains.server_openlitespeed')}</option>
                          </select>
                          <button
                            type="button"
                            title={t('domains.webserver_configure')}
                            className="shrink-0 rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                            onClick={() => setQuickTarget(domain)}
                          >
                            <ServerCog className="h-4 w-4" aria-hidden />
                            <span className="sr-only">{t('domains.webserver_configure')}</span>
                          </button>
                        </div>
                      </td>

                      <td className="px-6 py-4">
                        {canToggle ? (
                          <button
                            type="button"
                            className={statusBadge}
                            disabled={!!b.status}
                            onClick={() => {
                              if (
                                !window.confirm(
                                  t('domains.confirm_status_change', { status: nextStatusLabel }),
                                )
                              ) {
                                return
                              }
                              statusM.mutate({
                                id: domain.id,
                                status: nextStatus,
                              })
                            }}
                          >
                            {domain.status === 'active'
                              ? t('common.active')
                              : domain.status === 'suspended'
                                ? t('domains.suspended')
                                : domain.status}
                          </button>
                        ) : (
                          <span className={statusBadge}>
                            {domain.status === 'pending' ? t('common.pending') : domain.status}
                          </span>
                        )}
                      </td>

                      <td className="px-6 py-4 text-right">
                        <div className="inline-flex items-center justify-end gap-1">
                          <button
                            type="button"
                            title={t('domains.quick_settings')}
                            className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                            onClick={() => setQuickTarget(domain)}
                          >
                            <Settings className="h-4 w-4" />
                          </button>
                          <button
                            type="button"
                            title={t('domains.open_site')}
                            className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                            onClick={() => {
                              const url = safeDomainUrl(domain.name)
                              if (!url) {
                                toast.error('Geçersiz domain URL')
                                return
                              }
                              window.open(url, '_blank', 'noopener,noreferrer')
                            }}
                          >
                            <ExternalLink className="h-4 w-4" />
                          </button>
                          <button
                            type="button"
                            title={t('domains.delete_site')}
                            className="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/40 text-red-600 dark:text-red-400"
                            onClick={() => setDeleteTarget(domain)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                          <button
                            type="button"
                            title={t('domains.logs_button')}
                            className="p-1.5 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400"
                            onClick={() => setLogTarget(domain)}
                          >
                            <FileText className="h-4 w-4" />
                          </button>
                          <button
                            type="button"
                            title={t('domains.traffic_button')}
                            className="p-1.5 rounded-lg hover:bg-fuchsia-50 dark:hover:bg-fuchsia-950/40 text-fuchsia-600 dark:text-fuchsia-400"
                            onClick={() => setTrafficTarget(domain)}
                          >
                            <BarChart2 className="h-4 w-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
            </tbody>
          </table>
        </div>

        {!domainsQ.isLoading && filtered.length === 0 && (
          <div className="py-12 text-center text-gray-500 dark:text-gray-400">{t('common.no_data')}</div>
        )}
      </div>
    </div>
  )
}
