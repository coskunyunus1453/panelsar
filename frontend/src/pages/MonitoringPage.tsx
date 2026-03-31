import { useCallback, useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import {
  Activity,
  AlertTriangle,
  CheckCircle2,
  Cpu,
  Database,
  Gauge,
  HardDrive,
  Layers,
  Lock,
  Mail,
  RefreshCw,
  Server,
  Waves,
} from 'lucide-react'
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import clsx from 'clsx'
import api from '../services/api'
import { useAuthStore } from '../store/authStore'
import { useThemeStore } from '../store/themeStore'
import { tokenHasAbility } from '../lib/abilities'
import { useDomainsList } from '../hooks/useDomains'

type HistoryPoint = { t: number; cpu: number; mem: number; disk: number }

type ServerStats = {
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
  cpu_model?: string
  cpu_cores_logical?: number
  memory_available?: number
  swap_total?: number
  swap_used?: number
  swap_percent?: number
  top_cpu_processes?: {
    pid?: number
    name?: string
    cpu_percent?: number
    rss_bytes?: number
  }[]
  top_memory_processes?: {
    pid?: number
    name?: string
    cpu_percent?: number
    rss_bytes?: number
  }[]
  top_disk_mounts?: {
    path?: string
    fstype?: string
    used_percent?: number
    used_bytes?: number
    total_bytes?: number
  }[]
}

type ServiceRow = {
  name?: string
  status?: string
  enabled?: boolean
}

const PIE_COLORS = ['#3b82f6', '#8b5cf6', '#f59e0b', '#10b981']
const CHART_CPU = '#3b82f6'
const CHART_MEM = '#8b5cf6'
const CHART_DISK = '#f59e0b'

function formatBytes(n?: number | null): string {
  if (n == null || !Number.isFinite(n) || n < 0) return '—'
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  if (n < 1024 * 1024 * 1024) return `${(n / (1024 * 1024)).toFixed(1)} MB`
  return `${(n / (1024 * 1024 * 1024)).toFixed(2)} GB`
}

function formatUptime(seconds?: number | null): string {
  if (seconds == null || !Number.isFinite(seconds)) return '—'
  const s = Math.floor(seconds)
  const d = Math.floor(s / 86400)
  const h = Math.floor((s % 86400) / 3600)
  const m = Math.floor((s % 3600) / 60)
  if (d > 0) return `${d}d ${h}h ${m}m`
  if (h > 0) return `${h}h ${m}m`
  return `${m}m`
}

function AnimatedBar({ value, colorClass }: { value: number; colorClass: string }) {
  const v = Math.min(100, Math.max(0, value))
  return (
    <div className="h-2.5 w-full overflow-hidden rounded-full bg-gray-200/80 dark:bg-gray-700/80">
      <div
        className={clsx(
          'h-full rounded-full origin-left transition-all duration-700 ease-out motion-safe:animate-[monitor-shimmer_2.2s_ease-in-out_infinite]',
          colorClass,
        )}
        style={{ width: `${v}%`, transform: 'translateZ(0)' }}
      />
    </div>
  )
}

export default function MonitoringPage() {
  const { t } = useTranslation()
  const { isDark } = useThemeStore()
  const abilities = useAuthStore((s) => s.user?.abilities)
  const canServer = tokenHasAbility(abilities, 'monitoring:server')
  const [history, setHistory] = useState<HistoryPoint[]>([])
  const [tick, setTick] = useState(() => Date.now())
  const [healthDomainId, setHealthDomainId] = useState<number | ''>('')
  const domainsQ = useDomainsList()

  const gridColor = isDark ? '#334155' : '#e2e8f0'
  const axisColor = isDark ? '#94a3b8' : '#64748b'
  const tooltipBg = isDark ? '#1e293b' : '#fff'
  const tooltipBorder = isDark ? '#475569' : '#e2e8f0'

  const summaryQ = useQuery({
    queryKey: ['monitoring-summary'],
    queryFn: async () => (await api.get('/monitoring/summary')).data,
    refetchInterval: 45_000,
  })
  const healthQ = useQuery({
    queryKey: ['monitoring-health', healthDomainId || 'global'],
    queryFn: async () =>
      (
        await api.get('/monitoring/health', {
          params: healthDomainId === '' ? undefined : { domain_id: healthDomainId },
        })
      ).data as {
        score: number
        grade: 'excellent' | 'good' | 'warning' | 'critical'
        response_ms: number
        site_response_ms?: number | null
        scope?: 'global' | 'domain'
        domain?: { id: number; name: string; status: string } | null
        snapshot: { cpu: number; ram: number; disk: number; error_rate: number }
        reasons: Array<{ key: string; ok: boolean; label: string; detail: string }>
      },
    refetchInterval: 20_000,
  })
  const healthSitesQ = useQuery({
    queryKey: ['monitoring-health-sites'],
    queryFn: async () =>
      (await api.get('/monitoring/health/sites', { params: { limit: 20 } })).data as {
        items: Array<{
          domain_id: number
          name: string
          score: number
          grade: 'excellent' | 'good' | 'warning' | 'critical'
          reasons: string[]
        }>
      },
    refetchInterval: 30_000,
  })

  const serverQ = useQuery({
    queryKey: ['monitoring-server'],
    enabled: canServer,
    queryFn: async () => (await api.get('/monitoring/server')).data,
    refetchInterval: canServer ? 10_000 : false,
  })

  const stats = serverQ.data?.stats as ServerStats | undefined
  const servicesRaw = serverQ.data?.services
  const serviceRows: ServiceRow[] = Array.isArray(servicesRaw) ? servicesRaw : []

  const pushHistory = useCallback((s: ServerStats) => {
    setHistory((prev) => {
      const next: HistoryPoint = {
        t: Date.now(),
        cpu: Math.min(100, Math.max(0, s.cpu_usage ?? 0)),
        mem: Math.min(100, Math.max(0, s.memory_percent ?? 0)),
        disk: Math.min(100, Math.max(0, s.disk_percent ?? 0)),
      }
      const merged = [...prev, next]
      return merged.slice(-40)
    })
    setTick(Date.now())
  }, [])

  useEffect(() => {
    if (!canServer) return
    const st = serverQ.data?.stats as ServerStats | undefined
    if (!st) return
    pushHistory(st)
  }, [canServer, serverQ.data, serverQ.dataUpdatedAt, pushHistory])

  const chartHistory = useMemo(
    () =>
      history.map((p, i) => ({
        i,
        time: new Date(p.t).toLocaleTimeString(undefined, {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
        }),
        cpu: Math.round(p.cpu * 10) / 10,
        mem: Math.round(p.mem * 10) / 10,
        disk: Math.round(p.disk * 10) / 10,
      })),
    [history],
  )

  const s = summaryQ.data as
    | {
        domains?: number
        databases?: number
        email_accounts?: number
        disk_estimate_mb?: number | null
      }
    | undefined

  const accountPie = useMemo(() => {
    const d = s?.domains ?? 0
    const db = s?.databases ?? 0
    const em = s?.email_accounts ?? 0
    const items = [
      { name: t('nav.domains'), value: Math.max(0, d), key: 'domains' },
      { name: t('nav.databases'), value: Math.max(0, db), key: 'databases' },
      { name: t('nav.email'), value: Math.max(0, em), key: 'email' },
    ].filter((x) => x.value > 0)
    if (items.length === 0) {
      return [{ name: t('monitoring.no_metrics_yet'), value: 1, key: 'empty' }]
    }
    return items
  }, [s?.domains, s?.databases, s?.email_accounts, t])

  const summaryCards = [
    {
      label: t('nav.domains'),
      value: s?.domains ?? '—',
      icon: Server,
      accent: 'from-blue-500/20 to-blue-600/5',
      iconBg: 'bg-blue-500/15 text-blue-600 dark:text-blue-400',
    },
    {
      label: t('nav.databases'),
      value: s?.databases ?? '—',
      icon: Database,
      accent: 'from-violet-500/20 to-violet-600/5',
      iconBg: 'bg-violet-500/15 text-violet-600 dark:text-violet-400',
    },
    {
      label: t('nav.email'),
      value: s?.email_accounts ?? '—',
      icon: Mail,
      accent: 'from-amber-500/20 to-amber-600/5',
      iconBg: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    },
    {
      label: t('monitoring.disk_usage_estimate'),
      value: s?.disk_estimate_mb != null ? `${s.disk_estimate_mb} MB` : '—',
      icon: HardDrive,
      accent: 'from-emerald-500/20 to-emerald-600/5',
      iconBg: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    },
  ]
  const health = healthQ.data
  const healthScore = Math.max(0, Math.min(100, health?.score ?? 0))
  const healthColor =
    healthScore >= 90
      ? 'from-emerald-500 to-emerald-400'
      : healthScore >= 75
        ? 'from-blue-500 to-sky-400'
        : healthScore >= 60
          ? 'from-amber-500 to-orange-400'
          : 'from-red-500 to-rose-400'

  const serviceBadge = (status?: string) => {
    const st = (status ?? '').toLowerCase()
    if (st === 'running') {
      return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-500/30'
    }
    if (st === 'stopped') {
      return 'bg-slate-500/15 text-slate-600 dark:text-slate-300 ring-slate-500/30'
    }
    if (st === 'error') {
      return 'bg-red-500/15 text-red-700 dark:text-red-300 ring-red-500/30'
    }
    return 'bg-amber-500/15 text-amber-800 dark:text-amber-200 ring-amber-500/30'
  }

  const serviceLabel = (status?: string) => {
    const st = (status ?? '').toLowerCase()
    if (st === 'running') return t('monitoring.service_running')
    if (st === 'stopped') return t('monitoring.service_stopped')
    if (st === 'error') return t('monitoring.service_error')
    return t('monitoring.service_unknown')
  }

  const cpuPct = Math.round(stats?.cpu_usage ?? 0)
  const memPct = Math.round(stats?.memory_percent ?? 0)
  const diskPct = Math.round(stats?.disk_percent ?? 0)

  const procCpuData =
    stats?.top_cpu_processes?.map((p) => ({
      name: (p.name ?? '?').slice(0, 24),
      cpu: Math.round((p.cpu_percent ?? 0) * 10) / 10,
    })) ?? []

  const procMemData =
    stats?.top_memory_processes?.map((p) => ({
      name: (p.name ?? '?').slice(0, 24),
      mb: Math.round(((p.rss_bytes ?? 0) / (1024 * 1024)) * 10) / 10,
    })) ?? []

  const mountsData =
    stats?.top_disk_mounts?.map((m) => ({
      path: (m.path ?? '/').slice(0, 28),
      pct: Math.round((m.used_percent ?? 0) * 10) / 10,
      used: formatBytes(m.used_bytes),
      total: formatBytes(m.total_bytes),
    })) ?? []

  return (
    <div className="space-y-8 pb-10">
      <style>{`
        @keyframes monitor-shimmer {
          0%, 100% { filter: brightness(1); }
          50% { filter: brightness(1.12); }
        }
      `}</style>

      <div className="relative overflow-hidden rounded-2xl border border-gray-200/80 bg-gradient-to-br from-primary-50/90 via-white to-violet-50/50 p-6 shadow-sm dark:border-panel-border dark:from-slate-900 dark:via-panel-card dark:to-slate-900/90">
        <div className="pointer-events-none absolute -right-20 -top-20 h-56 w-56 rounded-full bg-primary-400/10 blur-3xl dark:bg-primary-500/10" />
        <div className="pointer-events-none absolute -bottom-16 -left-10 h-48 w-48 rounded-full bg-violet-400/10 blur-3xl dark:bg-violet-500/10" />
        <div className="relative flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-center gap-4">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-white shadow-lg shadow-primary-600/25">
              <Activity className="h-8 w-8" strokeWidth={2.2} />
            </div>
            <div>
              <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                {t('nav.monitoring')}
              </h1>
              <p className="mt-0.5 max-w-xl text-sm text-gray-600 dark:text-gray-400">
                {t('monitoring.subtitle')}
              </p>
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <span className="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm ring-1 ring-gray-200/80 dark:bg-panel-card dark:text-gray-200 dark:ring-panel-border">
              <span className="relative flex h-2.5 w-2.5">
                <span
                  className={clsx(
                    'absolute inline-flex h-full w-full rounded-full opacity-75',
                    summaryQ.isFetching ? 'animate-ping bg-primary-400' : 'bg-emerald-400',
                  )}
                />
                <span
                  className={clsx(
                    'relative inline-flex h-2.5 w-2.5 rounded-full',
                    summaryQ.isFetching ? 'bg-primary-500' : 'bg-emerald-500',
                  )}
                />
              </span>
              {t('monitoring.live')}
              {summaryQ.isFetching && (
                <RefreshCw className="h-3.5 w-3.5 animate-spin text-primary-500" />
              )}
            </span>
            <span className="text-xs text-gray-500 dark:text-gray-500">
              {t('monitoring.last_update')}:{' '}
              {summaryQ.dataUpdatedAt ? new Date(summaryQ.dataUpdatedAt).toLocaleTimeString() : '—'}
            </span>
          </div>
        </div>
      </div>

      {/* Özet kartlar */}
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {summaryCards.map((c) => (
          <div
            key={c.label}
            className={clsx(
              'group card relative overflow-hidden p-5 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md',
            )}
          >
            <div
              className={clsx(
                'pointer-events-none absolute inset-0 bg-gradient-to-br opacity-60 transition-opacity group-hover:opacity-100',
                c.accent,
              )}
            />
            <div className="relative">
              <div
                className={clsx(
                  'mb-3 inline-flex rounded-xl p-2.5 transition-transform duration-300 group-hover:scale-105',
                  c.iconBg,
                )}
              >
                <c.icon className="h-6 w-6" />
              </div>
              <p className="text-3xl font-bold tabular-nums text-gray-900 dark:text-white">
                {summaryQ.isLoading ? '…' : c.value}
              </p>
              <p className="mt-1 text-sm font-medium text-gray-600 dark:text-gray-400">{c.label}</p>
            </div>
          </div>
        ))}
      </div>

      <div className="card p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Health Score</h2>
            <p className="text-sm text-gray-500 dark:text-gray-400">Sunucu genel sağlık puanı (0-100)</p>
          </div>
          <div className="flex items-center gap-2">
            <select
              className="input h-9 text-xs"
              value={healthDomainId}
              onChange={(e) => setHealthDomainId(e.target.value ? Number(e.target.value) : '')}
            >
              <option value="">Global</option>
              {(domainsQ.data ?? []).map((d) => (
                <option key={d.id} value={d.id}>
                  {d.name}
                </option>
              ))}
            </select>
            <div className="text-xs text-gray-500">
              {(health?.scope === 'domain' ? 'Site' : 'API')} {health?.scope === 'domain' ? (health?.site_response_ms ?? '—') : (health?.response_ms ?? '—')} ms
            </div>
          </div>
        </div>
        <div className="mt-4 grid gap-6 lg:grid-cols-3">
          <div className="flex items-center gap-5">
            <div
              className="relative h-28 w-28 rounded-full transition-all duration-700"
              style={{
                background: `conic-gradient(rgb(59 130 246) ${healthScore * 3.6}deg, rgb(229 231 235) 0deg)`,
              }}
            >
              <div className={`absolute inset-0 rounded-full bg-gradient-to-br ${healthColor} opacity-20`} />
              <div className="absolute inset-[8px] rounded-full bg-white dark:bg-gray-900 flex items-center justify-center shadow-inner">
                <span className="text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{healthScore}</span>
              </div>
            </div>
            <div className="space-y-1 text-sm">
              <div className="text-gray-900 dark:text-white font-semibold">
                {healthScore >= 90 ? 'Mükemmel' : healthScore >= 75 ? 'İyi' : healthScore >= 60 ? 'Dikkat' : 'Kritik'}
              </div>
              <div className="text-gray-500">CPU {health?.snapshot?.cpu ?? '—'}%</div>
              <div className="text-gray-500">RAM {health?.snapshot?.ram ?? '—'}%</div>
              <div className="text-gray-500">Disk {health?.snapshot?.disk ?? '—'}%</div>
            </div>
          </div>
          <div className="lg:col-span-2 space-y-2">
            {(health?.reasons ?? []).slice(0, 6).map((r) => (
              <div key={r.key} className="flex items-start gap-2 rounded-lg border border-gray-100 dark:border-gray-800 px-3 py-2">
                {r.ok ? (
                  <CheckCircle2 className="h-4 w-4 mt-0.5 text-emerald-500" />
                ) : (
                  <AlertTriangle className="h-4 w-4 mt-0.5 text-amber-500" />
                )}
                <div>
                  <div className="text-sm font-medium text-gray-900 dark:text-white">{r.label}</div>
                  <div className="text-xs text-gray-500">{r.detail}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="card p-6">
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Site Health Listesi</h3>
          <span className="text-xs text-gray-500">ilk 20 site</span>
        </div>
        <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          {(healthSitesQ.data?.items ?? []).map((it) => {
            const badge =
              it.score >= 90
                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                : it.score >= 75
                  ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
                  : it.score >= 60
                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                    : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
            return (
              <button
                key={it.domain_id}
                type="button"
                className="text-left rounded-xl border border-gray-100 dark:border-gray-800 p-3 hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors"
                onClick={() => setHealthDomainId(it.domain_id)}
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium text-sm text-gray-900 dark:text-white truncate">{it.name}</span>
                  <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${badge}`}>{it.score}</span>
                </div>
                {it.reasons?.length > 0 ? (
                  <p className="mt-1 text-xs text-gray-500 truncate">{it.reasons.join(' • ')}</p>
                ) : (
                  <p className="mt-1 text-xs text-gray-500">Durum iyi</p>
                )}
              </button>
            )
          })}
          {healthSitesQ.isLoading && <p className="text-sm text-gray-500">{t('common.loading')}</p>}
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-5">
        <div className="card p-6 lg:col-span-2">
          <div className="mb-4 flex items-center gap-2">
            <Layers className="h-5 w-5 text-primary-500" />
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
              {t('monitoring.account_overview')}
            </h2>
          </div>
          <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">{t('monitoring.account_chart_hint')}</p>
          <div className="h-[260px]">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={accountPie}
                  dataKey="value"
                  nameKey="name"
                  cx="50%"
                  cy="50%"
                  innerRadius={58}
                  outerRadius={88}
                  paddingAngle={2}
                  strokeWidth={0}
                  animationBegin={0}
                  animationDuration={900}
                  isAnimationActive
                >
                  {accountPie.map((_, idx) => (
                    <Cell
                      key={`cell-${idx}`}
                      fill={PIE_COLORS[idx % PIE_COLORS.length]}
                      className="outline-none"
                    />
                  ))}
                </Pie>
                <Tooltip
                  contentStyle={{
                    background: tooltipBg,
                    border: `1px solid ${tooltipBorder}`,
                    borderRadius: 12,
                  }}
                />
                <Legend wrapperStyle={{ fontSize: 12 }} />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="card flex flex-col justify-center p-6 lg:col-span-3">
          <div className="flex items-center gap-2 text-gray-900 dark:text-white">
            <Gauge className="h-5 w-5 text-violet-500" />
            <h2 className="text-lg font-semibold">{t('monitoring.insights_title')}</h2>
          </div>
          <p className="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
            {t('monitoring.account_chart_hint')} {t('monitoring.history_hint')}
          </p>
          <div className="mt-6 grid gap-4 sm:grid-cols-3">
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-900/40">
              <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{t('nav.domains')}</p>
              <p className="mt-1 text-2xl font-bold text-primary-600 dark:text-primary-400">
                {summaryQ.isLoading ? '…' : (s?.domains ?? 0)}
              </p>
            </div>
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-900/40">
              <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{t('nav.databases')}</p>
              <p className="mt-1 text-2xl font-bold text-violet-600 dark:text-violet-400">
                {summaryQ.isLoading ? '…' : (s?.databases ?? 0)}
              </p>
            </div>
            <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-900/40">
              <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{t('nav.email')}</p>
              <p className="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">
                {summaryQ.isLoading ? '…' : (s?.email_accounts ?? 0)}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Admin sunucu */}
      {!canServer && (
        <div className="card flex items-start gap-4 border-dashed border-gray-300 p-6 dark:border-gray-600">
          <div className="rounded-xl bg-gray-100 p-3 dark:bg-gray-800">
            <Lock className="h-6 w-6 text-gray-500" />
          </div>
          <div>
            <h3 className="font-semibold text-gray-900 dark:text-white">{t('monitoring.server_section')}</h3>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{t('monitoring.admin_only')}</p>
          </div>
        </div>
      )}

      {canServer && (
        <div className="space-y-6">
          <div className="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 className="text-xl font-bold text-gray-900 dark:text-white">{t('monitoring.server_section')}</h2>
              <p className="text-sm text-gray-500 dark:text-gray-400">{t('monitoring.server_hint')}</p>
            </div>
            <span className="text-xs text-gray-500">
              engine ~{tick ? new Date(tick).toLocaleTimeString() : ''}
            </span>
          </div>

          {serverQ.isError && (
            <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200">
              {t('monitoring.engine_unavailable')}
            </div>
          )}

          {serverQ.isLoading && !stats && (
            <p className="text-gray-500">{t('common.loading')}</p>
          )}

          {stats && (
            <>
              <div className="grid gap-4 lg:grid-cols-3">
                <div className="card p-5">
                  <div className="mb-3 flex items-center justify-between">
                    <span className="text-sm font-medium text-gray-600 dark:text-gray-400">{t('monitoring.cpu')}</span>
                    <Cpu className="h-4 w-4 text-blue-500" />
                  </div>
                  <p className="text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{cpuPct}%</p>
                  <div className="mt-3">
                    <AnimatedBar value={cpuPct} colorClass="bg-gradient-to-r from-blue-600 to-blue-400" />
                  </div>
                </div>
                <div className="card p-5">
                  <div className="mb-3 flex items-center justify-between">
                    <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                      {t('monitoring.memory')}
                    </span>
                    <Waves className="h-4 w-4 text-violet-500" />
                  </div>
                  <p className="text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{memPct}%</p>
                  <p className="mt-1 text-xs text-gray-500">
                    {t('monitoring.used_of', {
                      used: formatBytes(stats.memory_used),
                      total: formatBytes(stats.memory_total),
                    })}
                  </p>
                  <div className="mt-3">
                    <AnimatedBar value={memPct} colorClass="bg-gradient-to-r from-violet-600 to-violet-400" />
                  </div>
                </div>
                <div className="card p-5">
                  <div className="mb-3 flex items-center justify-between">
                    <span className="text-sm font-medium text-gray-600 dark:text-gray-400">{t('monitoring.disk')}</span>
                    <HardDrive className="h-4 w-4 text-amber-500" />
                  </div>
                  <p className="text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{diskPct}%</p>
                  <p className="mt-1 text-xs text-gray-500">
                    {t('monitoring.used_of', {
                      used: formatBytes(stats.disk_used),
                      total: formatBytes(stats.disk_total),
                    })}
                  </p>
                  <div className="mt-3">
                    <AnimatedBar value={diskPct} colorClass="bg-gradient-to-r from-amber-600 to-amber-400" />
                  </div>
                </div>
              </div>

              <div className="card p-6">
                <h3 className="mb-1 text-lg font-semibold text-gray-900 dark:text-white">
                  {t('monitoring.system_identity')}
                </h3>
                <div className="mt-4 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-3">
                  <div className="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/60">
                    <span className="text-gray-500">{t('monitoring.hostname')}</span>
                    <p className="font-mono text-gray-900 dark:text-gray-100">{stats.hostname ?? '—'}</p>
                  </div>
                  <div className="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/60">
                    <span className="text-gray-500">{t('monitoring.os')}</span>
                    <p className="text-gray-900 dark:text-gray-100">{stats.os ?? '—'}</p>
                  </div>
                  <div className="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/60">
                    <span className="text-gray-500">{t('monitoring.uptime')}</span>
                    <p className="font-mono text-gray-900 dark:text-gray-100">{formatUptime(stats.uptime)}</p>
                  </div>
                  <div className="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/60">
                    <span className="text-gray-500">{t('monitoring.cpu_model')}</span>
                    <p className="line-clamp-2 text-gray-900 dark:text-gray-100">{stats.cpu_model ?? '—'}</p>
                  </div>
                  <div className="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/60">
                    <span className="text-gray-500">{t('monitoring.cpu_cores')}</span>
                    <p className="font-mono text-gray-900 dark:text-gray-100">{stats.cpu_cores_logical ?? '—'}</p>
                  </div>
                  <div className="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/60">
                    <span className="text-gray-500">{t('monitoring.available')}</span>
                    <p className="font-mono text-gray-900 dark:text-gray-100">
                      {formatBytes(stats.memory_available)}
                    </p>
                  </div>
                  <div className="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/60">
                    <span className="text-gray-500">{t('monitoring.swap')}</span>
                    <p className="font-mono text-gray-900 dark:text-gray-100">
                      {Math.round(stats.swap_percent ?? 0)}% · {t('monitoring.used_of', {
                        used: formatBytes(stats.swap_used),
                        total: formatBytes(stats.swap_total),
                      })}
                    </p>
                  </div>
                </div>
              </div>

              {chartHistory.length >= 2 && (
                <div className="card p-6">
                  <h3 className="mb-1 text-lg font-semibold text-gray-900 dark:text-white">
                    {t('monitoring.history_title')}
                  </h3>
                  <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">{t('monitoring.history_hint')}</p>
                  <div className="h-[280px]">
                    <ResponsiveContainer width="100%" height="100%">
                      <AreaChart data={chartHistory} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                        <defs>
                          <linearGradient id="fillCpu" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={CHART_CPU} stopOpacity={0.35} />
                            <stop offset="100%" stopColor={CHART_CPU} stopOpacity={0.02} />
                          </linearGradient>
                          <linearGradient id="fillMemMon" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={CHART_MEM} stopOpacity={0.35} />
                            <stop offset="100%" stopColor={CHART_MEM} stopOpacity={0.02} />
                          </linearGradient>
                          <linearGradient id="fillDiskMon" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={CHART_DISK} stopOpacity={0.35} />
                            <stop offset="100%" stopColor={CHART_DISK} stopOpacity={0.02} />
                          </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 3" stroke={gridColor} vertical={false} />
                        <XAxis dataKey="time" tick={{ fill: axisColor, fontSize: 10 }} tickLine={false} axisLine={false} />
                        <YAxis domain={[0, 100]} tick={{ fill: axisColor, fontSize: 11 }} width={32} />
                        <Tooltip
                          contentStyle={{
                            background: tooltipBg,
                            border: `1px solid ${tooltipBorder}`,
                            borderRadius: 12,
                          }}
                        />
                        <Legend wrapperStyle={{ fontSize: 12 }} />
                        <Area
                          type="monotone"
                          dataKey="cpu"
                          name={t('monitoring.cpu')}
                          stroke={CHART_CPU}
                          fill="url(#fillCpu)"
                          strokeWidth={2}
                          dot={false}
                          isAnimationActive
                          animationDuration={900}
                        />
                        <Area
                          type="monotone"
                          dataKey="mem"
                          name={t('monitoring.memory')}
                          stroke={CHART_MEM}
                          fill="url(#fillMemMon)"
                          strokeWidth={2}
                          dot={false}
                          isAnimationActive
                          animationDuration={950}
                        />
                        <Area
                          type="monotone"
                          dataKey="disk"
                          name={t('monitoring.disk')}
                          stroke={CHART_DISK}
                          fill="url(#fillDiskMon)"
                          strokeWidth={2}
                          dot={false}
                          isAnimationActive
                          animationDuration={1000}
                        />
                      </AreaChart>
                    </ResponsiveContainer>
                  </div>
                </div>
              )}

              <div className="grid gap-6 xl:grid-cols-2">
                {mountsData.length > 0 && (
                  <div className="card p-6">
                    <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                      {t('monitoring.mounts_title')}
                    </h3>
                    <div className="h-[220px]">
                      <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={mountsData} layout="vertical" margin={{ left: 12, right: 12 }}>
                          <CartesianGrid strokeDasharray="3 3" stroke={gridColor} horizontal />
                          <XAxis type="number" domain={[0, 100]} tick={{ fill: axisColor, fontSize: 11 }} />
                          <YAxis
                            type="category"
                            dataKey="path"
                            width={100}
                            tick={{ fill: axisColor, fontSize: 10 }}
                          />
                          <Tooltip
                            contentStyle={{
                              background: tooltipBg,
                              border: `1px solid ${tooltipBorder}`,
                              borderRadius: 12,
                            }}
                            formatter={(value: number, _n, item) => [`${value}%`, item?.payload?.path]}
                          />
                          <Bar
                            dataKey="pct"
                            name={t('monitoring.used_pct')}
                            radius={[0, 8, 8, 0]}
                            fill="#3b82f6"
                            isAnimationActive
                            animationDuration={900}
                          />
                        </BarChart>
                      </ResponsiveContainer>
                    </div>
                  </div>
                )}

                {(procCpuData.length > 0 || procMemData.length > 0) && (
                  <div className="card p-6">
                    <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                      {t('monitoring.top_cpu')} / {t('monitoring.top_mem')}
                    </h3>
                    <div className="grid gap-6 sm:grid-cols-2">
                      <div className="h-[200px]">
                        <ResponsiveContainer width="100%" height="100%">
                          <BarChart data={procCpuData} layout="vertical">
                            <CartesianGrid strokeDasharray="3 3" stroke={gridColor} />
                            <XAxis type="number" tick={{ fill: axisColor, fontSize: 10 }} />
                            <YAxis dataKey="name" type="category" width={88} tick={{ fill: axisColor, fontSize: 9 }} />
                            <Tooltip
                              contentStyle={{
                                background: tooltipBg,
                                border: `1px solid ${tooltipBorder}`,
                                borderRadius: 12,
                              }}
                            />
                            <Bar
                              dataKey="cpu"
                              fill="#8b5cf6"
                              radius={[0, 6, 6, 0]}
                              isAnimationActive
                              animationDuration={800}
                            />
                          </BarChart>
                        </ResponsiveContainer>
                      </div>
                      <div className="h-[200px]">
                        <ResponsiveContainer width="100%" height="100%">
                          <BarChart data={procMemData} layout="vertical">
                            <CartesianGrid strokeDasharray="3 3" stroke={gridColor} />
                            <XAxis type="number" tick={{ fill: axisColor, fontSize: 10 }} />
                            <YAxis dataKey="name" type="category" width={88} tick={{ fill: axisColor, fontSize: 9 }} />
                            <Tooltip
                              contentStyle={{
                                background: tooltipBg,
                                border: `1px solid ${tooltipBorder}`,
                                borderRadius: 12,
                              }}
                              formatter={(v: number) => [`${v} MB`, t('monitoring.rss')]}
                            />
                            <Bar
                              dataKey="mb"
                              fill="#10b981"
                              radius={[0, 6, 6, 0]}
                              isAnimationActive
                              animationDuration={850}
                            />
                          </BarChart>
                        </ResponsiveContainer>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {serviceRows.length > 0 && (
                <div className="card p-6">
                  <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                    {t('monitoring.services_title')}
                  </h3>
                  <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    {serviceRows.map((svc, i) => (
                      <div
                        key={svc.name ?? `svc-${i}`}
                        className="flex items-center justify-between gap-2 rounded-xl border border-gray-100 bg-gray-50/90 px-3 py-2.5 dark:border-gray-800 dark:bg-gray-900/40"
                      >
                        <span className="truncate font-mono text-sm text-gray-900 dark:text-gray-100">
                          {svc.name ?? '—'}
                        </span>
                        <span
                          className={clsx(
                            'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ring-1',
                            serviceBadge(svc.status),
                          )}
                        >
                          {serviceLabel(svc.status)}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Süreç tabloları (detay) */}
              {(stats.top_cpu_processes?.length ?? 0) > 0 && (
                <div className="grid gap-6 lg:grid-cols-2">
                  <div className="card overflow-hidden">
                    <div className="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                      <h3 className="font-semibold text-gray-900 dark:text-white">{t('monitoring.top_cpu')}</h3>
                    </div>
                    <div className="overflow-x-auto">
                      <table className="w-full text-sm">
                        <thead className="bg-gray-50 dark:bg-gray-900/80">
                          <tr>
                            <th className="px-4 py-2 text-left">{t('monitoring.pid')}</th>
                            <th className="px-4 py-2 text-left">{t('monitoring.process')}</th>
                            <th className="px-4 py-2 text-right">CPU %</th>
                          </tr>
                        </thead>
                        <tbody>
                          {stats.top_cpu_processes?.map((p, idx) => (
                            <tr key={`${p.pid}-${idx}`} className="border-t border-gray-100 dark:border-gray-800">
                              <td className="px-4 py-2 font-mono text-gray-600 dark:text-gray-400">{p.pid}</td>
                              <td className="max-w-[200px] truncate px-4 py-2 font-mono">{p.name}</td>
                              <td className="px-4 py-2 text-right tabular-nums">
                                {(p.cpu_percent ?? 0).toFixed(1)}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                  <div className="card overflow-hidden">
                    <div className="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                      <h3 className="font-semibold text-gray-900 dark:text-white">{t('monitoring.top_mem')}</h3>
                    </div>
                    <div className="overflow-x-auto">
                      <table className="w-full text-sm">
                        <thead className="bg-gray-50 dark:bg-gray-900/80">
                          <tr>
                            <th className="px-4 py-2 text-left">{t('monitoring.pid')}</th>
                            <th className="px-4 py-2 text-left">{t('monitoring.process')}</th>
                            <th className="px-4 py-2 text-right">{t('monitoring.rss')}</th>
                          </tr>
                        </thead>
                        <tbody>
                          {stats.top_memory_processes?.map((p, idx) => (
                            <tr key={`${p.pid}-m-${idx}`} className="border-t border-gray-100 dark:border-gray-800">
                              <td className="px-4 py-2 font-mono text-gray-600 dark:text-gray-400">{p.pid}</td>
                              <td className="max-w-[200px] truncate px-4 py-2 font-mono">{p.name}</td>
                              <td className="px-4 py-2 text-right tabular-nums">
                                {formatBytes(p.rss_bytes)}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      )}
    </div>
  )
}
