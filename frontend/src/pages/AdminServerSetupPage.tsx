import { useTranslation } from 'react-i18next'
import { Link, Navigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { Wrench, CheckCircle2, XCircle, AlertCircle, ExternalLink } from 'lucide-react'
import clsx from 'clsx'

type Capabilities = {
  engine: {
    url_configured: boolean
    url: string
    internal_key_set: boolean
    health: Record<string, unknown> | null
    health_error: string | null
    api_ok: boolean
    stats_hostname?: string | null
  }
  hosting_web_root: string
  mysql_provision: { enabled: boolean; host: string; port: number }
  postgres_provision: { enabled: boolean; host: string; port: number }
  wordpress_installer: {
    ready: boolean
    engine_wordpress_automated: boolean
    requires_mysql_db: boolean
  }
  email: { mode: string }
  ui_links: { phpmyadmin_configured: boolean; adminer_configured: boolean }
  admin_system_page: string
}

function StatusIcon({ ok }: { ok: boolean }) {
  return ok ? (
    <CheckCircle2 className="h-5 w-5 text-emerald-500 shrink-0" />
  ) : (
    <XCircle className="h-5 w-5 text-amber-500 shrink-0" />
  )
}

export default function AdminServerSetupPage() {
  const { t } = useTranslation()
  const isAdmin = useAuthStore((s) => s.user?.roles?.some((r) => r.name === 'admin'))

  const q = useQuery({
    queryKey: ['admin-server-capabilities'],
    queryFn: async () => (await api.get('/admin/server/capabilities')).data as Capabilities,
    enabled: !!isAdmin,
  })

  if (!isAdmin) {
    return <Navigate to="/dashboard" replace />
  }

  const d = q.data
  const engineHealthOk = d?.engine.health != null && d.engine.health_error == null
  const engineLineOk = Boolean(d?.engine.api_ok && d.engine.internal_key_set && d.engine.url_configured)

  return (
    <div className="space-y-6 max-w-4xl">
      <div className="flex items-start gap-4">
        <div className="p-3 rounded-2xl bg-primary-500/10 text-primary-600 dark:text-primary-400">
          <Wrench className="h-8 w-8" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.server_setup')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm mt-1">{t('server_setup.subtitle')}</p>
        </div>
      </div>

      <div className="flex items-start gap-2 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-950/30 px-4 py-3 text-sm text-amber-950 dark:text-amber-100">
        <AlertCircle className="h-5 w-5 shrink-0 mt-0.5" />
        <p>{t('server_setup.one_click_note')}</p>
      </div>

      {q.isLoading && <p className="text-gray-500">{t('common.loading')}</p>}
      {q.isError && <p className="text-red-600 text-sm">{t('server_setup.load_error')}</p>}

      {d && (
        <div className="space-y-4">
          <div className="card p-5 space-y-3">
            <div className="flex items-center justify-between gap-3">
              <h2 className="font-semibold text-gray-900 dark:text-white">{t('server_setup.engine')}</h2>
              <StatusIcon ok={engineLineOk && engineHealthOk} />
            </div>
            <ul className="text-sm text-gray-600 dark:text-gray-400 space-y-1">
              <li>
                {t('server_setup.engine_url')}:{' '}
                <span className="font-mono text-xs">{d.engine.url || '—'}</span>
              </li>
              <li>
                {t('server_setup.internal_key')}: {d.engine.internal_key_set ? 'OK' : '—'}
              </li>
              <li>
                /health: {engineHealthOk ? 'OK' : d.engine.health_error || t('server_setup.unreachable')}
              </li>
              <li>
                API (stats): {d.engine.api_ok ? 'OK' : '—'}{' '}
                {d.engine.stats_hostname ? (
                  <span className="font-mono text-xs">({d.engine.stats_hostname})</span>
                ) : null}
              </li>
            </ul>
            <Link
              to="/admin/system"
              className="inline-flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:underline"
            >
              {t('server_setup.open_system')} <ExternalLink className="h-3.5 w-3.5" />
            </Link>
          </div>

          <div className="card p-5 space-y-3">
            <div className="flex items-center justify-between gap-3">
              <h2 className="font-semibold text-gray-900 dark:text-white">{t('server_setup.web_hosting')}</h2>
              <StatusIcon ok={d.engine.api_ok} />
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">{t('server_setup.web_hint')}</p>
            <p className="text-xs font-mono text-gray-500 break-all">{d.hosting_web_root || '—'}</p>
          </div>

          <div className="card p-5 space-y-3">
            <div className="flex items-center justify-between gap-3">
              <h2 className="font-semibold text-gray-900 dark:text-white">{t('server_setup.mysql_title')}</h2>
              <StatusIcon ok={d.mysql_provision.enabled} />
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">{t('server_setup.mysql_hint')}</p>
            <p className="text-xs text-gray-500">
              {d.mysql_provision.host}:{d.mysql_provision.port}
            </p>
          </div>

          <div className="card p-5 space-y-3">
            <div className="flex items-center justify-between gap-3">
              <h2 className="font-semibold text-gray-900 dark:text-white">{t('server_setup.pg_title')}</h2>
              <StatusIcon ok={d.postgres_provision.enabled} />
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">{t('server_setup.pg_hint')}</p>
          </div>

          <div
            className={clsx(
              'card p-5 space-y-3 border-2',
              d.wordpress_installer.ready
                ? 'border-emerald-200 dark:border-emerald-900/40'
                : 'border-gray-100 dark:border-gray-800',
            )}
          >
            <div className="flex items-center justify-between gap-3">
              <h2 className="font-semibold text-gray-900 dark:text-white">{t('server_setup.wp_title')}</h2>
              <StatusIcon ok={d.wordpress_installer.ready} />
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">{t('server_setup.wp_hint')}</p>
            <ul className="text-xs text-gray-500 list-disc list-inside space-y-1">
              <li>{t('server_setup.wp_step_engine')}</li>
              <li>{t('server_setup.wp_step_mysql')}</li>
              <li>{t('server_setup.wp_step_installer')}</li>
            </ul>
            <Link
              to="/installer"
              className="inline-flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:underline"
            >
              {t('nav.installer')} →
            </Link>
          </div>

          <div className="card p-5 space-y-3">
            <div className="flex items-center justify-between gap-3">
              <h2 className="font-semibold text-gray-900 dark:text-white">{t('server_setup.email_title')}</h2>
              <StatusIcon ok={false} />
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">{t('server_setup.email_mirror_hint')}</p>
          </div>

          <div className="card p-5 space-y-3">
            <h2 className="font-semibold text-gray-900 dark:text-white">{t('server_setup.ui_links')}</h2>
            <ul className="text-sm text-gray-600 dark:text-gray-400 space-y-1">
              <li>
                phpMyAdmin URL: {d.ui_links.phpmyadmin_configured ? 'OK' : t('server_setup.not_set')}
              </li>
              <li>
                Adminer URL: {d.ui_links.adminer_configured ? 'OK' : t('server_setup.not_set')}
              </li>
            </ul>
          </div>
        </div>
      )}
    </div>
  )
}
