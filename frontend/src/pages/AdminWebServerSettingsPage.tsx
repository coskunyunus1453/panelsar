import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { ServerCog, Save, AlertTriangle, RefreshCw } from 'lucide-react'
import toast from 'react-hot-toast'
import { tokenHasAbility } from '../lib/abilities'

type WebServerSettings = {
  nginx_manage_vhosts: boolean
  nginx_reload_after_vhost: boolean
  apache_manage_vhosts: boolean
  apache_reload_after_vhost: boolean
  openlitespeed_manage_vhosts?: boolean
  openlitespeed_conf_root?: string
  openlitespeed_reload_after_vhost?: boolean
  openlitespeed_ctrl_path?: string
  php_fpm_manage_pools: boolean
  php_fpm_reload_after_pool: boolean
  php_fpm_socket: string
  php_fpm_listen_dir: string
  php_fpm_pool_dir_template: string
  php_fpm_pool_user: string
  php_fpm_pool_group: string
}

type ApacheModuleRow = {
  name: string
  enabled: boolean
}

type ServiceHealth = {
  installed?: boolean
  active?: boolean
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
    if (settingsQ.data?.settings) {
      const s = settingsQ.data.settings
      setForm({
        ...s,
        openlitespeed_manage_vhosts: s.openlitespeed_manage_vhosts ?? false,
        openlitespeed_conf_root: s.openlitespeed_conf_root ?? '/usr/local/lsws',
        openlitespeed_reload_after_vhost: s.openlitespeed_reload_after_vhost ?? false,
        openlitespeed_ctrl_path: s.openlitespeed_ctrl_path ?? '',
      })
    }
  }, [settingsQ.data])

  const canEdit = useMemo(() => !!form && canWrite, [form, canWrite])
  const [activeTab, setActiveTab] = useState<'general' | 'apache' | 'nginx'>('general')
  const [nginxScope, setNginxScope] = useState<'main' | 'panel'>('main')
  const [nginxContent, setNginxContent] = useState('')

  const saveM = useMutation({
    mutationFn: async () => {
      if (!form) return
      const payload = {
        nginx_manage_vhosts: form.nginx_manage_vhosts,
        nginx_reload_after_vhost: form.nginx_reload_after_vhost,
        apache_manage_vhosts: form.apache_manage_vhosts,
        apache_reload_after_vhost: form.apache_reload_after_vhost,
        openlitespeed_manage_vhosts: form.openlitespeed_manage_vhosts ?? false,
        openlitespeed_conf_root: form.openlitespeed_conf_root ?? '',
        openlitespeed_reload_after_vhost: form.openlitespeed_reload_after_vhost ?? false,
        openlitespeed_ctrl_path: form.openlitespeed_ctrl_path ?? '',
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

  const apacheModulesQ = useQuery({
    queryKey: ['admin-webserver-apache-modules'],
    queryFn: async () => (await api.get('/admin/settings/webserver/apache-modules')).data as { modules: ApacheModuleRow[] },
    enabled: canView,
  })

  const servicesQ = useQuery({
    queryKey: ['admin-webserver-services'],
    queryFn: async () =>
      (await api.get('/admin/settings/webserver/services')).data as {
        services: { nginx?: ServiceHealth; apache?: ServiceHealth; openlitespeed?: ServiceHealth }
      },
    enabled: canView,
  })

  const toggleApacheModuleM = useMutation({
    mutationFn: async ({ name, enabled }: { name: string; enabled: boolean }) =>
      api.post(`/admin/settings/webserver/apache-modules/${encodeURIComponent(name)}`, { enabled }),
    onSuccess: () => {
      void apacheModulesQ.refetch()
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const nginxConfigQ = useQuery({
    queryKey: ['admin-webserver-nginx-config', nginxScope],
    queryFn: async () =>
      (await api.get('/admin/settings/webserver/nginx-config', { params: { scope: nginxScope } })).data as {
        scope: 'main' | 'panel'
        content: string
      },
    enabled: canView,
  })

  useEffect(() => {
    if (nginxConfigQ.data?.content !== undefined) setNginxContent(nginxConfigQ.data.content)
  }, [nginxConfigQ.data?.content, nginxScope])

  const saveNginxM = useMutation({
    mutationFn: async () =>
      api.put('/admin/settings/webserver/nginx-config', { scope: nginxScope, content: nginxContent, test_reload: true }),
    onSuccess: () => {
      toast.success(t('webserver.saved'))
      void nginxConfigQ.refetch()
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

      <div className="card p-4 sm:p-6 space-y-5">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div className="rounded-xl border border-gray-200 dark:border-gray-800 p-3 text-sm">
            <div className="font-semibold">Nginx</div>
            <div className="mt-1 text-xs text-gray-500">
              {servicesQ.data?.services?.nginx?.installed ? 'Kurulu' : 'Kurulu değil'} /{' '}
              {servicesQ.data?.services?.nginx?.active ? 'Aktif' : 'Pasif'}
            </div>
            {!servicesQ.data?.services?.nginx?.installed && (
              <p className="mt-2 text-xs text-amber-600">Nginx kurulu görünmüyor. Bu sekmede düzenleme sınırlı olabilir.</p>
            )}
          </div>
          <div className="rounded-xl border border-gray-200 dark:border-gray-800 p-3 text-sm">
            <div className="font-semibold">Apache</div>
            <div className="mt-1 text-xs text-gray-500">
              {servicesQ.data?.services?.apache?.installed ? 'Kurulu' : 'Kurulu değil'} /{' '}
              {servicesQ.data?.services?.apache?.active ? 'Aktif' : 'Pasif'}
            </div>
            {!servicesQ.data?.services?.apache?.installed && (
              <p className="mt-2 text-xs text-amber-600">Apache kurulu görünmüyor. Modül yönetimi kullanılamaz.</p>
            )}
          </div>
          <div className="rounded-xl border border-gray-200 dark:border-gray-800 p-3 text-sm">
            <div className="font-semibold">{t('webserver.openlitespeed')}</div>
            <div className="mt-1 text-xs text-gray-500">
              {servicesQ.data?.services?.openlitespeed?.installed ? 'Kurulu' : 'Kurulu değil'} /{' '}
              {servicesQ.data?.services?.openlitespeed?.active ? 'Aktif' : 'Pasif'}
            </div>
            {!servicesQ.data?.services?.openlitespeed?.installed && (
              <p className="mt-2 text-xs text-amber-600">{t('webserver.ols_not_installed_hint')}</p>
            )}
          </div>
        </div>

        <div className="flex flex-wrap gap-2">
          <button type="button" className={`btn ${activeTab === 'general' ? 'btn-primary' : 'btn-secondary'}`} onClick={() => setActiveTab('general')}>
            {t('webserver.title')}
          </button>
          <button type="button" className={`btn ${activeTab === 'apache' ? 'btn-primary' : 'btn-secondary'}`} onClick={() => setActiveTab('apache')}>
            {t('webserver.apache')}
          </button>
          <button type="button" className={`btn ${activeTab === 'nginx' ? 'btn-primary' : 'btn-secondary'}`} onClick={() => setActiveTab('nginx')}>
            {t('webserver.nginx')}
          </button>
        </div>

        {activeTab === 'general' && (
          <>
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
          <h2 className="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            {t('webserver.openlitespeed')}
          </h2>
          <p className="text-xs text-gray-500 dark:text-gray-400">{t('webserver.ols_include_hint')}</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label className="flex items-center gap-3 text-sm">
              <input
                type="checkbox"
                className="rounded border-gray-300"
                checked={form?.openlitespeed_manage_vhosts ?? false}
                onChange={(e) =>
                  setForm((s) => (s ? { ...s, openlitespeed_manage_vhosts: e.target.checked } : s))
                }
              />
              {t('webserver.manage_vhosts')}
            </label>
            <label className="flex items-center gap-3 text-sm">
              <input
                type="checkbox"
                className="rounded border-gray-300"
                checked={form?.openlitespeed_reload_after_vhost ?? false}
                onChange={(e) =>
                  setForm((s) => (s ? { ...s, openlitespeed_reload_after_vhost: e.target.checked } : s))
                }
              />
              {t('webserver.reload_after_vhost')}
            </label>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
            <div>
              <label className="label">{t('webserver.ols_conf_root')}</label>
              <input
                className="input w-full"
                value={form?.openlitespeed_conf_root ?? ''}
                onChange={(e) =>
                  setForm((s) => (s ? { ...s, openlitespeed_conf_root: e.target.value } : s))
                }
              />
            </div>
            <div>
              <label className="label">{t('webserver.ols_ctrl_path')}</label>
              <input
                className="input w-full"
                placeholder="/usr/local/lsws/bin/lswsctrl"
                value={form?.openlitespeed_ctrl_path ?? ''}
                onChange={(e) =>
                  setForm((s) => (s ? { ...s, openlitespeed_ctrl_path: e.target.value } : s))
                }
              />
            </div>
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
          </>
        )}

        {activeTab === 'apache' && (
          <section className="space-y-3">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{t('webserver.apache')}</h2>
              <button
                type="button"
                className="btn-secondary inline-flex items-center gap-2"
                onClick={() => void apacheModulesQ.refetch()}
                disabled={apacheModulesQ.isFetching}
              >
                <RefreshCw className="h-4 w-4" />
                {t('common.refresh')}
              </button>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              {(apacheModulesQ.data?.modules ?? []).map((m) => (
                <label key={m.name} className="border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 flex items-center justify-between text-sm">
                  <span className="font-medium">{m.name}</span>
                  <input
                    type="checkbox"
                    checked={m.enabled}
                    disabled={!canWrite || toggleApacheModuleM.isPending}
                    onChange={(e) => toggleApacheModuleM.mutate({ name: m.name, enabled: e.target.checked })}
                  />
                </label>
              ))}
            </div>
          </section>
        )}

        {activeTab === 'nginx' && (
          <section className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h2 className="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{t('webserver.nginx')}</h2>
              <div className="flex items-center gap-2">
                <select className="input h-9" value={nginxScope} onChange={(e) => setNginxScope(e.target.value as 'main' | 'panel')}>
                  <option value="main">nginx.conf</option>
                  <option value="panel">hostvim.conf</option>
                </select>
                <button type="button" className="btn-secondary" onClick={() => void nginxConfigQ.refetch()}>{t('common.refresh')}</button>
              </div>
            </div>
            <textarea
              className="input w-full min-h-[360px] font-mono text-xs"
              value={nginxContent}
              onChange={(e) => setNginxContent(e.target.value)}
              disabled={!canWrite || nginxConfigQ.isLoading}
            />
            <div className="flex justify-end">
              <button
                type="button"
                className="btn-primary"
                onClick={() => saveNginxM.mutate()}
                disabled={!canWrite || saveNginxM.isPending || nginxConfigQ.isLoading}
              >
                {t('common.save')}
              </button>
            </div>
          </section>
        )}
      </div>
    </div>
  )
}

