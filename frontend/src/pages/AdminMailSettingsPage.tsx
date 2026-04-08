import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { Mail, Send, Save, Loader2, CheckCircle2, XCircle } from 'lucide-react'
import toast from 'react-hot-toast'
import clsx from 'clsx'

type MailSettings = {
  outbound_mail_persisted: boolean
  driver: string
  smtp_host: string
  smtp_port: number
  smtp_username: string
  smtp_password_set: boolean
  smtp_encryption: string
  from_address: string
  from_name: string
  smtp_recommended_host?: string
  smtp_recommended_port?: number
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
  const [wizardDomain, setWizardDomain] = useState('')
  const [wizardProgress, setWizardProgress] = useState(0)
  const [wizardTick, setWizardTick] = useState(0)
  const [dnsCopyText, setDnsCopyText] = useState('')
  const [stackProgress, setStackProgress] = useState(0)
  const [stackTick, setStackTick] = useState(0)
  const [stackOutput, setStackOutput] = useState('')
  const [stackValidation, setStackValidation] = useState<Array<{ key: string; label: string; ok: boolean; detail: string }>>([])
  const [stackRemediations, setStackRemediations] = useState<string[]>([])

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
    if (!wizardDomain) {
      const from = q.data.from_address || ''
      const parts = from.split('@')
      if (parts.length === 2) setWizardDomain(parts[1])
    }
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

  const diagM = useMutation({
    mutationFn: async () =>
      (
        await api.post<{
          ok: boolean
          dns_resolved: boolean
          ips: string[]
          port_open: boolean
          connection_error?: string
          hint?: string
        }>('/admin/settings/mail/diagnostics', { smtp_host: smtpHost, smtp_port: smtpPort })
      ).data,
    onSuccess: (d) => {
      const status = d.ok ? 'SMTP tanılama başarılı' : 'SMTP tanılama: sorun bulundu'
      const details = [
        `DNS: ${d.dns_resolved ? 'ok' : 'hata'}`,
        `Port: ${d.port_open ? 'ok' : 'kapalı'}`,
        d.hint ? `İpucu: ${d.hint}` : '',
      ]
        .filter(Boolean)
        .join(' | ')
      toast.success(`${status} — ${details}`, { duration: 7000 })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const wizardM = useMutation({
    mutationFn: async () =>
      (
        await api.post<{
          ok: boolean
          checks: Array<{ key: string; label: string; ok: boolean; detail: string }>
          recommended: { smtp_host: string; smtp_port: number; smtp_encryption: string; webmail_url?: string | null }
          dns_suggestions?: Array<{
            type: string
            name: string
            value?: string | null
            priority?: number
            required?: boolean
          }>
        }>('/admin/settings/mail/wizard-checks', { domain: wizardDomain.trim() })
      ).data,
    onSuccess: (d) => {
      setWizardProgress(100)
      if (d.ok) {
        toast.success('Mail sihirbazı: tüm kontroller başarılı', { duration: 5000 })
      } else {
        toast.error('Mail sihirbazı: eksikler var, aşağıdaki adımları tamamlayın', { duration: 5000 })
      }
    },
    onError: (err: unknown) => {
      setWizardProgress(100)
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const applyDnsM = useMutation({
    mutationFn: async () =>
      (
        await api.post<{
          message: string
          created: Array<{ type: string; name: string; value: string }>
          skipped: Array<{ reason: string }>
          errors: Array<{ error: string }>
        }>('/admin/settings/mail/wizard-apply-dns', { domain: wizardDomain.trim() })
      ).data,
    onSuccess: (d) => {
      const msg = `${d.message} | eklendi=${d.created.length}, atlandı=${d.skipped.length}, hata=${d.errors.length}`
      if (d.errors.length > 0) toast.error(msg, { duration: 7000 })
      else toast.success(msg, { duration: 7000 })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const setupStackM = useMutation({
    mutationFn: async () =>
      (
        await api.post<{
          message: string
          output?: string
          dns?: { created?: Array<unknown>; skipped?: Array<unknown>; errors?: Array<unknown> }
          validation?: { checks?: Array<{ key: string; label: string; ok: boolean; detail: string }> }
          remediations?: string[]
        }>('/admin/settings/mail/setup-stack', { domain: wizardDomain.trim() })
      ).data,
    onSuccess: (d) => {
      setStackProgress(100)
      setStackOutput(d.output ?? '')
      setStackValidation(d.validation?.checks ?? [])
      setStackRemediations(d.remediations ?? [])
      const c = d.dns?.created?.length ?? 0
      const s = d.dns?.skipped?.length ?? 0
      const e = d.dns?.errors?.length ?? 0
      const msg = `${d.message} | DNS eklendi=${c}, atlandı=${s}, hata=${e}`
      if (e > 0) toast.error(msg, { duration: 9000 })
      else toast.success(msg, { duration: 9000 })
      void wizardM.mutate()
    },
    onError: (err: unknown) => {
      setStackProgress(100)
      const ax = err as { response?: { data?: { message?: string; output?: string } } }
      setStackOutput(ax.response?.data?.output ?? '')
      setStackValidation([])
      setStackRemediations([])
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  useEffect(() => {
    if (!wizardM.isPending) return
    setWizardProgress(7)
    const id = window.setInterval(() => {
      setWizardTick((s) => s + 1)
      setWizardProgress((p) => (p >= 92 ? p : p + 4))
    }, 180)
    return () => window.clearInterval(id)
  }, [wizardM.isPending])

  useEffect(() => {
    if (!setupStackM.isPending) return
    setStackProgress(8)
    const id = window.setInterval(() => {
      setStackTick((s) => s + 1)
      setStackProgress((p) => (p >= 90 ? p : p + 3))
    }, 220)
    return () => window.clearInterval(id)
  }, [setupStackM.isPending])

  const runningLabel = useMemo(() => {
    const steps = [
      'DNS kayıtları kontrol ediliyor...',
      'mail host çözümleme test ediliyor...',
      'SMTP portları test ediliyor...',
      'IMAP portları test ediliyor...',
      'webmail host/https kontrol ediliyor...',
      'önerilen ayarlar hazırlanıyor...',
    ]
    return steps[wizardTick % steps.length]
  }, [wizardTick])

  const stackRunningLabel = useMemo(() => {
    const steps = [
      'Mail paketleri hazırlanıyor...',
      'Postfix ve Dovecot kuruluyor...',
      'Servisler etkinleştiriliyor...',
      'Firewall portları açılıyor...',
      'Webmail host yapılandırılıyor...',
      'DNS otomatik kayıtları uygulanıyor...',
    ]
    return steps[stackTick % steps.length]
  }, [stackTick])

  useEffect(() => {
    const rows = wizardM.data?.dns_suggestions ?? []
    if (!rows.length) {
      setDnsCopyText('')
      return
    }
    const txt = rows
      .map((r) => {
        if (r.type === 'MX') return `${r.type} ${r.name} ${r.value ?? ''} priority=${r.priority ?? 10}`
        return `${r.type} ${r.name} ${r.value ?? ''}`
      })
      .join('\n')
    setDnsCopyText(txt)
  }, [wizardM.data?.dns_suggestions])

  const applyWizardRecommendation = () => {
    const rec = wizardM.data?.recommended
    if (!rec) return
    setDriver('smtp')
    setSmtpHost(rec.smtp_host)
    setSmtpPort(rec.smtp_port)
    setSmtpEnc(rec.smtp_encryption)
    toast.success('Önerilen SMTP ayarları forma uygulandı')
  }

  const copyDnsTemplate = async () => {
    if (!dnsCopyText) return
    try {
      await navigator.clipboard.writeText(dnsCopyText)
      toast.success('DNS şablonu panoya kopyalandı')
    } catch {
      toast.error('Panoya kopyalanamadı')
    }
  }

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

      {q.isSuccess && q.data && !q.data.outbound_mail_persisted && (
        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
          {t('mail_settings.not_persisted_warning')}
        </div>
      )}

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
                {!!q.data?.smtp_recommended_host && (
                  <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Öneri (sunucu içi gönderim): <code>{q.data.smtp_recommended_host}</code>:{' '}
                    <code>{q.data.smtp_recommended_port ?? 587}</code>
                  </p>
                )}
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

          <div className="rounded-xl border border-primary-200 bg-primary-50/60 p-4 dark:border-primary-900/40 dark:bg-primary-950/20 space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <p className="text-sm font-semibold text-gray-900 dark:text-white">Mail Kurulum Sihirbazı</p>
              <div className="flex flex-wrap gap-2">
                <button
                  type="button"
                  className="btn-primary"
                  disabled={wizardM.isPending || setupStackM.isPending || !wizardDomain.trim()}
                  onClick={() => wizardM.mutate()}
                >
                  {wizardM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Kontrol Et'}
                </button>
                <button
                  type="button"
                  className="btn-secondary"
                  disabled={wizardM.isPending || setupStackM.isPending || !wizardDomain.trim()}
                  onClick={() => setupStackM.mutate()}
                >
                  {setupStackM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Mail Stack Kur'}
                </button>
              </div>
            </div>
            <div>
              <label className="label">Domain</label>
              <input
                className="input max-w-md"
                value={wizardDomain}
                onChange={(e) => setWizardDomain(e.target.value)}
                placeholder="relevant.tr"
              />
            </div>
            <div className="h-2 w-full rounded bg-gray-200 dark:bg-gray-800 overflow-hidden">
              <div className="h-full bg-primary-600 transition-all duration-200" style={{ width: `${wizardProgress}%` }} />
            </div>
            <p className="text-xs text-gray-600 dark:text-gray-300">
              {wizardM.isPending ? runningLabel : 'DNS, SMTP, IMAP ve webmail kontrolleri gerçek bağlantı testi ile yapılır.'}
            </p>
            {(setupStackM.isPending || stackOutput) && (
              <div className="rounded-lg border border-secondary-200 bg-secondary-50/70 p-3 text-xs text-secondary-900 dark:border-secondary-900/40 dark:bg-secondary-950/20 dark:text-secondary-200">
                <div className="mb-2 flex items-center justify-between gap-2">
                  <span className="font-medium">Kurulum durumu</span>
                  <span>{setupStackM.isPending ? stackRunningLabel : 'Tamamlandı'}</span>
                </div>
                <div className="h-2 w-full rounded bg-secondary-100 dark:bg-secondary-900/50 overflow-hidden">
                  <div className="h-full bg-secondary-600 transition-all duration-200" style={{ width: `${stackProgress}%` }} />
                </div>
                {stackOutput && (
                  <pre className="mt-2 max-h-44 overflow-auto rounded bg-black p-2 text-[11px] text-green-200 whitespace-pre-wrap">{stackOutput}</pre>
                )}
                {stackValidation.length > 0 && (
                  <div className="mt-2 space-y-1">
                    {stackValidation.map((c) => (
                      <div key={`sv-${c.key}`} className="flex items-start gap-2 rounded border border-secondary-200/60 dark:border-secondary-900/40 px-2 py-1">
                        {c.ok ? <CheckCircle2 className="h-3.5 w-3.5 mt-0.5 text-emerald-500" /> : <XCircle className="h-3.5 w-3.5 mt-0.5 text-red-500" />}
                        <div>
                          <div className="text-[11px] font-semibold">{c.label}</div>
                          <div className="text-[11px] opacity-90">{c.detail}</div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
                {stackRemediations.length > 0 && (
                  <div className="mt-2 rounded border border-amber-200/70 dark:border-amber-900/40 bg-amber-50/70 dark:bg-amber-950/20 p-2">
                    <div className="text-[11px] font-semibold mb-1">Onarım önerileri</div>
                    <ul className="text-[11px] space-y-0.5 list-disc pl-4">
                      {stackRemediations.map((r, i) => (
                        <li key={`rm-${i}`}>{r}</li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            )}

            {wizardM.data?.checks && (
              <div className="space-y-2">
                {wizardM.data.checks.map((c) => (
                  <div key={c.key} className="flex items-start gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white/70 dark:bg-gray-900/40 px-3 py-2 text-sm">
                    {c.ok ? (
                      <CheckCircle2 className="mt-0.5 h-4 w-4 text-emerald-600" />
                    ) : (
                      <XCircle className="mt-0.5 h-4 w-4 text-red-600" />
                    )}
                    <div>
                      <div className="font-medium text-gray-900 dark:text-white">{c.label}</div>
                      <div className="text-xs text-gray-600 dark:text-gray-300">{c.detail}</div>
                    </div>
                  </div>
                ))}
              </div>
            )}

            {wizardM.data?.recommended && (
              <div className="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 text-xs text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-200">
                Önerilen SMTP: <code>{wizardM.data.recommended.smtp_host}</code>:{' '}
                <code>{wizardM.data.recommended.smtp_port}</code> ({wizardM.data.recommended.smtp_encryption.toUpperCase()})
                {wizardM.data.recommended.webmail_url ? (
                  <span> | Webmail: <code>{wizardM.data.recommended.webmail_url}</code></span>
                ) : null}
                <div className="mt-2">
                  <button type="button" className="btn-secondary py-1.5 text-xs" onClick={applyWizardRecommendation}>
                    SMTP ayarlarına uygula
                  </button>
                </div>
              </div>
            )}

            {!!dnsCopyText && (
              <div className="rounded-lg border border-amber-200 bg-amber-50/70 p-3 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-200">
                <div className="mb-2 font-medium">DNS kayıt şablonu</div>
                <pre className="max-h-40 overflow-auto rounded bg-black p-2 text-[11px] text-green-200 whitespace-pre-wrap">{dnsCopyText}</pre>
                <div className="mt-2 flex flex-wrap gap-2">
                  <button type="button" className="btn-secondary py-1.5 text-xs" onClick={copyDnsTemplate}>
                    DNS şablonunu kopyala
                  </button>
                  <button
                    type="button"
                    className="btn-primary py-1.5 text-xs"
                    onClick={() => applyDnsM.mutate()}
                    disabled={applyDnsM.isPending || !wizardDomain.trim()}
                  >
                    {applyDnsM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Eksik DNS kayıtlarını otomatik ekle'}
                  </button>
                </div>
              </div>
            )}
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
                disabled={
                  testM.isPending ||
                  saveM.isPending ||
                  !q.data?.outbound_mail_persisted ||
                  q.data?.driver === 'log'
                }
                onClick={() => testM.mutate()}
                className="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
              >
                <Send className="h-4 w-4" />
                {t('mail_settings.test')}
              </button>
              <button
                type="button"
                disabled={diagM.isPending || saveM.isPending || driver !== 'smtp'}
                onClick={() => diagM.mutate()}
                className="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
              >
                SMTP tanılama
              </button>
              <span className="text-xs text-gray-500 dark:text-gray-400">
                {q.data?.driver === 'log' && q.data?.outbound_mail_persisted
                  ? t('mail_settings.test_disabled_log')
                  : t('mail_settings.test_hint')}
              </span>
            </div>
          </div>
        </form>
      )}
    </div>
  )
}
