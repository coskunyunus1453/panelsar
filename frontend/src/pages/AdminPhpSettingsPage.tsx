import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import toast from 'react-hot-toast'
import clsx from 'clsx'
import { FileCode, Puzzle, Save, AlertTriangle, SlidersHorizontal } from 'lucide-react'
import { tokenHasAbility } from '../lib/abilities'
import PhpQuickSettingsPanel from '../components/admin/PhpQuickSettingsPanel'

type PhpModule = {
  directive: 'extension' | 'zend_extension'
  name: string
  raw_value: string
  enabled: boolean
}

type LimitsSync = {
  php_upload_max_filesize_mb?: number
  php_post_max_size_mb?: number
  effective_php_limit_mb?: number
  file_manager_limit_mb?: number
  nginx_hint_client_max_body_size_mb?: number
  message?: string
}
type IniResponse = { path?: string; ini: string; file_manager_limit_mb?: number; limits_sync?: LimitsSync }
type ModulesResponse = { path?: string; modules: PhpModule[] }
type VersionsResponse = { versions: string[] }
type NginxSyncStep = { key: 'read_config' | 'patch_config' | 'test_reload'; ok: boolean; message: string }
type NginxSyncStartResponse = { run_id: string; status: 'queued' }
type NginxSyncStatusResponse = {
  run_id: string
  status: 'queued' | 'running' | 'success' | 'failed'
  progress: number
  steps: NginxSyncStep[]
  message?: string
  failed_step?: NginxSyncStep['key']
}

function extractIniSizeMb(ini: string, key: string): number | null {
  const re = new RegExp(`^\\s*${key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*=\\s*([0-9.]+\\s*[KMG]?)\\s*$`, 'im')
  const m = ini.match(re)
  if (!m?.[1]) return null
  const raw = m[1].replace(/\s+/g, '').toUpperCase()
  if (!raw) return null
  const unit = raw.slice(-1)
  const n = Number(/[0-9]/.test(unit) ? raw : raw.slice(0, -1))
  if (!Number.isFinite(n) || n <= 0) return null
  if (unit === 'G') return Math.ceil(n * 1024)
  if (unit === 'K') return Math.ceil(n / 1024)
  if (unit === 'M') return Math.ceil(n)
  return Math.ceil(n / (1024 * 1024))
}

const TAB_IDS = ['quick', 'modules', 'ini'] as const
type TabId = (typeof TAB_IDS)[number]

export default function AdminPhpSettingsPage() {
  const { t } = useTranslation()
  const user = useAuthStore((s) => s.user)
  const abilities = user?.abilities

  const canRead = tokenHasAbility(abilities, 'php:read')
  const canWrite = tokenHasAbility(abilities, 'php:write')
  const canView = canRead || canWrite

  const [searchParams, setSearchParams] = useSearchParams()
  const versionFromUrl = (searchParams.get('v') ?? '').trim()
  const tabFromUrl = (searchParams.get('tab') ?? '').trim().toLowerCase()
  const activeTab: TabId = TAB_IDS.includes(tabFromUrl as TabId) ? (tabFromUrl as TabId) : 'quick'

  const versionsQ = useQuery({
    queryKey: ['admin-php-versions'],
    queryFn: async () => (await api.get('/admin/settings/php/versions')).data as VersionsResponse,
    enabled: canView,
  })

  const [version, setVersion] = useState<string>('')
  useEffect(() => {
    const list = versionsQ.data?.versions ?? []
    if (!list.length) return
    const preferred =
      versionFromUrl && list.includes(versionFromUrl)
        ? versionFromUrl
        : version && list.includes(version)
          ? version
          : list[0]
    if (preferred !== version) {
      setVersion(preferred)
    }
    if (versionFromUrl && !list.includes(versionFromUrl) && preferred) {
      setSearchParams(
        (prev) => {
          const sp = new URLSearchParams(prev)
          sp.set('v', preferred)
          return sp
        },
        { replace: true },
      )
    }
  }, [versionsQ.data, versionFromUrl, version, setSearchParams])

  const setVersionInUiAndUrl = (next: string) => {
    setVersion(next)
    setSearchParams(
      (prev) => {
        const sp = new URLSearchParams(prev)
        if (next) sp.set('v', next)
        else sp.delete('v')
        return sp
      },
      { replace: true },
    )
  }

  const setTab = (id: TabId) => {
    setSearchParams(
      (prev) => {
        const sp = new URLSearchParams(prev)
        sp.set('tab', id)
        return sp
      },
      { replace: true },
    )
  }

  const iniQ = useQuery({
    queryKey: ['admin-php-ini', version],
    enabled: canView && !!version,
    queryFn: async () => {
      const { data } = await api.get(`/admin/settings/php/${encodeURIComponent(version)}/ini`)
      return data as IniResponse
    },
  })

  const modulesQ = useQuery({
    queryKey: ['admin-php-modules', version],
    enabled: canView && !!version,
    queryFn: async () => {
      const { data } = await api.get(`/admin/settings/php/${encodeURIComponent(version)}/modules`)
      return data as ModulesResponse
    },
  })

  const [iniText, setIniText] = useState('')
  const [moduleState, setModuleState] = useState<PhpModule[]>([])
  const [limitsSync, setLimitsSync] = useState<LimitsSync | null>(null)
  const [nginxSyncProgress, setNginxSyncProgress] = useState(0)
  const [nginxSyncSteps, setNginxSyncSteps] = useState<NginxSyncStep[]>([])
  const [nginxSyncRunId, setNginxSyncRunId] = useState<string | null>(null)

  useEffect(() => {
    if (iniQ.data?.ini != null) setIniText(iniQ.data.ini)
    if (iniQ.data?.file_manager_limit_mb != null) {
      setLimitsSync((prev) => ({
        ...prev,
        file_manager_limit_mb: iniQ.data?.file_manager_limit_mb,
      }))
    }
  }, [iniQ.data])

  useEffect(() => {
    if (modulesQ.data?.modules) setModuleState(modulesQ.data.modules)
  }, [modulesQ.data])

  const toggleModule = (idx: number) => {
    setModuleState((prev) => {
      const next = [...prev]
      next[idx] = { ...next[idx], enabled: !next[idx].enabled }
      return next
    })
  }

  const saveModulesM = useMutation({
    mutationFn: async () => {
      if (!version) return
      return api.patch(`/admin/settings/php/${encodeURIComponent(version)}/modules`, {
        reload: true,
        modules: moduleState.map((m) => ({
          directive: m.directive,
          name: m.name,
          enabled: m.enabled,
        })),
      })
    },
    onSuccess: () => {
      toast.success(t('php_settings.modules_saved'))
      void modulesQ.refetch()
      void iniQ.refetch()
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const saveIniM = useMutation({
    mutationFn: async () => {
      if (!version) return
      const { data } = await api.put(`/admin/settings/php/${encodeURIComponent(version)}/ini`, {
        reload: true,
        ini: iniText,
      })
      return data as IniResponse
    },
    onSuccess: (data) => {
      toast.success(t('php_settings.ini_saved'))
      if (data?.limits_sync) {
        setLimitsSync(data.limits_sync)
      }
      void modulesQ.refetch()
      void iniQ.refetch()
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const syncNginxLimitM = useMutation({
    mutationFn: async () => {
      const targetMb = limitsSync?.nginx_hint_client_max_body_size_mb
      if (!targetMb || targetMb < 1) {
        throw new Error(t('php_settings.upload_sync.no_target'))
      }
      const { data } = await api.post('/admin/settings/php/sync-nginx-upload-limit', {
        limit_mb: targetMb,
        scope: 'panel',
      })
      return data as NginxSyncStartResponse
    },
    onSuccess: (data) => {
      setNginxSyncProgress(5)
      setNginxSyncSteps([
        { key: 'read_config', ok: false, message: t('php_settings.upload_sync.step_read') },
        { key: 'patch_config', ok: false, message: t('php_settings.upload_sync.step_patch') },
        { key: 'test_reload', ok: false, message: t('php_settings.upload_sync.step_reload') },
      ])
      setNginxSyncRunId(data.run_id)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      setNginxSyncRunId(null)
      setNginxSyncProgress(0)
      setNginxSyncSteps([])
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const nginxSyncStatusQ = useQuery({
    queryKey: ['admin-php-nginx-upload-sync-status', nginxSyncRunId],
    enabled: !!nginxSyncRunId,
    queryFn: async () =>
      (
        await api.get(`/admin/settings/php/sync-nginx-upload-limit/${encodeURIComponent(String(nginxSyncRunId))}`)
      ).data as NginxSyncStatusResponse,
    refetchInterval: 1000,
  })

  useEffect(() => {
    const data = nginxSyncStatusQ.data
    if (!data || !nginxSyncRunId) return
    if (data.run_id !== nginxSyncRunId) return

    setNginxSyncProgress(data.progress ?? 0)
    setNginxSyncSteps(data.steps ?? [])

    if (data.status === 'success') {
      toast.success(t('php_settings.upload_sync.nginx_saved'))
      setNginxSyncRunId(null)
    } else if (data.status === 'failed') {
      toast.error(data.message ?? String('Nginx sync failed'))
      setNginxSyncRunId(null)
    }
  }, [nginxSyncStatusQ.data, nginxSyncRunId, t])

  const hasAnyLoaded = versionsQ.isFetched && !!version

  const renderError = (msg: string | undefined) =>
    msg ? (
      <div className="flex items-start gap-3 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-950/30 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
        <AlertTriangle className="h-5 w-5 flex-shrink-0 mt-0.5" />
        <div>
          <p className="font-medium">{t('php_settings.load_error')}</p>
          <p className="text-amber-800/80 dark:text-amber-300/80 mt-1">{msg}</p>
        </div>
      </div>
    ) : null

  const errMsg =
    (versionsQ.isError ? 'versions' : '') ||
    (iniQ.isError ? 'ini' : '') ||
    (modulesQ.isError ? 'modules' : '')

  const disabled = !canWrite

  const saveModulesDisabled = disabled || saveModulesM.isPending || modulesQ.isLoading || !moduleState.length
  const saveIniDisabled = disabled || saveIniM.isPending || iniQ.isLoading || !hasAnyLoaded

  const canEditIni = useMemo(() => canWrite, [canWrite])
  const effectiveLimits = useMemo(() => {
    const parsedUpload = extractIniSizeMb(iniText, 'upload_max_filesize')
    const parsedPost = extractIniSizeMb(iniText, 'post_max_size')
    const upload = limitsSync?.php_upload_max_filesize_mb ?? parsedUpload
    const post = limitsSync?.php_post_max_size_mb ?? parsedPost
    const parsedEffective =
      upload && post
        ? Math.max(1, Math.min(upload, post))
        : upload ?? post ?? undefined
    const phpEffective = limitsSync?.effective_php_limit_mb ?? parsedEffective
    const appLimit = limitsSync?.file_manager_limit_mb
    const nginxHint =
      limitsSync?.nginx_hint_client_max_body_size_mb ??
      (typeof phpEffective === 'number' && phpEffective > 0 ? phpEffective : undefined)
    const hasData = [upload, post, phpEffective, appLimit, nginxHint].some((v) => typeof v === 'number' && v > 0)
    return { upload, post, phpEffective, appLimit, nginxHint, hasData }
  }, [limitsSync, iniText])

  const tabs: { id: TabId; icon: typeof SlidersHorizontal; label: string }[] = [
    { id: 'quick', icon: SlidersHorizontal, label: t('php_settings.tabs.quick') },
    { id: 'modules', icon: Puzzle, label: t('php_settings.tabs.modules') },
    { id: 'ini', icon: FileCode, label: t('php_settings.tabs.ini') },
  ]

  if (!canView) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="space-y-5 sm:space-y-6 max-w-6xl mx-auto">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div className="flex items-start gap-3 sm:gap-4 min-w-0">
          <div className="p-2.5 sm:p-3 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 text-white shadow-lg shadow-primary-500/25 flex-shrink-0">
            <Puzzle className="h-7 w-7 sm:h-8 sm:w-8" />
          </div>
          <div className="min-w-0">
            <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-300">
              {t('php_settings.title')}
            </h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm mt-0.5">{t('php_settings.subtitle')}</p>
          </div>
        </div>

        <div className="flex flex-col xs:flex-row flex-wrap items-stretch sm:items-center gap-2 w-full lg:w-auto">
          <label className="sr-only" htmlFor="php-version-select">
            {t('php_settings.php_version')}
          </label>
          <select
            id="php-version-select"
            className="input w-full sm:w-auto min-w-[10rem]"
            value={version}
            disabled={!canView || versionsQ.isLoading || disabled}
            onChange={(e) => setVersionInUiAndUrl(e.target.value)}
          >
            {(versionsQ.data?.versions ?? []).map((v) => (
              <option key={v} value={v}>
                PHP {v}
              </option>
            ))}
          </select>
        </div>
      </div>

      {(errMsg || (versionsQ.isError && errMsg)) && renderError(String(errMsg || ''))}

      <div className="border-b border-gray-200 dark:border-gray-800 -mx-1 px-1">
        <nav
          className="flex gap-1 sm:gap-2 overflow-x-auto pb-px scrollbar-thin touch-pan-x"
          role="tablist"
          aria-label={t('php_settings.tabs.aria')}
        >
          {tabs.map(({ id, icon: Icon, label }) => (
            <button
              key={id}
              type="button"
              role="tab"
              aria-selected={activeTab === id}
              onClick={() => setTab(id)}
              className={clsx(
                'flex items-center gap-2 flex-shrink-0 px-3 sm:px-4 py-2.5 rounded-t-lg text-sm font-medium transition-colors border-b-2 -mb-px',
                activeTab === id
                  ? 'border-primary-600 text-primary-700 dark:text-primary-400 bg-white/60 dark:bg-gray-900/40'
                  : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50/80 dark:hover:bg-gray-800/40',
              )}
            >
              <Icon className="h-4 w-4 flex-shrink-0 opacity-80" aria-hidden />
              {label}
            </button>
          ))}
        </nav>
      </div>

      <div className="min-h-[12rem]" role="tabpanel">
        {activeTab === 'quick' && (
          <div className="card p-4 sm:p-6 space-y-6">
            {effectiveLimits.hasData && (
              <div className="rounded-xl border border-primary-200/70 dark:border-primary-900/50 bg-primary-50/60 dark:bg-primary-950/20 p-4">
                <p className="text-sm font-semibold text-primary-800 dark:text-primary-300">
                  {t('php_settings.upload_sync.title')}
                </p>
                <p className="mt-1 text-xs text-primary-700/80 dark:text-primary-300/80">
                  {limitsSync?.message || t('php_settings.upload_sync.hint')}
                </p>
                <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs">
                  <div className="rounded-lg bg-white/70 dark:bg-gray-900/40 px-3 py-2">
                    <span className="text-gray-500 dark:text-gray-400">{t('php_settings.upload_sync.upload')}</span>
                    <div className="font-semibold text-gray-900 dark:text-gray-200">{effectiveLimits.upload ?? '—'} MB</div>
                  </div>
                  <div className="rounded-lg bg-white/70 dark:bg-gray-900/40 px-3 py-2">
                    <span className="text-gray-500 dark:text-gray-400">{t('php_settings.upload_sync.post')}</span>
                    <div className="font-semibold text-gray-900 dark:text-gray-200">{effectiveLimits.post ?? '—'} MB</div>
                  </div>
                  <div className="rounded-lg bg-white/70 dark:bg-gray-900/40 px-3 py-2">
                    <span className="text-gray-500 dark:text-gray-400">{t('php_settings.upload_sync.effective')}</span>
                    <div className="font-semibold text-gray-900 dark:text-gray-200">{effectiveLimits.phpEffective ?? '—'} MB</div>
                  </div>
                  <div className="rounded-lg bg-white/70 dark:bg-gray-900/40 px-3 py-2">
                    <span className="text-gray-500 dark:text-gray-400">{t('php_settings.upload_sync.app')}</span>
                    <div className="font-semibold text-gray-900 dark:text-gray-200">{effectiveLimits.appLimit ?? '—'} MB</div>
                  </div>
                  <div className="rounded-lg bg-white/70 dark:bg-gray-900/40 px-3 py-2">
                    <span className="text-gray-500 dark:text-gray-400">{t('php_settings.upload_sync.nginx')}</span>
                    <div className="font-semibold text-gray-900 dark:text-gray-200">{effectiveLimits.nginxHint ?? '—'} MB</div>
                  </div>
                </div>
                <div className="mt-3">
                  <button
                    type="button"
                    className="btn-secondary min-h-[40px]"
                    disabled={!canWrite || syncNginxLimitM.isPending || !!nginxSyncRunId || !effectiveLimits.nginxHint}
                    onClick={() => {
                      setNginxSyncRunId(null)
                      setNginxSyncSteps([])
                      setNginxSyncProgress(0)
                      syncNginxLimitM.mutate()
                    }}
                  >
                    {t('php_settings.upload_sync.sync_nginx')}
                  </button>
                </div>
              </div>
            )}
            <PhpQuickSettingsPanel
              iniText={iniText}
              setIniText={setIniText}
              disabled={disabled}
              loading={iniQ.isLoading}
            />
            <button
              type="button"
              className="btn-primary w-full sm:w-auto min-h-[44px]"
              disabled={saveIniDisabled}
              onClick={() => saveIniM.mutate()}
            >
              <Save className="h-4 w-4 inline mr-2" />
              {t('php_settings.save_ini')}
            </button>
          </div>
        )}

        {activeTab === 'modules' && (
          <div className="card p-4 sm:p-6 space-y-4">
            <div className="flex items-center gap-2">
              <Puzzle className="h-5 w-5 text-primary-600 flex-shrink-0" />
              <h2 className="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {t('php_settings.modules')}
              </h2>
            </div>

            <div className="space-y-3 max-h-[min(60vh,32rem)] overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-lg p-3">
              {modulesQ.isLoading && <p className="text-sm text-gray-500">{t('common.loading')}</p>}
              {!modulesQ.isLoading && !moduleState.length && (
                <p className="text-sm text-gray-500">{t('common.no_data')}</p>
              )}
              {moduleState.map((m, idx) => (
                <label
                  key={`${m.directive}:${m.name}:${idx}`}
                  className="flex items-center justify-between gap-3 text-sm py-1"
                >
                  <span className="font-mono text-xs text-gray-700 dark:text-gray-200 break-all">
                    {m.directive}:{m.name}
                  </span>
                  <input type="checkbox" className="flex-shrink-0" checked={m.enabled} disabled={disabled} onChange={() => toggleModule(idx)} />
                </label>
              ))}
            </div>

            <button
              type="button"
              className="btn-primary w-full sm:w-auto min-h-[44px]"
              disabled={saveModulesDisabled}
              onClick={() => saveModulesM.mutate()}
            >
              {t('php_settings.save_modules')}
            </button>
          </div>
        )}

        {activeTab === 'ini' && (
          <div className="card p-4 sm:p-6 space-y-4">
            <div className="flex items-center gap-2">
              <FileCode className="h-5 w-5 text-primary-600 flex-shrink-0" />
              <h2 className="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {t('php_settings.ini')}
              </h2>
            </div>

            <p className="text-xs text-gray-500 dark:text-gray-400">{t('php_settings.ini_hint')}</p>

            <textarea
              className="w-full font-mono text-xs bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-lg p-3 min-h-[min(50vh,28rem)] h-[50vh] max-h-[70vh] resize-y"
              value={iniText}
              disabled={!canEditIni || iniQ.isLoading}
              onChange={(e) => setIniText(e.target.value)}
            />

            <button
              type="button"
              className="btn-primary w-full sm:w-auto min-h-[44px]"
              disabled={saveIniDisabled}
              onClick={() => saveIniM.mutate()}
            >
              <Save className="h-4 w-4 inline mr-2" />
              {t('php_settings.save_ini')}
            </button>
          </div>
        )}
      </div>
      {nginxSyncRunId !== null && nginxSyncStatusQ.data?.status !== 'success' && nginxSyncStatusQ.data?.status !== 'failed' && (
        <div className="fixed inset-0 z-[140] flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-900">
            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
              {t('php_settings.upload_sync.modal_title')}
            </p>
            <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">
              {t('php_settings.upload_sync.modal_hint')}
            </p>
            <div className="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
              {t('php_settings.upload_sync.do_not_close')}
            </div>
            <div className="mt-4 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
              <div
                className="h-full rounded-full bg-primary-600 transition-all duration-300"
                style={{ width: `${nginxSyncProgress}%` }}
              />
            </div>
            <div className="mt-2 text-right text-xs text-gray-500 dark:text-gray-400">
              {Math.max(5, Math.min(99, nginxSyncProgress))}%
            </div>
            {!!nginxSyncSteps.length && (
              <div className="mt-3 space-y-1.5">
                {nginxSyncSteps.map((s, idx) => {
                  const threshold = (idx + 1) * 33
                  const done = s.ok || nginxSyncProgress >= threshold
                  const label =
                    s.key === 'read_config'
                      ? t('php_settings.upload_sync.step_read')
                      : s.key === 'patch_config'
                        ? t('php_settings.upload_sync.step_patch')
                        : t('php_settings.upload_sync.step_reload')
                  return (
                    <div key={s.key} className={clsx('text-xs', done ? 'text-emerald-600 dark:text-emerald-300' : 'text-gray-500 dark:text-gray-400')}>
                      {done ? 'OK' : '…'} {label}
                    </div>
                  )
                })}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
