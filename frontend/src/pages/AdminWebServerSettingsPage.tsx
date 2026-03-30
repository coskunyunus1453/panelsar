import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { ServerCog, Save, AlertTriangle } from 'lucide-react'
import toast from 'react-hot-toast'
import { tokenHasAbility } from '../lib/abilities'

type WebServerSettings = {
  nginx_manage_vhosts: boolean
  nginx_reload_after_vhost: boolean
  apache_manage_vhosts: boolean
  apache_reload_after_vhost: boolean
  php_fpm_manage_pools: boolean
  php_fpm_reload_after_pool: boolean
  php_fpm_socket: string
  php_fpm_listen_dir: string
  php_fpm_pool_dir_template: string
  php_fpm_pool_user: string
  php_fpm_pool_group: string
}

export default function AdminWebServerSettingsPage() {
  const { t } = useTranslation()
  const user = useAuthStore((s) => s.user)
  const abilities = user?.abilities
  const canRead = tokenHasAbility(abilities, 'webserver:read')
  const canWrite = tokenHasAbility(abilities, 'webserver:write')
  const canView = canRead || canWrite

  const settingsQ = useQuery({
    queryKey: ['admin-webserver-settings'],
    queryFn: async () => (await api.get('/admin/settings/webserver')).data as { settings: WebServerSettings },
    enabled: canView,
  })

  const [form, setForm] = useState<WebServerSettings | null>(null)
  useEffect(() => {
    if (settingsQ.data?.settings) setForm(settingsQ.data.settings)
  }, [settingsQ.data])

  const canEdit = useMemo(() => !!form && canWrite, [form, canWrite])

  const saveM = useMutation({
    mutationFn: async () => {
      if (!form) return
      const payload = {
        nginx_manage_vhosts: form.nginx_manage_vhosts,
        nginx_reload_after_vhost: form.nginx_reload_after_vhost,
        apache_manage_vhosts: form.apache_manage_vhosts,
        apache_reload_after_vhost: form.apache_reload_after_vhost,
        php_fpm_manage_pools: form.php_fpm_manage_pools,
        php_fpm_reload_after_pool: form.php_fpm_reload_after_pool,
        php_fpm_socket: form.php_fpm_socket,
        php_fpm_listen_dir: form.php_fpm_listen_dir,
        php_fpm_pool_dir_template: form.php_fpm_pool_dir_template,
        php_fpm_pool_user: form.php_fpm_pool_user,
        php_fpm_pool_group: form.php_fpm_pool_group,
        reload: true,
      }
      return api.put('/admin/settings/webserver', payload)
    },
    onSuccess: () => {
      toast.success(t('webserver.saved'))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  if (!canView) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div className="flex items-start gap-4">
          <div className="p-3 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 text-white shadow-lg shadow-primary-500/25">
            <ServerCog className="h-8 w-8" />
          </div>
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{t('webserver.title')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm mt-0.5">{t('webserver.subtitle')}</p>
          </div>
        </div>
        <button
          type="button"
          className="btn-primary inline-flex items-center gap-2"
          disabled={!canEdit || saveM.isPending || settingsQ.isLoading}
          onClick={() => saveM.mutate()}
        >
          <Save className="h-4 w-4" />
          {t('common.save')}
        </button>
      </div>

      {(settingsQ.isError || (settingsQ.data && !form)) && (
        <div className="flex items-start gap-3 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-950/30 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
          <AlertTriangle className="h-5 w-5 flex-shrink-0 mt-0.5" />
          <div>
            <p className="font-medium">{t('webserver.load_error')}</p>
            <p className="text-amber-800/80 dark:text-amber-300/80 mt-1">{t('webserver.engine_hint')}</p>
          </div>
        </div>
      )}

      <div className="card p-6 space-y-5">
        <section className="space-y-3">
          <h2 className="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{t('webserver.nginx')}</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label className="flex items-center gap-3 text-sm">
              <input
                type="checkbox"
                className="rounded border-gray-300"
                checked={form?.nginx_manage_vhosts ?? false}
                onChange={(e) => setForm((s) => (s ? { ...s, nginx_manage_vhosts: e.target.checked } : s))}
              />
              {t('webserver.manage_vhosts')}
            </label>
            <label className="flex items-center gap-3 text-sm">
              <input
                type="checkbox"
                className="rounded border-gray-300"
                checked={form?.nginx_reload_after_vhost ?? false}
                onChange={(e) => setForm((s) => (s ? { ...s, nginx_reload_after_vhost: e.target.checked } : s))}
              />
              {t('webserver.reload_after_vhost')}
            </label>
          </div>
        </section>

        <section className="space-y-3">
          <h2 className="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{t('webserver.apache')}</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label className="flex items-center gap-3 text-sm">
              <input
                type="checkbox"
                className="rounded border-gray-300"
                checked={form?.apache_manage_vhosts ?? false}
                onChange={(e) => setForm((s) => (s ? { ...s, apache_manage_vhosts: e.target.checked } : s))}
              />
              {t('webserver.manage_vhosts')}
            </label>
            <label className="flex items-center gap-3 text-sm">
              <input
                type="checkbox"
                className="rounded border-gray-300"
                checked={form?.apache_reload_after_vhost ?? false}
                onChange={(e) => setForm((s) => (s ? { ...s, apache_reload_after_vhost: e.target.checked } : s))}
              />
              {t('webserver.reload_after_vhost')}
            </label>
          </div>
        </section>

        <section className="space-y-3">
          <h2 className="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{t('webserver.php_fpm')}</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label className="flex items-center gap-3 text-sm">
              <input
                type="checkbox"
                className="rounded border-gray-300"
                checked={form?.php_fpm_manage_pools ?? false}
                onChange={(e) => setForm((s) => (s ? { ...s, php_fpm_manage_pools: e.target.checked } : s))}
              />
              {t('webserver.manage_pools')}
            </label>
            <label className="flex items-center gap-3 text-sm">
              <input
                type="checkbox"
                className="rounded border-gray-300"
                checked={form?.php_fpm_reload_after_pool ?? false}
                onChange={(e) => setForm((s) => (s ? { ...s, php_fpm_reload_after_pool: e.target.checked } : s))}
              />
              {t('webserver.reload_after_pool')}
            </label>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
            <div>
              <label className="label">{t('webserver.php_fpm_socket')}</label>
              <input
                className="input w-full"
                value={form?.php_fpm_socket ?? ''}
                onChange={(e) => setForm((s) => (s ? { ...s, php_fpm_socket: e.target.value } : s))}
                disabled={!!form?.php_fpm_manage_pools}
              />
            </div>
            <div>
              <label className="label">{t('webserver.php_fpm_listen_dir')}</label>
              <input
                className="input w-full"
                value={form?.php_fpm_listen_dir ?? ''}
                onChange={(e) => setForm((s) => (s ? { ...s, php_fpm_listen_dir: e.target.value } : s))}
                disabled={!form?.php_fpm_manage_pools}
              />
            </div>
            <div>
              <label className="label">{t('webserver.php_fpm_pool_dir_template')}</label>
              <input
                className="input w-full"
                value={form?.php_fpm_pool_dir_template ?? ''}
                onChange={(e) =>
                  setForm((s) => (s ? { ...s, php_fpm_pool_dir_template: e.target.value } : s))
                }
                disabled={!form?.php_fpm_manage_pools}
              />
            </div>
            <div>
              <label className="label">{t('webserver.php_fpm_pool_user')}</label>
              <input
                className="input w-full"
                value={form?.php_fpm_pool_user ?? ''}
                onChange={(e) => setForm((s) => (s ? { ...s, php_fpm_pool_user: e.target.value } : s))}
                disabled={!form?.php_fpm_manage_pools}
              />
            </div>
            <div>
              <label className="label">{t('webserver.php_fpm_pool_group')}</label>
              <input
                className="input w-full"
                value={form?.php_fpm_pool_group ?? ''}
                onChange={(e) => setForm((s) => (s ? { ...s, php_fpm_pool_group: e.target.value } : s))}
                disabled={!form?.php_fpm_manage_pools}
              />
            </div>
          </div>

          <p className="text-xs text-gray-500 dark:text-gray-400">
            {t('webserver.save_hint')}
          </p>
        </section>
      </div>
    </div>
  )
}

