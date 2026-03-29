import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { KeyRound } from 'lucide-react'
import toast from 'react-hot-toast'

export default function AdminLicensePage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')
  const [keyInput, setKeyInput] = useState('')

  const statusQ = useQuery({
    queryKey: ['license-status'],
    queryFn: async () => (await api.get('/license')).data as {
      local_key_set?: boolean
      engine?: Record<string, unknown> | null
    },
    enabled: !!isAdmin,
  })

  const validateM = useMutation({
    mutationFn: async (key: string) => api.post('/license/validate', { key }),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ['license-status'] })
      setKeyInput('')
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
              <strong className="text-gray-900 dark:text-white">.env LICENSE_KEY:</strong>{' '}
              {statusQ.data?.local_key_set ? t('license.key_set') : t('license.key_unset')}
            </li>
            <li>
              <strong className="text-gray-900 dark:text-white">Engine doğrulama:</strong>{' '}
              {statusQ.data?.engine != null
                ? JSON.stringify(statusQ.data.engine)
                : t('license.engine_skip')}
            </li>
          </ul>
        )}
        <p className="text-xs text-gray-500">{t('license.env_hint')}</p>
      </div>

      <div className="card p-6 space-y-4">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('license.try_key')}</h2>
        <p className="text-sm text-gray-500">{t('license.try_key_hint')}</p>
        <input
          type="password"
          autoComplete="off"
          className="input w-full font-mono text-sm"
          placeholder="license key"
          value={keyInput}
          onChange={(e) => setKeyInput(e.target.value)}
        />
        <button
          type="button"
          className="btn-primary"
          disabled={validateM.isPending || !keyInput.trim()}
          onClick={() => validateM.mutate(keyInput.trim())}
        >
          {t('license.validate')}
        </button>
      </div>
    </div>
  )

}
