import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, FileText, RefreshCcw } from 'lucide-react'
import api from '../services/api'

type DomainRow = {
  id: number
  name: string
}

type DomainLogEntry = {
  type: string
  path: string
  exists: boolean
  content: string
  error?: string
}

type DiagnosticItem = {
  level: 'critical' | 'warning' | 'info'
  label: string
  hint: string
  count: number
}

type TabBadge = {
  errors: number
  warnings: number
}

function buildDiagnostics(logs: DomainLogEntry[]): DiagnosticItem[] {
  const bucket = new Map<string, DiagnosticItem>()
  const push = (key: string, level: DiagnosticItem['level'], label: string, hint: string) => {
    const prev = bucket.get(key)
    if (prev) {
      prev.count += 1
      return
    }
    bucket.set(key, { level, label, hint, count: 1 })
  }

  for (const entry of logs) {
    const raw = String(entry.content || '').toLowerCase()
    if (!raw) continue
    const lines = raw.split('\n')
    for (const line of lines) {
      if (!line.trim()) continue
      if (line.includes(' 500 ') || line.includes('error 500') || line.includes('internal server error')) {
        push('http500', 'critical', 'HTTP 500', 'Uygulama logunu kontrol edin (PHP/Laravel exception olabilir).')
      }
      if (line.includes(' 502 ') || line.includes(' 503 ') || line.includes(' 504 ')) {
        push('gateway', 'critical', '502/503/504', 'PHP-FPM veya upstream servis durumu kontrol edilmeli.')
      }
      if (line.includes('timeout') || line.includes('timed out') || line.includes('upstream timed out')) {
        push('timeout', 'warning', 'Timeout', 'Uzun süren sorgu/işlem veya yetersiz kaynak olabilir.')
      }
      if (line.includes('sqlstate') || line.includes('mysql') || line.includes('pdoexception') || line.includes('database connection')) {
        push('db', 'critical', 'Veritabanı hatası', 'DB kullanıcı/şifre/host ve servis durumunu doğrulayın.')
      }
      if (line.includes('permission denied') || line.includes('eacces')) {
        push('perm', 'warning', 'İzin hatası', 'Dosya sahipliği ve chmod değerlerini kontrol edin.')
      }
      if (line.includes('not found') || line.includes(' 404 ')) {
        push('404', 'info', '404/Not Found', 'Eksik dosya/route veya yanlış URL olabilir.')
      }
    }
  }

  return Array.from(bucket.values()).sort((a, b) => {
    const prio = (x: DiagnosticItem['level']) => (x === 'critical' ? 3 : x === 'warning' ? 2 : 1)
    return prio(b.level) - prio(a.level) || b.count - a.count
  })
}

function buildTabBadges(logs: DomainLogEntry[]): Record<string, TabBadge> {
  const out: Record<string, TabBadge> = {}
  for (const entry of logs) {
    const key = entry.type
    const raw = String(entry.content || '').toLowerCase()
    const lines = raw.split('\n')
    let errors = 0
    let warnings = 0
    for (const line of lines) {
      if (!line.trim()) continue
      if (
        line.includes('error') ||
        line.includes('exception') ||
        line.includes('fatal') ||
        line.includes(' 500 ') ||
        line.includes(' 502 ') ||
        line.includes(' 503 ') ||
        line.includes(' 504 ')
      ) {
        errors += 1
        continue
      }
      if (
        line.includes('warning') ||
        line.includes('deprecated') ||
        line.includes('notice') ||
        line.includes('timeout')
      ) {
        warnings += 1
      }
    }
    out[key] = { errors, warnings }
  }
  return out
}

export default function AdminLogsPage() {
  const { t } = useTranslation()
  const [domainId, setDomainId] = useState<number | ''>('')
  const [logLines, setLogLines] = useState(200)
  const [activeTab, setActiveTab] = useState<string>('')

  const domainsQ = useQuery({
    queryKey: ['domains', 'paginated'],
    queryFn: async () => (await api.get('/domains')).data as { data?: DomainRow[] },
  })

  const logsQ = useQuery({
    queryKey: ['admin-logs', domainId, logLines],
    enabled: !!domainId,
    queryFn: async () => {
      const { data } = await api.get<{ logs: DomainLogEntry[] }>(`/domains/${domainId}/logs?lines=${logLines}`)
      return data
    },
  })

  const diagnostics = useMemo(() => buildDiagnostics(logsQ.data?.logs ?? []), [logsQ.data?.logs])
  const tabBadges = useMemo(() => buildTabBadges(logsQ.data?.logs ?? []), [logsQ.data?.logs])
  const domains = domainsQ.data?.data ?? []
  const logEntries = logsQ.data?.logs ?? []

  const orderedTabs = useMemo(() => {
    const arr = [...logEntries]
    arr.sort((a, b) => {
      const al = `${a.type} ${a.path}`.toLowerCase()
      const bl = `${b.type} ${b.path}`.toLowerCase()
      const aLaravel = al.includes('laravel')
      const bLaravel = bl.includes('laravel')
      if (aLaravel !== bLaravel) return aLaravel ? -1 : 1
      return a.type.localeCompare(b.type)
    })
    return arr
  }, [logEntries])

  useEffect(() => {
    if (!orderedTabs.length) {
      setActiveTab('')
      return
    }
    const stillExists = orderedTabs.some((x) => x.type === activeTab)
    if (!stillExists) {
      setActiveTab(orderedTabs[0].type)
    }
  }, [orderedTabs, activeTab])

  const activeEntry = orderedTabs.find((x) => x.type === activeTab) ?? orderedTabs[0] ?? null

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('logs_page.title')}</h1>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{t('logs_page.subtitle')}</p>
      </div>

      <div className="card p-4 space-y-4">
        <div className="grid gap-3 md:grid-cols-[1fr_auto_auto]">
          <select
            className="input w-full"
            value={domainId}
            onChange={(e) => setDomainId(e.target.value ? Number(e.target.value) : '')}
          >
            <option value="">{t('logs_page.select_domain')}</option>
            {domains.map((d) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>
          <select className="input w-full md:w-32" value={logLines} onChange={(e) => setLogLines(Number(e.target.value))}>
            <option value={100}>100</option>
            <option value={200}>200</option>
            <option value={500}>500</option>
            <option value={1000}>1000</option>
          </select>
          <button type="button" className="btn-secondary inline-flex items-center gap-2" onClick={() => void logsQ.refetch()}>
            <RefreshCcw className="h-4 w-4" />
            {t('common.refresh')}
          </button>
        </div>

        {!!domainId && (
          <>
            <div className="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-3">
              <p className="text-sm font-semibold text-amber-900 dark:text-amber-200">{t('logs_page.quick_diagnosis')}</p>
              {diagnostics.length === 0 ? (
                <p className="mt-1 text-sm text-amber-800/90 dark:text-amber-200/90">{t('logs_page.no_critical_found')}</p>
              ) : (
                <ul className="mt-2 space-y-2 text-sm">
                  {diagnostics.slice(0, 5).map((d, idx) => (
                    <li key={`${d.label}-${idx}`} className="flex items-start gap-2">
                      <AlertTriangle className={`mt-0.5 h-4 w-4 ${d.level === 'critical' ? 'text-rose-600' : d.level === 'warning' ? 'text-amber-600' : 'text-sky-600'}`} />
                      <span className="text-gray-800 dark:text-gray-200">
                        <strong>{d.label}</strong> ({d.count}) - {d.hint}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            {logsQ.isLoading && <p className="text-sm text-gray-500">{t('common.loading')}</p>}

            {!logsQ.isLoading && (logsQ.data?.logs ?? []).length === 0 && (
              <p className="text-sm text-gray-500">{t('domains.logs_empty')}</p>
            )}

            {!logsQ.isLoading && orderedTabs.length > 0 && (
              <div className="space-y-3">
                <div className="flex flex-wrap gap-2">
                  {orderedTabs.map((entry) => {
                    const isActive = (activeEntry?.type ?? '') === entry.type
                    const laravelBadge = `${entry.type} ${entry.path}`.toLowerCase().includes('laravel')
                    return (
                      <button
                        key={entry.type}
                        type="button"
                        className={`rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors ${
                          isActive
                            ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                            : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'
                        }`}
                        onClick={() => setActiveTab(entry.type)}
                      >
                        {entry.type}
                        {laravelBadge ? ' (Laravel)' : ''}
                        {tabBadges[entry.type] && (
                          <span className="ml-2 inline-flex items-center gap-1">
                            {tabBadges[entry.type].errors > 0 && (
                              <span className="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-900/40 dark:text-rose-200">
                                E:{tabBadges[entry.type].errors}
                              </span>
                            )}
                            {tabBadges[entry.type].warnings > 0 && (
                              <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">
                                W:{tabBadges[entry.type].warnings}
                              </span>
                            )}
                          </span>
                        )}
                      </button>
                    )
                  })}
                </div>

                {activeEntry && (
                  <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    <div className="flex items-center justify-between bg-gray-50 px-3 py-2 dark:bg-gray-800/70">
                      <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-300 inline-flex items-center gap-1">
                          <FileText className="h-3.5 w-3.5" />
                          {activeEntry.type}
                        </p>
                        <p className="truncate text-[11px] text-gray-500">{activeEntry.path}</p>
                      </div>
                      {!activeEntry.exists && <span className="text-xs text-amber-600">{t('domains.logs_not_found')}</span>}
                    </div>
                    <pre className="max-h-[520px] overflow-auto bg-black p-3 text-xs text-green-200 whitespace-pre-wrap">
                      {activeEntry.error
                        ? `${t('domains.logs_read_error')}: ${activeEntry.error}`
                        : activeEntry.content?.trim() || t('domains.logs_no_content')}
                    </pre>
                  </div>
                )}
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}

