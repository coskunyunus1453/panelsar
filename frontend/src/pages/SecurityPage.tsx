import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import {
  AlertTriangle,
  Binary,
  CheckCircle2,
  Clock,
  Cpu,
  Flame,
  Globe,
  Loader2,
  Lock,
  RefreshCw,
  Shield,
  ShieldAlert,
  ShieldCheck,
  ShieldX,
  Terminal,
  Zap,
} from 'lucide-react'
import toast from 'react-hot-toast'
import { useAuthStore } from '../store/authStore'
import clsx from 'clsx'
import {
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
} from 'recharts'

type SecurityTabId = 'firewall' | 'ssh' | 'server' | 'website' | 'brute' | 'compiler' | 'attack'

function ProPill() {
  return (
    <span className="ml-2 inline-flex items-center rounded-md bg-violet-600/15 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-700 dark:text-violet-300">
      Pro
    </span>
  )
}

function PlaceholderCard({
  title,
  description,
  pro,
}: {
  title: string
  description: string
  pro?: boolean
}) {
  return (
    <div className="flex gap-3 rounded-xl border border-dashed border-gray-300 bg-gray-50/80 p-4 dark:border-gray-600 dark:bg-gray-800/30">
      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-200/80 dark:bg-gray-700">
        <Lock className="h-5 w-5 text-gray-500 dark:text-gray-400" />
      </div>
      <div>
        <p className="text-sm font-semibold text-gray-900 dark:text-white">
          {title}
          {pro ? <ProPill /> : null}
        </p>
        <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">{description}</p>
      </div>
    </div>
  )
}

export default function SecurityPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const isAdmin = useAuthStore((s) => s.user?.roles?.some((r) => r.name === 'admin'))
  const [tab, setTab] = useState<SecurityTabId>('firewall')
  const [scanTarget, setScanTarget] = useState('/var/www')
  const [scanOutput, setScanOutput] = useState('')
  const [mailConfirm, setMailConfirm] = useState('')
  const [mailReport, setMailReport] = useState<{
    dry_run?: boolean
    active_domains?: number
    scanned?: number
    orphans?: string[]
    removed?: string[]
  } | null>(null)
  const [jailBantime, setJailBantime] = useState(600)
  const [jailFindtime, setJailFindtime] = useState(600)
  const [jailMaxretry, setJailMaxretry] = useState(5)
  const [installModal, setInstallModal] = useState<{
    open: boolean
    key: 'fail2ban' | 'modsecurity' | null
    status: 'idle' | 'running' | 'success' | 'error'
    logs: string[]
    startedAt: number | null
    finishedAt: number | null
  }>({
    open: false,
    key: null,
    status: 'idle',
    logs: [],
    startedAt: null,
    finishedAt: null,
  })

  const tabs = useMemo(
    () =>
      [
        { id: 'firewall' as const, icon: Flame, label: t('security.tabs.firewall') },
        { id: 'ssh' as const, icon: Terminal, label: t('security.tabs.ssh') },
        { id: 'server' as const, icon: Cpu, label: t('security.tabs.server') },
        { id: 'website' as const, icon: Globe, label: t('security.tabs.website') },
        { id: 'brute' as const, icon: ShieldAlert, label: t('security.tabs.brute') },
        { id: 'compiler' as const, icon: Binary, label: t('security.tabs.compiler') },
        { id: 'attack' as const, icon: Zap, label: t('security.tabs.attack') },
      ] as const,
    [t],
  )

  const q = useQuery({
    queryKey: ['security-overview'],
    queryFn: async () => (await api.get('/security/overview')).data,
    refetchInterval: 45_000,
  })

  const fwM = useMutation({
    mutationFn: async (payload: {
      action: string
      protocol: string
      port?: string
      source?: string
    }) => api.post('/security/firewall', payload),
    onSuccess: () => {
      toast.success(t('security.toast.rule_sent'))
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string }; status?: number } }
      if (ax.response?.status === 403) toast.error(t('security.toast.admin_only'))
      else toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const toggleM = useMutation({
    mutationFn: async (payload: { key: 'fail2ban' | 'modsecurity' | 'clamav'; enabled: boolean }) => {
      if (payload.key === 'fail2ban') return api.post('/security/fail2ban/toggle', { enabled: payload.enabled })
      if (payload.key === 'modsecurity') return api.post('/security/modsecurity/toggle', { enabled: payload.enabled })
      return api.post('/security/clamav/toggle', { enabled: payload.enabled })
    },
    onSuccess: () => {
      toast.success(t('security.toast.setting_updated'))
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string }; status?: number } }
      if (ax.response?.status === 403) toast.error(t('security.toast.admin_only'))
      else toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const installM = useMutation({
    mutationFn: async (key: 'fail2ban' | 'modsecurity') => {
      if (key === 'fail2ban') return api.post('/security/fail2ban/install')
      return api.post('/security/modsecurity/install')
    },
    onSuccess: () => {
      setInstallModal((s) => ({
        ...s,
        status: 'success',
        finishedAt: Date.now(),
        logs: [...s.logs, t('security.install.done_log')],
      }))
      toast.success(t('security.toast.install_done'))
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string }; status?: number } }
      setInstallModal((s) => ({
        ...s,
        status: 'error',
        finishedAt: Date.now(),
        logs: [
          ...s.logs,
          [ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err),
        ],
      }))
      if (ax.response?.status === 403) toast.error(t('security.toast.admin_only'))
      else toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const runInstall = (key: 'fail2ban' | 'modsecurity') => {
    const title = key === 'fail2ban' ? 'Fail2ban' : 'ModSecurity'
    setInstallModal({
      open: true,
      key,
      status: 'running',
      startedAt: Date.now(),
      finishedAt: null,
      logs: [
        t('security.install.started', { name: title }),
        t('security.install.step_apt'),
        t('security.install.step_service'),
      ],
    })
    installM.mutate(key)
  }

  const clamavScanM = useMutation({
    mutationFn: async () => (await api.post('/security/clamav/scan', { target: scanTarget })).data,
    onSuccess: (res) => {
      setScanOutput(String(res?.result?.output ?? ''))
      toast.success(t('security.toast.scan_done'))
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string; result?: { output?: string } }; status?: number } }
      setScanOutput(String(ax.response?.data?.result?.output ?? ''))
      if (ax.response?.status === 403) toast.error(t('security.toast.admin_only'))
      else toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const jailM = useMutation({
    mutationFn: async () =>
      api.post('/security/fail2ban/jail', {
        bantime: jailBantime,
        findtime: jailFindtime,
        maxretry: jailMaxretry,
      }),
    onSuccess: () => {
      toast.success(t('security.toast.jail_updated'))
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string } } }
      toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const mailReconcileM = useMutation({
    mutationFn: async (payload: { dry_run: boolean; confirm?: string }) =>
      (await api.post('/security/mail/reconcile', payload)).data,
    onSuccess: (res) => {
      setMailReport(res?.result?.report ?? null)
      if (res?.result?.report?.dry_run) toast.success(t('security.toast.mail_dry_done'))
      else toast.success(t('security.toast.mail_clean_done'))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; result?: { error?: string } } } }
      toast.error(ax.response?.data?.result?.error ?? ax.response?.data?.message ?? String(err))
    },
  })

  type Overview = {
    fail2ban?: {
      enabled?: boolean
      installed?: boolean
      jails?: string[]
      error?: string
      settings?: { bantime?: number; findtime?: number; maxretry?: number; error?: string }
    }
    firewall?: {
      backend?: string
      default_policy?: string
      recent_rules?: Array<{
        action?: string
        protocol?: string
        port?: string
        source?: string
        applied_at?: unknown
      }>
    }
    modsecurity?: { enabled?: boolean; installed?: boolean; error?: string }
    clamav?: { enabled?: boolean; installed?: boolean; last_scan?: unknown; error?: string }
  }

  const overview = q.data?.overview as Overview | undefined
  const fail2banNeedsInstall =
    overview?.fail2ban?.installed === false ||
    (overview?.fail2ban?.installed !== true &&
      String(overview?.fail2ban?.error ?? '').toLowerCase().includes('not installed'))
  const modsecNeedsInstall =
    overview?.modsecurity?.installed === false ||
    (overview?.modsecurity?.installed !== true &&
      String(overview?.modsecurity?.error ?? '').toLowerCase().includes('missing modsecurity'))
  const fwRules = overview?.firewall?.recent_rules ?? []

  const coverage = useMemo(() => {
    const fail2banOk = !!overview?.fail2ban?.enabled
    const firewallOk =
      typeof overview?.firewall?.default_policy === 'string' && (overview?.firewall?.default_policy ?? '') !== ''
    const modsecOk = !!overview?.modsecurity?.enabled
    const clamavOk = !!overview?.clamav?.enabled
    const enabledCount = [fail2banOk, firewallOk, modsecOk, clamavOk].filter(Boolean).length
    const total = 4
    const pct = Math.round((enabledCount / total) * 100)
    return { enabledCount, total, pct, fail2banOk, firewallOk, modsecOk, clamavOk }
  }, [overview])

  const actionDist = useMemo(() => {
    const out: Record<string, number> = {}
    for (const r of fwRules) {
      const a = String(r.action ?? '').toLowerCase().trim()
      if (!a) continue
      out[a] = (out[a] ?? 0) + 1
    }
    const allow = out['allow'] ?? 0
    const deny = out['deny'] ?? 0
    const other = Object.entries(out).reduce((acc, [k, v]) => (k === 'allow' || k === 'deny' ? acc : acc + v), 0)
    return [
      { key: 'allow', label: 'allow', value: allow, color: '#22c55e' },
      { key: 'deny', label: 'deny', value: deny, color: '#ef4444' },
      ...(other > 0 ? [{ key: 'other', label: 'other', value: other, color: '#f59e0b' }] : []),
    ]
  }, [fwRules])

  const protocolDist = useMemo(() => {
    const out: Record<string, number> = {}
    for (const r of fwRules) {
      const p = String(r.protocol ?? '').toLowerCase().trim()
      if (!p) continue
      out[p] = (out[p] ?? 0) + 1
    }
    const items = Object.entries(out)
      .map(([key, value]) => ({ key, label: key, value }))
      .sort((a, b) => b.value - a.value)
      .slice(0, 6)
    return items.length ? items : [{ key: 'unknown', label: 'unknown', value: 0 }]
  }, [fwRules])

  const coveragePieData = useMemo(
    () => [
      { key: 'enabled', name: 'enabled', value: coverage.pct, color: '#22c55e' },
      { key: 'disabled', name: 'disabled', value: Math.max(0, 100 - coverage.pct), color: '#ef4444' },
    ],
    [coverage.pct],
  )

  useEffect(() => {
    const s = overview?.fail2ban?.settings
    if (!s) return
    if (typeof s.bantime === 'number') setJailBantime(s.bantime)
    if (typeof s.findtime === 'number') setJailFindtime(s.findtime)
    if (typeof s.maxretry === 'number') setJailMaxretry(s.maxretry)
  }, [overview?.fail2ban?.settings])

  const overviewBody =
    q.isLoading ? (
      <p className="text-gray-500">{t('common.loading')}</p>
    ) : q.isError ? (
      <p className="text-sm text-amber-600">{t('security.overview_error')}</p>
    ) : null

  const refreshBtn = (
    <button
      type="button"
      className="btn-secondary inline-flex items-center gap-2 text-sm"
      onClick={() => void q.refetch()}
      disabled={q.isLoading || q.isFetching}
      title={t('security.refresh')}
    >
      <RefreshCw className={q.isFetching ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
      {t('security.refresh')}
    </button>
  )

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-500/10">
            <Shield className="h-7 w-7 text-red-600 dark:text-red-400" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.security')}</h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">{t('security.subtitle')}</p>
          </div>
        </div>
        {refreshBtn}
      </div>

      <div className="rounded-2xl border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/60">
        <div className="-mx-1 flex gap-1 overflow-x-auto pb-1">
          {tabs.map(({ id, icon: Icon, label }) => (
            <button
              key={id}
              type="button"
              onClick={() => setTab(id)}
              className={clsx(
                'flex shrink-0 items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
                tab === id
                  ? 'bg-primary-600 text-white shadow-md shadow-primary-600/20'
                  : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
              )}
            >
              <Icon className="h-4 w-4 shrink-0 opacity-90" />
              <span className="whitespace-nowrap">{label}</span>
            </button>
          ))}
        </div>
      </div>

      {tab === 'firewall' && (
        <div className="space-y-6">
          {overviewBody}
          {!q.isLoading && !q.isError && (
            <>
              <div className="grid gap-4 lg:grid-cols-2">
                <div className="rounded-xl border border-gray-200 bg-gradient-to-br from-slate-50 to-white p-5 dark:border-gray-700 dark:from-gray-900/80 dark:to-gray-900">
                  <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {t('security.firewall.backend')}
                  </p>
                  <p className="mt-2 font-mono text-lg text-gray-900 dark:text-white">
                    {overview?.firewall?.backend ?? '—'}
                  </p>
                  <p className="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    {t('security.firewall.default_policy')}{' '}
                    <span className="font-mono font-semibold text-gray-900 dark:text-white">
                      {overview?.firewall?.default_policy ?? '—'}
                    </span>
                  </p>
                </div>
                <div className="rounded-xl border border-gray-200 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                  <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('security.firewall.quick_stats')}</p>
                  <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">{t('security.firewall.quick_stats_hint')}</p>
                  <div className="mt-4 grid grid-cols-2 gap-3 text-center">
                    <div className="rounded-lg bg-emerald-50 py-3 dark:bg-emerald-950/30">
                      <p className="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{fwRules.filter((r) => String(r.action).toLowerCase() === 'allow').length}</p>
                      <p className="text-xs text-emerald-800/80 dark:text-emerald-200/80">{t('security.firewall.allow_rules')}</p>
                    </div>
                    <div className="rounded-lg bg-red-50 py-3 dark:bg-red-950/30">
                      <p className="text-2xl font-bold text-red-700 dark:text-red-300">{fwRules.filter((r) => String(r.action).toLowerCase() === 'deny').length}</p>
                      <p className="text-xs text-red-800/80 dark:text-red-200/80">{t('security.firewall.deny_rules')}</p>
                    </div>
                  </div>
                </div>
              </div>

              <div className="grid gap-4 lg:grid-cols-3">
                <div className="rounded-xl border border-gray-100 p-4 dark:border-gray-800 dark:bg-gray-900/30">
                  <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('security.charts.coverage')}</p>
                  <div className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {coverage.enabledCount}/{coverage.total} ({coverage.pct}%)
                  </div>
                  <div className="mt-2" style={{ height: 180 }}>
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Tooltip />
                        <Legend />
                        <Pie data={coveragePieData} dataKey="value" nameKey="name" innerRadius={50} outerRadius={70} stroke="none" isAnimationActive>
                          {coveragePieData.map((p) => (
                            <Cell key={p.key} fill={p.color} />
                          ))}
                        </Pie>
                      </PieChart>
                    </ResponsiveContainer>
                  </div>
                </div>
                <div className="rounded-xl border border-gray-100 p-4 dark:border-gray-800 dark:bg-gray-900/30 lg:col-span-2">
                  <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('security.charts.actions_protocols')}</p>
                  <div className="mt-3 grid gap-4 sm:grid-cols-2">
                    <div style={{ height: 180 }}>
                      <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={actionDist}>
                          <CartesianGrid strokeDasharray="3 3" />
                          <XAxis dataKey="label" />
                          <Tooltip />
                          <Bar dataKey="value" radius={[8, 8, 0, 0]}>
                            {actionDist.map((a) => (
                              <Cell key={a.key} fill={a.color} />
                            ))}
                          </Bar>
                        </BarChart>
                      </ResponsiveContainer>
                    </div>
                    <div style={{ height: 180 }}>
                      <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                          <Tooltip />
                          <Pie data={protocolDist} dataKey="value" nameKey="label" innerRadius={40} outerRadius={65} isAnimationActive>
                            {protocolDist.map((p, i) => {
                              const palette = ['#3b82f6', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444', '#64748b']
                              return <Cell key={p.key} fill={palette[i % palette.length]} />
                            })}
                          </Pie>
                        </PieChart>
                      </ResponsiveContainer>
                    </div>
                  </div>
                </div>
              </div>

              {fwRules.length > 0 && (
                <div>
                  <p className="mb-2 text-sm font-semibold text-gray-900 dark:text-white">{t('security.firewall.recent_rules')}</p>
                  <div className="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                    <table className="w-full text-sm">
                      <thead className="bg-gray-50 dark:bg-gray-800/80">
                        <tr>
                          <th className="px-3 py-2 text-left">{t('security.table.action')}</th>
                          <th className="px-3 py-2 text-left">{t('security.table.protocol')}</th>
                          <th className="px-3 py-2 text-left">{t('security.table.port')}</th>
                          <th className="px-3 py-2 text-left">{t('security.table.source')}</th>
                          <th className="px-3 py-2 text-left">{t('security.table.time')}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {fwRules.map((r, i) => (
                          <tr key={i} className="border-t border-gray-100 dark:border-gray-800">
                            <td className="px-3 py-2 font-mono">
                              {(() => {
                                const a = String(r.action ?? '').toLowerCase()
                                const cls =
                                  a === 'allow'
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-200'
                                    : a === 'deny'
                                      ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-200'
                                      : 'bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200'
                                return (
                                  <span className={`inline-flex rounded-full border px-2 py-0.5 text-xs ${cls}`}>
                                    {String(r.action ?? '—')}
                                  </span>
                                )
                              })()}
                            </td>
                            <td className="px-3 py-2 font-mono">{String(r.protocol ?? '—')}</td>
                            <td className="px-3 py-2 font-mono">{String(r.port ?? '—')}</td>
                            <td className="px-3 py-2 font-mono text-xs">{String(r.source ?? '—')}</td>
                            <td className="px-3 py-2 text-xs text-gray-500">{String(r.applied_at ?? '—')}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {isAdmin && (
                <div className="rounded-xl border border-gray-200 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-white">{t('security.firewall.add_rule')}</h3>
                  <form
                    className="mt-4 grid max-w-xl gap-4 sm:grid-cols-2"
                    onSubmit={(ev) => {
                      ev.preventDefault()
                      const fd = new FormData(ev.currentTarget)
                      fwM.mutate({
                        action: String(fd.get('action')),
                        protocol: String(fd.get('protocol')),
                        port: String(fd.get('port') || '') || undefined,
                        source: String(fd.get('source') || '') || undefined,
                      })
                    }}
                  >
                    <div>
                      <label className="label">{t('security.form.action')}</label>
                      <select name="action" className="input w-full">
                        <option value="allow">allow</option>
                        <option value="deny">deny</option>
                      </select>
                    </div>
                    <div>
                      <label className="label">{t('security.form.protocol')}</label>
                      <select name="protocol" className="input w-full">
                        <option value="tcp">tcp</option>
                        <option value="udp">udp</option>
                        <option value="icmp">icmp</option>
                        <option value="any">any</option>
                      </select>
                    </div>
                    <div>
                      <label className="label">{t('security.form.port')}</label>
                      <input name="port" className="input w-full" placeholder="443" />
                    </div>
                    <div>
                      <label className="label">{t('security.form.source')}</label>
                      <input name="source" className="input w-full" placeholder="0.0.0.0/0" />
                    </div>
                    <div className="sm:col-span-2">
                      <button type="submit" className="btn-primary" disabled={fwM.isPending}>
                        {t('security.form.apply')}
                      </button>
                    </div>
                  </form>
                </div>
              )}
            </>
          )}
        </div>
      )}

      {tab === 'ssh' && (
        <div className="space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-400">{t('security.ssh.intro')}</p>
          <div className="grid gap-3 md:grid-cols-2">
            <PlaceholderCard
              pro
              title={t('security.ssh.port_title')}
              description={t('security.ssh.port_desc')}
            />
            <PlaceholderCard
              pro
              title={t('security.ssh.root_title')}
              description={t('security.ssh.root_desc')}
            />
            <PlaceholderCard
              pro
              title={t('security.ssh.password_title')}
              description={t('security.ssh.password_desc')}
            />
            <PlaceholderCard
              pro
              title={t('security.ssh.keys_title')}
              description={t('security.ssh.keys_desc')}
            />
          </div>
        </div>
      )}

      {tab === 'server' && (
        <div className="space-y-6">
          {overviewBody}
          {!q.isLoading && !q.isError && (
            <>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                  className={clsx(
                    'rounded-xl border p-4',
                    coverage.clamavOk
                      ? 'border-emerald-200 bg-emerald-50/40 dark:border-emerald-900/40 dark:bg-emerald-950/20'
                      : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/15',
                  )}
                >
                  <div className="flex items-center justify-between">
                    <p className="text-xs text-gray-500 dark:text-gray-400">ClamAV</p>
                    <Clock className="h-4 w-4 text-amber-600" />
                  </div>
                  <p className="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                    {coverage.clamavOk ? t('security.status.on') : t('security.status.off')}
                  </p>
                  <p className="mt-1 font-mono text-xs text-gray-600 dark:text-gray-400">
                    {overview?.clamav?.last_scan != null ? String(overview.clamav.last_scan) : t('security.clamav.no_scan')}
                  </p>
                  {isAdmin && (
                    <button
                      type="button"
                      className="mt-3 rounded-lg border border-gray-300 px-2 py-1 text-xs dark:border-gray-600"
                      onClick={() => toggleM.mutate({ key: 'clamav', enabled: !coverage.clamavOk })}
                      disabled={toggleM.isPending}
                    >
                      {coverage.clamavOk ? t('security.action.disable') : t('security.action.enable')}
                    </button>
                  )}
                  {!!overview?.clamav?.error && (
                    <p className="mt-2 text-[11px] text-red-600">{String(overview.clamav.error)}</p>
                  )}
                </div>
                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                  <p className="text-xs text-gray-500">Fail2ban</p>
                  <p className="mt-2 text-sm font-semibold">{coverage.fail2banOk ? t('security.status.on') : t('security.status.off')}</p>
                  <p className="mt-1 text-xs text-gray-500">{t('security.server.brute_hint')}</p>
                </div>
                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                  <p className="text-xs text-gray-500">ModSecurity</p>
                  <p className="mt-2 text-sm font-semibold">{coverage.modsecOk ? t('security.status.on') : t('security.status.off')}</p>
                  <p className="mt-1 text-xs text-gray-500">{t('security.server.waf_hint')}</p>
                </div>
                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                  <p className="text-xs text-gray-500">{t('security.firewall.backend')}</p>
                  <p className="mt-2 font-mono text-sm font-semibold">{overview?.firewall?.backend ?? '—'}</p>
                </div>
              </div>

              {isAdmin && (
                <>
                  <div className="rounded-xl border border-red-200 bg-red-50/40 p-5 dark:border-red-900/40 dark:bg-red-950/20">
                    <h3 className="text-base font-semibold text-red-900 dark:text-red-200">{t('security.mail_cleanup.title')}</h3>
                    <p className="mt-1 text-xs text-red-800/90 dark:text-red-300/90">{t('security.mail_cleanup.warning')}</p>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                      <button
                        type="button"
                        className="btn-secondary"
                        onClick={() => mailReconcileM.mutate({ dry_run: true })}
                        disabled={mailReconcileM.isPending}
                      >
                        {mailReconcileM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('security.mail_cleanup.dry')}
                      </button>
                      <input
                        className="input w-64 font-mono text-sm"
                        value={mailConfirm}
                        onChange={(e) => setMailConfirm(e.target.value)}
                        placeholder="DELETE_ORPHAN_MAIL_STATE"
                      />
                      <button
                        type="button"
                        className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        onClick={() => mailReconcileM.mutate({ dry_run: false, confirm: mailConfirm.trim() || undefined })}
                        disabled={mailReconcileM.isPending}
                      >
                        {t('security.mail_cleanup.confirm')}
                      </button>
                    </div>
                    {mailReport && (
                      <div className="mt-3 rounded-lg border border-gray-200 bg-white/70 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                        <p className="text-xs text-gray-700 dark:text-gray-300">
                          active_domains={mailReport.active_domains ?? 0}, scanned={mailReport.scanned ?? 0}, orphans=
                          {(mailReport.orphans ?? []).length}, removed={(mailReport.removed ?? []).length}
                        </p>
                        {(mailReport.orphans ?? []).length > 0 && (
                          <pre className="mt-2 max-h-40 overflow-auto rounded bg-black p-2 text-xs text-green-200">
                            {(mailReport.orphans ?? []).join('\n')}
                          </pre>
                        )}
                      </div>
                    )}
                  </div>

                  <div className="rounded-xl border border-gray-200 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">{t('security.clamav.scan_title')}</h3>
                    <div className="mt-4 flex flex-wrap items-end gap-2">
                      <div className="min-w-[260px] flex-1">
                        <label className="label">{t('security.clamav.target')}</label>
                        <input
                          className="input w-full font-mono"
                          value={scanTarget}
                          onChange={(e) => setScanTarget(e.target.value)}
                          placeholder="/var/www"
                        />
                      </div>
                      <button type="button" className="btn-primary" onClick={() => clamavScanM.mutate()} disabled={clamavScanM.isPending}>
                        {clamavScanM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('security.clamav.run')}
                      </button>
                    </div>
                    <pre className="mt-3 max-h-64 overflow-auto whitespace-pre-wrap rounded-lg bg-black p-3 text-xs text-green-200">
                      {scanOutput || t('security.clamav.no_output')}
                    </pre>
                  </div>
                </>
              )}

              <div className="grid gap-3 md:grid-cols-2">
                <PlaceholderCard
                  pro
                  title={t('security.server.kernel_title')}
                  description={t('security.server.kernel_desc')}
                />
                <PlaceholderCard
                  pro
                  title={t('security.server.audit_title')}
                  description={t('security.server.audit_desc')}
                />
              </div>
            </>
          )}
        </div>
      )}

      {tab === 'website' && (
        <div className="space-y-6">
          {overviewBody}
          {!q.isLoading && !q.isError && (
            <div
              className={clsx(
                'rounded-2xl border p-6',
                coverage.modsecOk
                  ? 'border-emerald-200 bg-emerald-50/30 dark:border-emerald-900/40 dark:bg-emerald-950/15'
                  : 'border-amber-200 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/15',
              )}
            >
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-white">ModSecurity (Apache WAF)</h3>
                  <p className="mt-1 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{t('security.website.modsec_intro')}</p>
                </div>
                {coverage.modsecOk ? (
                  <CheckCircle2 className="h-8 w-8 text-emerald-600" />
                ) : (
                  <AlertTriangle className="h-8 w-8 text-amber-600" />
                )}
              </div>
              <p className="mt-4 text-sm font-medium text-gray-900 dark:text-white">
                {t('security.status.label')}: {coverage.modsecOk ? t('security.status.on') : t('security.status.off')}
              </p>
              {isAdmin && (
                <div className="mt-4 flex flex-wrap gap-2">
                  <button
                    type="button"
                    className="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600"
                    onClick={() => toggleM.mutate({ key: 'modsecurity', enabled: !coverage.modsecOk })}
                    disabled={toggleM.isPending}
                  >
                    {coverage.modsecOk ? t('security.action.disable') : t('security.action.enable')}
                  </button>
                  {modsecNeedsInstall && (
                    <button
                      type="button"
                      className="rounded-lg bg-amber-600 px-3 py-2 text-sm text-white hover:bg-amber-700"
                      onClick={() => runInstall('modsecurity')}
                      disabled={installM.isPending}
                    >
                      {t('security.action.install_modsec')}
                    </button>
                  )}
                </div>
              )}
              {!!overview?.modsecurity?.error && (
                <p className="mt-3 text-sm text-red-600">{String(overview.modsecurity.error)}</p>
              )}
              <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">{t('security.website.toggle_hint')}</p>
            </div>
          )}
          <div className="grid gap-3 md:grid-cols-2">
            <PlaceholderCard pro title={t('security.website.per_site_title')} description={t('security.website.per_site_desc')} />
            <PlaceholderCard pro title={t('security.website.crs_title')} description={t('security.website.crs_desc')} />
          </div>
        </div>
      )}

      {tab === 'brute' && (
        <div className="space-y-6">
          {overviewBody}
          {!q.isLoading && !q.isError && (
            <>
              <div
                className={clsx(
                  'rounded-2xl border p-6',
                  coverage.fail2banOk
                    ? 'border-emerald-200 bg-emerald-50/30 dark:border-emerald-900/40 dark:bg-emerald-950/15'
                    : 'border-amber-200 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/15',
                )}
              >
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Fail2ban</h3>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{t('security.brute.intro')}</p>
                  </div>
                  {coverage.fail2banOk ? (
                    <ShieldCheck className="h-8 w-8 text-emerald-600" />
                  ) : (
                    <ShieldX className="h-8 w-8 text-amber-600" />
                  )}
                </div>
                {overview?.fail2ban?.jails && overview.fail2ban.jails.length > 0 && (
                  <p className="mt-3 font-mono text-xs text-gray-700 dark:text-gray-300">{overview.fail2ban.jails.join(', ')}</p>
                )}
                <div className="mt-4 flex flex-wrap gap-2">
                  {isAdmin && (
                    <>
                      <button
                        type="button"
                        className="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600"
                        onClick={() => toggleM.mutate({ key: 'fail2ban', enabled: !coverage.fail2banOk })}
                        disabled={toggleM.isPending}
                      >
                        {coverage.fail2banOk ? t('security.action.disable') : t('security.action.enable')}
                      </button>
                      {fail2banNeedsInstall && (
                        <button
                          type="button"
                          className="rounded-lg bg-amber-600 px-3 py-2 text-sm text-white hover:bg-amber-700"
                          onClick={() => runInstall('fail2ban')}
                          disabled={installM.isPending}
                        >
                          {t('security.action.install_fail2ban')}
                        </button>
                      )}
                    </>
                  )}
                </div>
                {!!overview?.fail2ban?.error && (
                  <p className="mt-3 text-sm text-amber-800 dark:text-amber-200">{String(overview.fail2ban.error)}</p>
                )}
                <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">{t('security.brute.toggle_hint')}</p>
              </div>

              {isAdmin && (
                <div className="rounded-xl border border-gray-200 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-white">{t('security.brute.jail_title')}</h3>
                  <div className="mt-4 grid gap-3 sm:grid-cols-3">
                    <div>
                      <label className="label">{t('security.brute.bantime')}</label>
                      <input
                        type="number"
                        min={60}
                        max={604800}
                        className="input w-full"
                        value={jailBantime}
                        onChange={(e) => setJailBantime(Number(e.target.value || 600))}
                      />
                    </div>
                    <div>
                      <label className="label">{t('security.brute.findtime')}</label>
                      <input
                        type="number"
                        min={60}
                        max={604800}
                        className="input w-full"
                        value={jailFindtime}
                        onChange={(e) => setJailFindtime(Number(e.target.value || 600))}
                      />
                    </div>
                    <div>
                      <label className="label">{t('security.brute.maxretry')}</label>
                      <input
                        type="number"
                        min={1}
                        max={20}
                        className="input w-full"
                        value={jailMaxretry}
                        onChange={(e) => setJailMaxretry(Number(e.target.value || 5))}
                      />
                    </div>
                  </div>
                  <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-xs text-gray-500">{t('security.brute.jail_hint')}</p>
                    <button type="button" className="btn-primary" onClick={() => jailM.mutate()} disabled={jailM.isPending}>
                      {jailM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('security.brute.apply')}
                    </button>
                  </div>
                  {!!overview?.fail2ban?.settings?.error && (
                    <p className="mt-2 text-xs text-red-600">{String(overview.fail2ban.settings.error)}</p>
                  )}
                </div>
              )}

              <PlaceholderCard
                pro
                title={t('security.brute.captcha_title')}
                description={t('security.brute.captcha_desc')}
              />
            </>
          )}
        </div>
      )}

      {tab === 'compiler' && (
        <div className="space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-400">{t('security.compiler.intro')}</p>
          <div className="grid gap-3 md:grid-cols-2">
            <PlaceholderCard pro title={t('security.compiler.gcc_title')} description={t('security.compiler.gcc_desc')} />
            <PlaceholderCard pro title={t('security.compiler.per_user_title')} description={t('security.compiler.per_user_desc')} />
          </div>
        </div>
      )}

      {tab === 'attack' && (
        <div className="space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-400">{t('security.attack.intro')}</p>
          <div className="rounded-xl border border-primary-200 bg-primary-50/40 p-5 dark:border-primary-900/40 dark:bg-primary-950/20">
            <p className="text-sm font-medium text-gray-900 dark:text-white">{t('security.attack.layers_title')}</p>
            <ul className="mt-3 list-inside list-disc space-y-1 text-sm text-gray-700 dark:text-gray-300">
              <li>{t('security.attack.layer_fw')}</li>
              <li>{t('security.attack.layer_f2b')}</li>
              <li>{t('security.attack.layer_modsec')}</li>
            </ul>
          </div>
          <div className="grid gap-3 md:grid-cols-2">
            <PlaceholderCard pro title={t('security.attack.rate_title')} description={t('security.attack.rate_desc')} />
            <PlaceholderCard pro title={t('security.attack.ddos_title')} description={t('security.attack.ddos_desc')} />
            <PlaceholderCard pro title={t('security.attack.bot_title')} description={t('security.attack.bot_desc')} />
            <PlaceholderCard pro title={t('security.attack.geo_title')} description={t('security.attack.geo_desc')} />
          </div>
        </div>
      )}

      {installModal.open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-2xl space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-900">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-semibold">
                {installModal.key === 'fail2ban' ? t('security.install.modal_fail2ban') : t('security.install.modal_modsec')}
              </h3>
              <button
                type="button"
                className="btn-secondary"
                onClick={() => setInstallModal((s) => ({ ...s, open: false }))}
                disabled={installModal.status === 'running'}
              >
                {t('common.close')}
              </button>
            </div>
            <div className="text-sm">
              {t('security.install.status')}:{' '}
              {installModal.status === 'running'
                ? t('security.install.running')
                : installModal.status === 'success'
                  ? t('security.install.success')
                  : installModal.status === 'error'
                    ? t('security.install.error')
                    : t('security.install.idle')}
            </div>
            <pre className="max-h-64 overflow-auto whitespace-pre-wrap rounded-lg bg-black p-3 text-xs text-green-200">
              {installModal.logs.join('\n')}
            </pre>
            {installModal.startedAt && (
              <p className="text-xs text-gray-500">
                {new Date(installModal.startedAt).toLocaleString()}{' '}
                {installModal.finishedAt ? `• ${new Date(installModal.finishedAt).toLocaleString()}` : ''}
              </p>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
