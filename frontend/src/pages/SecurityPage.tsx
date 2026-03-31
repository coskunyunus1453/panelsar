import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  Loader2,
  RefreshCw,
  Shield,
  ShieldCheck,
  ShieldX,
} from 'lucide-react'
import toast from 'react-hot-toast'
import { useAuthStore } from '../store/authStore'
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

export default function SecurityPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const isAdmin = useAuthStore((s) => s.user?.roles?.some((r) => r.name === 'admin'))
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
      toast.success('Kural gönderildi')
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string }; status?: number } }
      if (ax.response?.status === 403) toast.error('Yalnızca yönetici')
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
      toast.success('Güvenlik ayarı güncellendi')
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string }; status?: number } }
      if (ax.response?.status === 403) toast.error('Yalnızca yönetici')
      else toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const clamavScanM = useMutation({
    mutationFn: async () => (await api.post('/security/clamav/scan', { target: scanTarget })).data,
    onSuccess: (res) => {
      setScanOutput(String(res?.result?.output ?? ''))
      toast.success('ClamAV taraması tamamlandı')
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string; result?: { output?: string } }; status?: number } }
      setScanOutput(String(ax.response?.data?.result?.output ?? ''))
      if (ax.response?.status === 403) toast.error('Yalnızca yönetici')
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
      toast.success('Fail2ban ayarları güncellendi')
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
      if (res?.result?.report?.dry_run) toast.success('Dry-run tamamlandı')
      else toast.success('Orphan mail kayıt temizliği tamamlandı')
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; result?: { error?: string } } } }
      toast.error(ax.response?.data?.result?.error ?? ax.response?.data?.message ?? String(err))
    },
  })

  type Overview = {
    fail2ban?: {
      enabled?: boolean
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
    modsecurity?: { enabled?: boolean; error?: string }
    clamav?: { enabled?: boolean; last_scan?: unknown; error?: string }
  }

  const overview = q.data?.overview as Overview | undefined
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

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Shield className="h-8 w-8 text-red-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.security')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('security.subtitle')}</p>
        </div>
      </div>

      <div className="card p-6 space-y-4">
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-sm font-semibold">Engine özeti</h3>
          <button
            type="button"
            className="btn-secondary inline-flex items-center gap-2 text-sm"
            onClick={() => void q.refetch()}
            disabled={q.isLoading || q.isFetching}
            title="Yenile"
          >
            <RefreshCw className={q.isFetching ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
            Yenile
          </button>
        </div>
        {q.isLoading ? (
          <p className="text-gray-500">{t('common.loading')}</p>
        ) : q.isError ? (
          <p className="text-sm text-amber-600">Özet alınamadı.</p>
        ) : (
          <>
            <div className="grid gap-3 lg:grid-cols-4 text-sm">
              <div
                className={[
                  'rounded-xl border p-4 transition-colors',
                  coverage.fail2banOk
                    ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/50 dark:bg-emerald-900/10'
                    : 'border-amber-200 bg-amber-50/60 dark:border-amber-900/50 dark:bg-amber-900/10',
                ].join(' ')}
              >
                <div className="flex items-center justify-between">
                  <p className="text-gray-500 text-xs">Fail2ban</p>
                  {coverage.fail2banOk ? (
                    <ShieldCheck className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                  ) : (
                    <ShieldX className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                  )}
                </div>
                <p className="mt-2 text-sm font-semibold">
                  Durum: {coverage.fail2banOk ? 'açık' : 'kapalı'}
                </p>
                {overview?.fail2ban?.jails && overview.fail2ban.jails.length > 0 && (
                  <p className="mt-2 text-xs text-gray-600 dark:text-gray-300 font-mono">
                    {overview.fail2ban.jails.join(', ')}
                  </p>
                )}
                {isAdmin && (
                  <button
                    type="button"
                    className="mt-3 rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                    onClick={() => toggleM.mutate({ key: 'fail2ban', enabled: !coverage.fail2banOk })}
                    disabled={toggleM.isPending}
                  >
                    {coverage.fail2banOk ? 'Kapat' : 'Aç'}
                  </button>
                )}
                {overview?.fail2ban?.error && (
                  <p className="mt-2 text-[11px] text-red-600">{String(overview.fail2ban.error)}</p>
                )}
              </div>

              <div
                className={[
                  'rounded-xl border p-4 transition-colors',
                  coverage.firewallOk
                    ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/50 dark:bg-emerald-900/10'
                    : 'border-amber-200 bg-amber-50/60 dark:border-amber-900/50 dark:bg-amber-900/10',
                ].join(' ')}
              >
                <div className="flex items-center justify-between">
                  <p className="text-gray-500 text-xs">Firewall</p>
                  {coverage.firewallOk ? (
                    <CheckCircle2 className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                  ) : (
                    <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                  )}
                </div>
                <p className="mt-2 text-sm font-semibold">
                  <span className="font-mono">{overview?.firewall?.backend ?? '—'}</span>
                </p>
                <p className="mt-1 text-xs text-gray-600 dark:text-gray-300 font-mono">
                  varsayılan: {overview?.firewall?.default_policy ?? '—'}
                </p>
              </div>

              <div
                className={[
                  'rounded-xl border p-4 transition-colors',
                  coverage.modsecOk
                    ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/50 dark:bg-emerald-900/10'
                    : 'border-amber-200 bg-amber-50/60 dark:border-amber-900/50 dark:bg-amber-900/10',
                ].join(' ')}
              >
                <div className="flex items-center justify-between">
                  <p className="text-gray-500 text-xs">ModSecurity</p>
                  {coverage.modsecOk ? (
                    <CheckCircle2 className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                  ) : (
                    <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                  )}
                </div>
                <p className="mt-2 text-sm font-semibold">Durum: {coverage.modsecOk ? 'açık' : 'kapalı'}</p>
                {isAdmin && (
                  <button
                    type="button"
                    className="mt-3 rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                    onClick={() => toggleM.mutate({ key: 'modsecurity', enabled: !coverage.modsecOk })}
                    disabled={toggleM.isPending}
                  >
                    {coverage.modsecOk ? 'Kapat' : 'Aç'}
                  </button>
                )}
                {overview?.modsecurity?.error && (
                  <p className="mt-2 text-[11px] text-red-600">{String(overview.modsecurity.error)}</p>
                )}
              </div>

              <div
                className={[
                  'rounded-xl border p-4 transition-colors',
                  coverage.clamavOk
                    ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/50 dark:bg-emerald-900/10'
                    : 'border-amber-200 bg-amber-50/60 dark:border-amber-900/50 dark:bg-amber-900/10',
                ].join(' ')}
              >
                <div className="flex items-center justify-between">
                  <p className="text-gray-500 text-xs">ClamAV</p>
                  <Clock className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                </div>
                <p className="mt-2 text-sm font-semibold">Son tarama</p>
                <p className="mt-1 text-xs text-gray-600 dark:text-gray-300 font-mono">
                  {overview?.clamav?.last_scan != null ? String(overview.clamav.last_scan) : 'kayıt yok'}
                </p>
                {isAdmin && (
                  <button
                    type="button"
                    className="mt-3 rounded-lg border border-gray-300 px-2 py-1 text-xs hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                    onClick={() => toggleM.mutate({ key: 'clamav', enabled: !coverage.clamavOk })}
                    disabled={toggleM.isPending}
                  >
                    {coverage.clamavOk ? 'Kapat' : 'Aç'}
                  </button>
                )}
                {overview?.clamav?.error && (
                  <p className="mt-2 text-[11px] text-red-600">{String(overview.clamav.error)}</p>
                )}
              </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-3 mt-4">
              <div className="rounded-xl border border-gray-100 dark:border-gray-800 p-4">
                <div className="flex items-center justify-between">
                  <p className="text-sm font-semibold">Güvenlik kapsaması</p>
                  <span className="text-xs text-gray-500 dark:text-gray-400 font-mono">
                    {coverage.enabledCount}/{coverage.total} ({coverage.pct}%)
                  </span>
                </div>
                <div className="mt-3" style={{ height: 190 }}>
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Tooltip />
                      <Legend />
                      <Pie
                        data={coveragePieData}
                        dataKey="value"
                        nameKey="name"
                        innerRadius={62}
                        outerRadius={82}
                        stroke="none"
                        isAnimationActive
                      >
                        {coveragePieData.map((p) => (
                          <Cell key={p.key} fill={p.color} />
                        ))}
                      </Pie>
                    </PieChart>
                  </ResponsiveContainer>
                </div>
                <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                  Modül durumu (Fail2ban, Firewall, ModSecurity, ClamAV) üzerinden yüzdesel özet.
                </p>
              </div>

              <div className="rounded-xl border border-gray-100 dark:border-gray-800 p-4 lg:col-span-2">
                <p className="text-sm font-semibold">Eylem & protokol dağılımı</p>
                <div className="mt-3 grid gap-4 sm:grid-cols-2">
                  <div className="rounded-lg border border-gray-100 dark:border-gray-800 p-3">
                    <p className="text-xs text-gray-500 dark:text-gray-400 font-semibold mb-2">Eylem</p>
                    <div style={{ height: 190 }}>
                      <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={actionDist}>
                          <CartesianGrid strokeDasharray="3 3" />
                          <XAxis dataKey="label" />
                          <Tooltip />
                          <Legend />
                          <Bar dataKey="value" radius={[8, 8, 8, 8]}>
                            {actionDist.map((a) => (
                              <Cell key={a.key} fill={a.color} />
                            ))}
                          </Bar>
                        </BarChart>
                      </ResponsiveContainer>
                    </div>
                  </div>

                  <div className="rounded-lg border border-gray-100 dark:border-gray-800 p-3">
                    <p className="text-xs text-gray-500 dark:text-gray-400 font-semibold mb-2">Protokol</p>
                    <div style={{ height: 190 }}>
                      <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                          <Tooltip />
                          <Legend />
                          <Pie
                            data={protocolDist}
                            dataKey="value"
                            nameKey="label"
                            innerRadius={45}
                            outerRadius={70}
                            isAnimationActive
                          >
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
            </div>

            {fwRules.length > 0 && (
              <div>
                <p className="text-sm font-semibold mb-2">Son güvenlik duvarı kuralları</p>
                <div className="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                  <table className="w-full text-sm">
                    <thead className="bg-gray-50 dark:bg-gray-800/80">
                      <tr>
                        <th className="text-left px-3 py-2">Eylem</th>
                        <th className="text-left px-3 py-2">Protokol</th>
                        <th className="text-left px-3 py-2">Port</th>
                        <th className="text-left px-3 py-2">Kaynak</th>
                        <th className="text-left px-3 py-2">Zaman</th>
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
                                  ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-200 border-emerald-200 dark:border-emerald-900/40'
                                  : a === 'deny'
                                    ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-200 border-red-200 dark:border-red-900/40'
                                    : 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-200 border-amber-200 dark:border-amber-900/40'
                              return (
                                <span className={`inline-flex items-center rounded-full border px-2 py-1 text-xs font-mono ${cls}`}>
                                  {String(r.action ?? '—')}
                                </span>
                              )
                            })()}
                          </td>
                          <td className="px-3 py-2 font-mono">{String(r.protocol ?? '—')}</td>
                          <td className="px-3 py-2 font-mono">{String(r.port ?? '—')}</td>
                          <td className="px-3 py-2 font-mono text-xs">{String(r.source ?? '—')}</td>
                          <td className="px-3 py-2 text-xs text-gray-500">
                            {String(r.applied_at ?? '—')}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {isAdmin && (
        <div className="card p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Mail state temizliği (admin)</h3>
          <div className="mb-6 rounded-xl border border-red-200 bg-red-50/40 p-4 dark:border-red-900/40 dark:bg-red-950/20">
            <p className="text-sm font-semibold text-red-800 dark:text-red-200">Güvenlik uyarısı</p>
            <p className="mt-1 text-xs text-red-700 dark:text-red-300">
              Bu araç panelde var olmayan domainlere ait engine mail state kayıtlarını siler. Yanlış kullanımda
              beklenmeyen domain mail yapılandırmaları kaybolabilir. Önce mutlaka Dry-run çalıştırın.
            </p>
            <div className="mt-3 flex flex-wrap items-center gap-2">
              <button
                type="button"
                className="btn-secondary"
                onClick={() => mailReconcileM.mutate({ dry_run: true })}
                disabled={mailReconcileM.isPending}
              >
                {mailReconcileM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Dry-run raporu al'}
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
                onClick={() =>
                  mailReconcileM.mutate({ dry_run: false, confirm: mailConfirm.trim() || undefined })
                }
                disabled={mailReconcileM.isPending}
              >
                Onayla ve temizle
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

          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Fail2ban gelişmiş (admin)</h3>
          <div className="mb-6 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div className="grid gap-3 sm:grid-cols-3">
              <div>
                <label className="label">Ban süresi (sn)</label>
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
                <label className="label">Pencere (sn)</label>
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
                <label className="label">Maks deneme</label>
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
            <div className="mt-3 flex items-center justify-between gap-3">
              <p className="text-xs text-gray-500">Öneri: bantime 3600, findtime 600, maxretry 5</p>
              <button type="button" className="btn-primary" onClick={() => jailM.mutate()} disabled={jailM.isPending}>
                {jailM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Fail2ban ayarlarını uygula'}
              </button>
            </div>
            {overview?.fail2ban?.settings?.error && (
              <p className="mt-2 text-xs text-red-600">{String(overview.fail2ban.settings.error)}</p>
            )}
          </div>

          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">ClamAV tarama (admin)</h3>
          <div className="mb-6 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div className="flex flex-wrap items-end gap-2">
              <div className="min-w-[260px] flex-1">
                <label className="label">Hedef dizin (mutlak yol)</label>
                <input
                  className="input w-full font-mono"
                  value={scanTarget}
                  onChange={(e) => setScanTarget(e.target.value)}
                  placeholder="/var/www"
                />
              </div>
              <button type="button" className="btn-primary" onClick={() => clamavScanM.mutate()} disabled={clamavScanM.isPending}>
                {clamavScanM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Taramayı başlat'}
              </button>
            </div>
            <pre className="mt-3 max-h-64 overflow-auto rounded-lg bg-black p-3 text-xs text-green-200 whitespace-pre-wrap">
              {scanOutput || 'Henüz tarama çıktısı yok'}
            </pre>
          </div>

          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Güvenlik duvarı kuralı (admin)
          </h3>
          <form
            className="grid sm:grid-cols-2 gap-4 max-w-xl"
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
              <label className="label">Eylem</label>
              <select name="action" className="input w-full">
                <option value="allow">allow</option>
                <option value="deny">deny</option>
              </select>
            </div>
            <div>
              <label className="label">Protokol</label>
              <select name="protocol" className="input w-full">
                <option value="tcp">tcp</option>
                <option value="udp">udp</option>
                <option value="icmp">icmp</option>
                <option value="any">any</option>
              </select>
            </div>
            <div>
              <label className="label">Port</label>
              <input name="port" className="input w-full" placeholder="443" />
            </div>
            <div>
              <label className="label">Kaynak</label>
              <input name="source" className="input w-full" placeholder="0.0.0.0/0" />
            </div>
            <div className="sm:col-span-2">
              <button type="submit" className="btn-primary" disabled={fwM.isPending}>
                Uygula
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  )
}
