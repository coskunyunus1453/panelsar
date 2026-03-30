import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import toast from 'react-hot-toast'
import { FileCode, Puzzle, Save, AlertTriangle } from 'lucide-react'
import { tokenHasAbility } from '../lib/abilities'

type PhpModule = {
  directive: 'extension' | 'zend_extension'
  name: string
  raw_value: string
  enabled: boolean
}

type IniResponse = { path?: string; ini: string }
type ModulesResponse = { path?: string; modules: PhpModule[] }
type VersionsResponse = { versions: string[] }

export default function AdminPhpSettingsPage() {
  const { t } = useTranslation()
  const user = useAuthStore((s) => s.user)
  const abilities = user?.abilities

  const canRead = tokenHasAbility(abilities, 'php:read')
  const canWrite = tokenHasAbility(abilities, 'php:write')
  const canView = canRead || canWrite

  const [searchParams, setSearchParams] = useSearchParams()
  const versionFromUrl = (searchParams.get('v') ?? '').trim()

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
          : list[list.length - 1]
    if (preferred !== version) {
      setVersion(preferred)
    }
  }, [versionsQ.data, versionFromUrl, version])

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

  useEffect(() => {
    if (iniQ.data?.ini != null) setIniText(iniQ.data.ini)
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
      return api.put(`/admin/settings/php/${encodeURIComponent(version)}/ini`, {
        reload: true,
        ini: iniText,
      })
    },
    onSuccess: () => {
      toast.success(t('php_settings.ini_saved'))
      void modulesQ.refetch()
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

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

  if (!canView) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div className="flex items-start gap-4">
          <div className="p-3 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 text-white shadow-lg shadow-primary-500/25">
            <Puzzle className="h-8 w-8" />
          </div>
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-300">{t('php_settings.title')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm mt-0.5">{t('php_settings.subtitle')}</p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <select
            className="input"
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

      <div className="grid lg:grid-cols-2 gap-6">
        <div className="card p-5 space-y-4">
          <div className="flex items-center gap-2">
            <Puzzle className="h-5 w-5 text-primary-600" />
            <h2 className="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
              {t('php_settings.modules')}
            </h2>
          </div>

          <div className="space-y-3 max-h-[55vh] overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-lg p-3">
            {modulesQ.isLoading && <p className="text-sm text-gray-500">{t('common.loading')}</p>}
            {!modulesQ.isLoading && !moduleState.length && <p className="text-sm text-gray-500">{t('common.no_data')}</p>}
            {moduleState.map((m, idx) => (
              <label key={`${m.directive}:${m.name}:${idx}`} className="flex items-center justify-between gap-3 text-sm">
                <span className="font-mono text-xs text-gray-700 dark:text-gray-200">
                  {m.directive}:{m.name}
                </span>
                <input type="checkbox" checked={m.enabled} disabled={disabled} onChange={() => toggleModule(idx)} />
              </label>
            ))}
          </div>

          <button
            type="button"
            className="btn-primary w-full"
            disabled={saveModulesDisabled}
            onClick={() => saveModulesM.mutate()}
          >
            {t('php_settings.save_modules')}
          </button>
        </div>

        <div className="card p-5 space-y-4">
          <div className="flex items-center gap-2">
            <FileCode className="h-5 w-5 text-primary-600" />
            <h2 className="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
              {t('php_settings.ini')}
            </h2>
          </div>

          <p className="text-xs text-gray-500 dark:text-gray-400">
            {t('php_settings.ini_hint')}
          </p>

          <textarea
            className="w-full font-mono text-xs bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-lg p-3 h-[40vh] resize-y"
            value={iniText}
            disabled={!canEditIni || iniQ.isLoading}
            onChange={(e) => setIniText(e.target.value)}
          />

          <button
            type="button"
            className="btn-primary w-full"
            disabled={saveIniDisabled}
            onClick={() => saveIniM.mutate()}
          >
            <Save className="h-4 w-4 inline mr-2" />
            {t('php_settings.save_ini')}
          </button>
        </div>
      </div>
    </div>
  )
}

