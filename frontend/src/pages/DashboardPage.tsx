import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import type { DashboardData } from '../types'
import { Globe, Database, Mail, HardDrive, Plus, Users, Power, RefreshCcw } from 'lucide-react'
import toast from 'react-hot-toast'
import ResourceChartsSection from '../components/dashboard/ResourceChartsSection'

export default function DashboardPage() {
  const { t } = useTranslation()
  const user = useAuthStore((s) => s.user)
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')

  const dashQ = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await api.get('/dashboard')).data.dashboard as DashboardData,
    refetchInterval: isAdmin ? 8_000 : false,
  })

  const d = dashQ.data
  const sys = d?.system_stats

  const rebootM = useMutation({
    mutationFn: async () => api.post('/system/reboot'),
    onSuccess: () => {
      // Sunucu reboot isteği gönderildi; bağlantı kopabilir.
      // Sadece kullanıcıya bildirim veriyoruz.
      toast.success(t('dashboard.reboot_requested'))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const updateClick = () => {
    // Şimdilik sadece placeholder: ileride gerçek update mekanizmasına bağlanacak.
    toast('Güncelleme sistemi yakında eklenecek.', { icon: '⏳' })
  }

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

  const quickActions = [
    { label: t('dashboard.create_site'), icon: Globe, path: '/domains' },
    { label: t('dashboard.create_database'), icon: Database, path: '/databases' },
    { label: t('dashboard.create_email'), icon: Mail, path: '/email' },
  ]

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            {t('dashboard.overview')}
          </h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">
            {t('dashboard.welcome')}, {user?.name}
          </p>
        </div>
        {isAdmin && (
          <div className="flex flex-wrap items-center gap-2">
            <button
              type="button"
              className="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-amber-300 text-amber-900 bg-amber-50 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-100 text-xs sm:text-sm"
              onClick={() => {
                if (
                  window.confirm(
                    t('dashboard.reboot_confirm') ||
                      'Sunucuyu güvenli şekilde yeniden başlatmak istediğinizden emin misiniz? Aktif bağlantılar kesilecektir.',
                  )
                ) {
                  rebootM.mutate()
                }
              }}
              disabled={rebootM.isPending}
            >
              <Power className="h-4 w-4" />
              {t('dashboard.reboot')}
            </button>
            <button
              type="button"
              className="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-sky-300 text-sky-900 bg-sky-50 hover:bg-sky-100 dark:border-sky-700 dark:bg-sky-900/20 dark:text-sky-100 text-xs sm:text-sm"
              onClick={updateClick}
            >
              <RefreshCcw className="h-4 w-4" />
              {t('dashboard.update_panel')}
            </button>
          </div>
        )}
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
          {!isAdmin ? (
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {t('dashboard.system_admin_only')}
            </p>
          ) : !sys ? (
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {t('dashboard.system_engine_hint')}
            </p>
          ) : (
            <div className="space-y-4">
              <ResourceChartsSection stats={sys} loading={dashQ.isLoading} />
              {sys.uptime != null && (
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  {t('dashboard.uptime_approx')}: {Math.floor(sys.uptime / 3600)}h
                </p>
              )}
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
