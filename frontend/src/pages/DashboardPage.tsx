import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import type { DashboardData } from '../types'
import {
  Globe,
  Database,
  Mail,
  HardDrive,
  Activity,
  Cpu,
  MemoryStick,
  Plus,
  Users,
} from 'lucide-react'

export default function DashboardPage() {
  const { t } = useTranslation()
  const user = useAuthStore((s) => s.user)
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')

  const dashQ = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await api.get('/dashboard')).data.dashboard as DashboardData,
  })

  const d = dashQ.data
  const sys = d?.system_stats

  const statCards = [
    {
      label: t('dashboard.domains_count'),
      value: d?.domains_count ?? '—',
      icon: Globe,
      color: 'text-blue-500',
      bg: 'bg-blue-50 dark:bg-blue-900/20',
    },
    {
      label: t('dashboard.databases_count'),
      value: d?.databases_count ?? '—',
      icon: Database,
      color: 'text-green-500',
      bg: 'bg-green-50 dark:bg-green-900/20',
    },
    {
      label: t('dashboard.email_count'),
      value: d?.email_accounts_count ?? '—',
      icon: Mail,
      color: 'text-purple-500',
      bg: 'bg-purple-50 dark:bg-purple-900/20',
    },
    {
      label: t('dashboard.disk_usage'),
      value:
        sys != null && sys.disk_used != null && sys.disk_total != null
          ? `${Math.round((sys.disk_used / 1024 / 1024 / 1024) * 10) / 10} / ${Math.round((sys.disk_total / 1024 / 1024 / 1024) * 10) / 10} GB`
          : `${t('dashboard.disk_usage')} (—)`,
      icon: HardDrive,
      color: 'text-orange-500',
      bg: 'bg-orange-50 dark:bg-orange-900/20',
      progress: sys?.disk_percent ?? undefined,
    },
  ]

  const adminExtras = isAdmin && d
    ? [
        {
          label: t('nav.users'),
          value: d.total_users ?? '—',
          icon: Users,
          color: 'text-indigo-500',
          bg: 'bg-indigo-50 dark:bg-indigo-900/20',
        },
        {
          label: t('nav.domains'),
          value: d.total_domains ?? '—',
          icon: Globe,
          color: 'text-cyan-500',
          bg: 'bg-cyan-50 dark:bg-cyan-900/20',
        },
      ]
    : []

  const systemCards =
    sys != null
      ? [
          {
            label: t('dashboard.cpu_usage'),
            value: `${Math.round(sys.cpu_usage ?? 0)}%`,
            icon: Cpu,
            progress: Math.min(100, Math.round(sys.cpu_usage ?? 0)),
            color:
              (sys.cpu_usage ?? 0) > 80 ? 'bg-red-500' : 'bg-green-500',
          },
          {
            label: t('dashboard.memory_usage'),
            value: `${Math.round(sys.memory_percent ?? 0)}%`,
            icon: MemoryStick,
            progress: Math.min(100, Math.round(sys.memory_percent ?? 0)),
            color:
              (sys.memory_percent ?? 0) > 80 ? 'bg-red-500' : 'bg-yellow-500',
          },
          {
            label: t('dashboard.bandwidth'),
            value: sys.uptime != null ? `${Math.floor(sys.uptime / 3600)}h` : '—',
            icon: Activity,
            progress: 0,
            color: 'bg-blue-500',
          },
        ]
      : []

  const quickActions = [
    { label: t('dashboard.create_site'), icon: Globe, path: '/domains' },
    { label: t('dashboard.create_database'), icon: Database, path: '/databases' },
    { label: t('dashboard.create_email'), icon: Mail, path: '/email' },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
          {t('dashboard.overview')}
        </h1>
        <p className="text-gray-500 dark:text-gray-400 mt-1">
          {t('dashboard.welcome')}, {user?.name}
        </p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {[...statCards, ...adminExtras].map((stat) => (
          <div key={stat.label} className="card p-5">
            <div className="flex items-center justify-between mb-3">
              <div className={`p-2.5 rounded-xl ${stat.bg}`}>
                <stat.icon className={`h-5 w-5 ${stat.color}`} />
              </div>
            </div>
            <p className="text-2xl font-bold text-gray-900 dark:text-white">
              {dashQ.isLoading ? '…' : stat.value}
            </p>
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
              {stat.label}
            </p>
            {'progress' in stat && stat.progress !== undefined && (
              <div className="mt-3 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                <div
                  className="bg-primary-500 h-1.5 rounded-full transition-all"
                  style={{ width: `${stat.progress}%` }}
                />
              </div>
            )}
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 card p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {t('dashboard.system_status')}
          </h3>
          {!isAdmin || !sys ? (
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {isAdmin
                ? 'Engine çalışmıyorsa sunucu istatistikleri görünmez. Engine ve ENGINE_INTERNAL_KEY kontrol edin.'
                : 'Sunucu kaynak özeti yalnızca yöneticiler için engine üzerinden gösterilir.'}
            </p>
          ) : (
            <div className="space-y-4">
              {systemCards.map((item) => (
                <div key={item.label} className="flex items-center gap-4">
                  <div className="p-2 rounded-lg bg-gray-100 dark:bg-gray-800">
                    <item.icon className="h-5 w-5 text-gray-600 dark:text-gray-400" />
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {item.label}
                      </span>
                      <span className="text-sm font-semibold text-gray-900 dark:text-white">
                        {item.value}
                      </span>
                    </div>
                    <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                      <div
                        className={`${item.color} h-2 rounded-full transition-all`}
                        style={{ width: `${item.progress}%` }}
                      />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="card p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {t('dashboard.quick_actions')}
          </h3>
          <div className="space-y-3">
            {quickActions.map((action) => (
              <Link
                key={action.path}
                to={action.path}
                className="w-full flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors text-left"
              >
                <div className="p-2 rounded-lg bg-primary-50 dark:bg-primary-900/20">
                  <Plus className="h-4 w-4 text-primary-600 dark:text-primary-400" />
                </div>
                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                  {action.label}
                </span>
              </Link>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}
