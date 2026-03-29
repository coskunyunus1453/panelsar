import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { Mail, Send, Save } from 'lucide-react'
import toast from 'react-hot-toast'
import clsx from 'clsx'

type MailSettings = {
  driver: string
  smtp_host: string
  smtp_port: number
  smtp_username: string
  smtp_password_set: boolean
  smtp_encryption: string
  from_address: string
  from_name: string
}

export default function AdminMailSettingsPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')

  const q = useQuery({
    queryKey: ['admin-mail-settings'],
    queryFn: async () => {
      const { data } = await api.get<MailSettings>('/admin/settings/mail')
      return data
    },
    enabled: !!isAdmin,
  })

  const [driver, setDriver] = useState('log')
  const [smtpHost, setSmtpHost] = useState('')
  const [smtpPort, setSmtpPort] = useState(587)
  const [smtpUser, setSmtpUser] = useState('')
  const [smtpPass, setSmtpPass] = useState('')
  const [smtpEnc, setSmtpEnc] = useState('')
  const [clearPass, setClearPass] = useState(false)
  const [fromAddress, setFromAddress] = useState('')
  const [fromName, setFromName] = useState('')

  useEffect(() => {
    if (!q.data) return
    setDriver(q.data.driver || 'log')
    setSmtpHost(q.data.smtp_host || '')
    setSmtpPort(q.data.smtp_port || 587)
    setSmtpUser(q.data.smtp_username || '')
    setSmtpEnc(q.data.smtp_encryption || '')
    setFromAddress(q.data.from_address || '')
    setFromName(q.data.from_name || '')
    setSmtpPass('')
    setClearPass(false)
  }, [q.data])

  const saveM = useMutation({
    mutationFn: async () => {
      const { data } = await api.put<MailSettings>('/admin/settings/mail', {
        driver,
        smtp_host: smtpHost,
        smtp_port: smtpPort,
        smtp_username: smtpUser,
        smtp_password: smtpPass || undefined,
        smtp_encryption: smtpEnc || '',
        clear_smtp_password: clearPass,
        from_address: fromAddress,
        from_name: fromName,
      })
      return data
    },
    onSuccess: () => {
      toast.success(t('mail_settings.saved'))
      qc.invalidateQueries({ queryKey: ['admin-mail-settings'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const d = ax.response?.data
      const msg =
        d?.message ??
        (d?.errors ? Object.values(d.errors).flat().join(', ') : String(err))
      toast.error(msg)
    },
  })

  const testM = useMutation({
    mutationFn: async () => {
      const { data } = await api.post<{ message: string }>('/admin/settings/mail/test', {})
      return data
    },
    onSuccess: (d) => {
      toast.success(d.message)
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
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Mail className="h-8 w-8 text-primary-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            {t('mail_settings.title')}
          </h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('mail_settings.subtitle')}</p>
        </div>
      </div>

      <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300">
        {t('mail_settings.hint')}
      </div>

      {q.isLoading && <p className="text-gray-500">{t('common.loading')}</p>}
      {q.isError && (
        <p className="text-red-600 dark:text-red-400">{t('mail_settings.load_error')}</p>
      )}

      {q.isSuccess && (
        <form
          className="card space-y-6 p-6"
          onSubmit={(e) => {
            e.preventDefault()
            saveM.mutate()
          }}
        >
          <div>
            <label className="label">{t('mail_settings.driver')}</label>
            <select
              className="input max-w-md"
              value={driver}
              onChange={(e) => setDriver(e.target.value)}
            >
              <option value="sendmail">{t('mail_settings.driver_sendmail')}</option>
              <option value="smtp">{t('mail_settings.driver_smtp')}</option>
              <option value="log">{t('mail_settings.driver_log')}</option>
            </select>
          </div>

          {driver === 'smtp' && (
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="sm:col-span-2">
                <label className="label">{t('mail_settings.smtp_host')}</label>
                <input
                  className="input"
                  value={smtpHost}
                  onChange={(e) => setSmtpHost(e.target.value)}
                  placeholder="smtp.sendgrid.net"
                />
              </div>
              <div>
                <label className="label">{t('mail_settings.smtp_port')}</label>
                <input
                  type="number"
                  className="input"
                  value={smtpPort}
                  onChange={(e) => setSmtpPort(Number(e.target.value) || 587)}
                  min={1}
                  max={65535}
                />
              </div>
              <div>
                <label className="label">{t('mail_settings.smtp_encryption')}</label>
                <select
                  className="input"
                  value={smtpEnc}
                  onChange={(e) => setSmtpEnc(e.target.value)}
                >
                  <option value="">{t('mail_settings.enc_none')}</option>
                  <option value="tls">TLS</option>
                  <option value="ssl">SSL</option>
                </select>
              </div>
              <div className="sm:col-span-2">
                <label className="label">{t('mail_settings.smtp_username')}</label>
                <input
                  className="input"
                  value={smtpUser}
                  onChange={(e) => setSmtpUser(e.target.value)}
                  autoComplete="off"
                />
              </div>
              <div className="sm:col-span-2">
                <label className="label">{t('mail_settings.smtp_password')}</label>
                <input
                  type="password"
                  className="input"
                  value={smtpPass}
                  onChange={(e) => setSmtpPass(e.target.value)}
                  placeholder={
                    q.data?.smtp_password_set
                      ? t('mail_settings.password_unchanged_hint')
                      : undefined
                  }
                  autoComplete="new-password"
                />
                {q.data?.smtp_password_set && (
                  <label className="mt-2 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input
                      type="checkbox"
                      checked={clearPass}
                      onChange={(e) => setClearPass(e.target.checked)}
                    />
                    {t('mail_settings.clear_password')}
                  </label>
                )}
              </div>
            </div>
          )}

          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className="label">{t('mail_settings.from_address')}</label>
              <input
                type="email"
                className="input"
                required
                value={fromAddress}
                onChange={(e) => setFromAddress(e.target.value)}
              />
            </div>
            <div>
              <label className="label">{t('mail_settings.from_name')}</label>
              <input
                className="input"
                required
                value={fromName}
                onChange={(e) => setFromName(e.target.value)}
              />
            </div>
          </div>

          <div className="flex flex-wrap gap-3">
            <button
              type="submit"
              disabled={saveM.isPending}
              className={clsx(
                'inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white',
                saveM.isPending
                  ? 'bg-primary-400'
                  : 'bg-primary-600 hover:bg-primary-700 dark:bg-primary-500',
              )}
            >
              <Save className="h-4 w-4" />
              {t('mail_settings.save')}
            </button>
            <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-3">
              <button
                type="button"
                disabled={testM.isPending || saveM.isPending}
                onClick={() => testM.mutate()}
                className="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
              >
                <Send className="h-4 w-4" />
                {t('mail_settings.test')}
              </button>
              <span className="text-xs text-gray-500 dark:text-gray-400">
                {t('mail_settings.test_hint')}
              </span>
            </div>
          </div>
        </form>
      )}
    </div>
  )
}
