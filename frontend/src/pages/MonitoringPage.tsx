import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import api from '../services/api'
import { Activity, Cpu, Database, HardDrive, Mail, Server } from 'lucide-react'
import { useAuthStore } from '../store/authStore'

export default function MonitoringPage() {
  const { t } = useTranslation()
  const isAdmin = useAuthStore((s) => s.user?.roles?.some((r) => r.name === 'admin'))

  const summaryQ = useQuery({
    queryKey: ['monitoring-summary'],
    queryFn: async () => (await api.get('/monitoring/summary')).data,
  })

  const serverQ = useQuery({
    queryKey: ['monitoring-server'],
    enabled: !!isAdmin,
    queryFn: async () => (await api.get('/monitoring/server')).data,
  })

  const s = summaryQ.data

  const cards = [
    {
      label: t('nav.domains'),
      value: s?.domains ?? '—',
      icon: Server,
    },
    {
      label: t('nav.databases'),
      value: s?.databases ?? '—',
      icon: Database,
    },
    {
      label: t('nav.email'),
      value: s?.email_accounts ?? '—',
      icon: Mail,
    },
    {
      label: t('dashboard.disk_usage'),
      value: s?.disk_estimate_mb != null ? `${s.disk_estimate_mb} MB (tahmini)` : '—',
      icon: HardDrive,
    },
  ]

  const stats = serverQ.data?.stats
  const servicesRaw = serverQ.data?.services
  const serviceRows: { name?: string; status?: string; enabled?: boolean }[] = Array.isArray(servicesRaw)
    ? servicesRaw
    : []

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Activity className="h-8 w-8 text-rose-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.monitoring')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('monitoring.subtitle')}</p>
        </div>
      </div>

      <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {cards.map((c) => (
          <div key={c.label} className="card p-5">
            <c.icon className="h-6 w-6 text-primary-500 mb-2" />
            <p className="text-2xl font-bold text-gray-900 dark:text-white">
              {summaryQ.isLoading ? '…' : c.value}
            </p>
            <p className="text-sm text-gray-500 mt-1">{c.label}</p>
          </div>
        ))}
      </div>

      {isAdmin && (
        <div className="card p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <Cpu className="h-5 w-5" />
            Sunucu (engine)
          </h3>
          {serverQ.isError && (
            <p className="text-sm text-amber-600">Engine erişilemiyor veya yetki yok.</p>
          )}
          {serverQ.isLoading && <p className="text-gray-500">{t('common.loading')}</p>}
          {stats && (
            <div className="grid sm:grid-cols-2 gap-4 text-sm">
              <div>
                <span className="text-gray-500">CPU</span>
                <p className="font-mono text-lg">{Math.round(stats.cpu_usage ?? 0)}%</p>
              </div>
              <div>
                <span className="text-gray-500">Bellek</span>
                <p className="font-mono text-lg">{Math.round(stats.memory_percent ?? 0)}%</p>
              </div>
              <div>
                <span className="text-gray-500">Disk</span>
                <p className="font-mono text-lg">{Math.round(stats.disk_percent ?? 0)}%</p>
              </div>
              <div>
                <span className="text-gray-500">Host</span>
                <p className="font-mono">{stats.hostname ?? '—'}</p>
              </div>
            </div>
          )}
          {serviceRows.length > 0 && (
            <div className="mt-4">
              <p className="text-sm font-semibold mb-2">Servisler</p>
              <div className="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                <table className="w-full text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-900">
                    <tr>
                      <th className="text-left px-3 py-2">Servis</th>
                      <th className="text-left px-3 py-2">Durum</th>
                      <th className="text-left px-3 py-2">Etkin</th>
                    </tr>
                  </thead>
                  <tbody>
                    {serviceRows.map((s, i) => (
                      <tr
                        key={s.name ?? `svc-${i}`}
                        className="border-t border-gray-100 dark:border-gray-800"
                      >
                        <td className="px-3 py-2 font-mono">{s.name ?? '—'}</td>
                        <td className="px-3 py-2 font-mono">{s.status ?? '—'}</td>
                        <td className="px-3 py-2">{s.enabled ? 'evet' : 'hayır'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
