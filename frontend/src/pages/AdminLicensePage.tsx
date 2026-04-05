import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { KeyRound } from 'lucide-react'
import toast from 'react-hot-toast'

type LicenseStatus = {
  local_key_set?: boolean
  key_source?: 'env' | 'database' | 'none'
  key_preview?: string | null
  hub_configured?: boolean
  source?: string
  hub?: Record<string, unknown> | null
  engine?: Record<string, unknown> | null
}

export default function AdminLicensePage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')
  const [keyInput, setKeyInput] = useState('')

  const statusQ = useQuery({
    queryKey: ['license-status'],
    queryFn: async () => (await api.get('/license')).data as LicenseStatus,
    enabled: !!isAdmin,
  })

  const activateM = useMutation({
    mutationFn: async (key: string) => api.post('/license/activate', { key }),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ['license-status'] })
      setKeyInput('')
      const d = res.data as { hub?: { plan_name?: string } }
      const plan = d?.hub && typeof d.hub.plan_name === 'string' ? d.hub.plan_name : ''
      toast.success(plan ? t('license.activate_ok_plan', { plan }) : t('license.activate_ok'))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const clearM = useMutation({
    mutationFn: async () => api.post('/license/clear'),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['license-status'] })
      toast.success(t('license.cleared_ok'))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const testM = useMutation({
    mutationFn: async (key: string) => api.post('/license/validate', { key }),
    onSuccess: (res) => {
      const d = res.data as Record<string, unknown>
      const msg =
        typeof d?.message === 'string'
          ? d.message
          : `${t('license.validate_ok')}: ${JSON.stringify(d)}`
      toast.success(msg, { duration: 6000 })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  if (!isAdmin) {
    return <Navigate to="/dashboard" replace />
  }

  const source = statusQ.data?.key_source ?? 'none'
  const hubOk = statusQ.data?.hub && (statusQ.data.hub as { valid?: boolean }).valid === true

  return (
    <div className="space-y-6 max-w-3xl">
      <div className="flex items-center gap-3">
        <KeyRound className="h-8 w-8 text-amber-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.license')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('license.subtitle')}</p>
        </div>
      </div>

      <div className="card p-6 space-y-4">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('license.status')}</h2>
        {statusQ.isLoading ? (
          <p className="text-gray-500">{t('common.loading')}</p>
        ) : (
          <ul className="text-sm space-y-2 text-gray-600 dark:text-gray-400">
            <li>
              <strong className="text-gray-900 dark:text-white">{t('license.key_storage')}:</strong>{' '}
              {source === 'env' && t('license.source_env')}
              {source === 'database' && t('license.source_database')}
              {source === 'none' && t('license.source_none')}
            </li>
            {statusQ.data?.key_preview ? (
              <li>
                <strong className="text-gray-900 dark:text-white">{t('license.key_preview')}:</strong>{' '}
                <span className="font-mono">{statusQ.data.key_preview}</span>
              </li>
            ) : null}
            <li>
              <strong className="text-gray-900 dark:text-white">{t('license.hub_label')}:</strong>{' '}
              {statusQ.data?.hub_configured ? t('license.hub_yes') : t('license.hub_no')}
            </li>
            <li>
              <strong className="text-gray-900 dark:text-white">{t('license.validation_label')}:</strong>{' '}
              {statusQ.data?.source === 'license_server' &&
                (hubOk ? t('license.valid_yes') : JSON.stringify(statusQ.data.hub))}
              {statusQ.data?.source === 'engine' &&
                (statusQ.data.engine != null
                  ? JSON.stringify(statusQ.data.engine)
                  : t('license.engine_skip'))}
            </li>
          </ul>
        )}
        <p className="text-xs text-gray-500">{t('license.ui_hint')}</p>
      </div>

      <div className="card p-6 space-y-4">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('license.activate_title')}</h2>
        <p className="text-sm text-gray-500 dark:text-gray-400">{t('license.activate_hint')}</p>
        <input
          type="password"
          autoComplete="off"
          className="input w-full font-mono text-sm"
          placeholder={t('license.key_placeholder')}
          value={keyInput}
          onChange={(e) => setKeyInput(e.target.value)}
        />
        <div className="flex flex-wrap gap-3">
          <button
            type="button"
            className="btn-primary"
            disabled={activateM.isPending || !keyInput.trim()}
            onClick={() => activateM.mutate(keyInput.trim())}
          >
            {t('license.activate')}
          </button>
          <button
            type="button"
            className="btn-secondary"
            disabled={testM.isPending || !keyInput.trim()}
            onClick={() => testM.mutate(keyInput.trim())}
          >
            {t('license.test_only')}
          </button>
          {source === 'database' ? (
            <button
              type="button"
              className="text-sm text-red-600 hover:text-red-700 dark:text-red-400"
              disabled={clearM.isPending}
              onClick={() => {
                if (window.confirm(t('license.clear_confirm'))) {
                  clearM.mutate()
                }
              }}
            >
              {t('license.clear_stored')}
            </button>
          ) : null}
        </div>
      </div>
    </div>
  )
}
