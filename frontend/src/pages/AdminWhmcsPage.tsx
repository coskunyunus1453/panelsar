import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useAuthStore } from '../store/authStore'
import { apiBaseUrl } from '../services/api'
import { Download, Link2 } from 'lucide-react'
import toast from 'react-hot-toast'

export default function AdminWhmcsPage() {
  const { t, i18n } = useTranslation()
  const isAdmin = useAuthStore((s) => s.user?.roles?.some((r) => r.name === 'admin'))
  const token = useAuthStore((s) => s.token)
  const [busy, setBusy] = useState(false)

  const downloadZip = async () => {
    if (!token) {
      toast.error(t('whmcs_integration.need_login'))
      return
    }
    setBusy(true)
    try {
      const locale = (i18n.language || 'en').split('-')[0]
      const res = await fetch(`${apiBaseUrl}/admin/integrations/whmcs/module-zip`, {
        method: 'GET',
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/zip',
          'X-Locale': locale,
        },
      })
      if (!res.ok) {
        const errText = await res.text()
        let msg = res.statusText
        try {
          const j = JSON.parse(errText) as { message?: string }
          if (j.message) msg = j.message
        } catch {
          if (errText) msg = errText.slice(0, 200)
        }
        throw new Error(msg)
      }
      const blob = await res.blob()
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = 'hostvim-whmcs-module.zip'
      a.rel = 'noopener'
      document.body.appendChild(a)
      a.click()
      a.remove()
      URL.revokeObjectURL(url)
      toast.success(t('whmcs_integration.download_ok'))
    } catch (e) {
      toast.error(e instanceof Error ? e.message : t('whmcs_integration.download_error'))
    } finally {
      setBusy(false)
    }
  }

  if (!isAdmin) {
    return <Navigate to="/dashboard" replace />
  }

  const steps = [
    t('whmcs_integration.step_1'),
    t('whmcs_integration.step_2'),
    t('whmcs_integration.step_3'),
    t('whmcs_integration.step_4'),
    t('whmcs_integration.step_5'),
    t('whmcs_integration.step_6'),
    t('whmcs_integration.step_7'),
  ]

  return (
    <div className="mx-auto max-w-3xl space-y-8">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <Link2 className="h-9 w-9 shrink-0 text-primary-500" aria-hidden />
          <div>
            <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">{t('whmcs_integration.title')}</h1>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">{t('whmcs_integration.subtitle')}</p>
          </div>
        </div>
        <button
          type="button"
          className="btn-primary inline-flex items-center gap-2"
          onClick={() => void downloadZip()}
          disabled={busy}
        >
          <Download className="h-4 w-4" />
          {busy ? t('whmcs_integration.downloading') : t('whmcs_integration.download')}
        </button>
      </div>

      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900/40">
        <h2 className="text-lg font-medium text-gray-900 dark:text-white">{t('whmcs_integration.steps_title')}</h2>
        <ol className="mt-4 list-decimal space-y-3 pl-5 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
          {steps.map((s, i) => (
            <li key={i}>{s}</li>
          ))}
        </ol>
      </div>

      <p className="text-xs text-gray-500 dark:text-gray-400">{t('whmcs_integration.footer_note')}</p>
    </div>
  )
}
