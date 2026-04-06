import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useThemeStore } from '../store/themeStore'
import { useAuthStore } from '../store/authStore'
import { mustEnrollTwoFactor } from '../lib/authRoles'
import { useBranding } from '../hooks/useBranding'
import { safeBrandingImageUrl } from '../lib/urlSafety'
import api from '../services/api'
import { Sun, Moon, Globe, User, Lock, Smartphone, ImageIcon } from 'lucide-react'
import toast from 'react-hot-toast'
import QRCode from 'qrcode'

const languages = [
  { code: 'en', name: 'English' },
  { code: 'tr', name: 'Türkçe' },
  { code: 'de', name: 'Deutsch' },
  { code: 'fr', name: 'Français' },
  { code: 'es', name: 'Español' },
  { code: 'pt', name: 'Português' },
  { code: 'zh', name: '中文' },
  { code: 'ja', name: '日本語' },
  { code: 'ar', name: 'العربية' },
  { code: 'ru', name: 'Русский' },
]

export default function SettingsPage() {
  const { t, i18n } = useTranslation()
  const [searchParams] = useSearchParams()
  const { isDark, toggleTheme } = useThemeStore()
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const enforceAdmin2fa = useAuthStore((s) => s.enforceAdmin2fa)
  const updateUser = useAuthStore((s) => s.updateUser)
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')
  const mandatory2faParam = searchParams.get('mandatory2fa') === '1'
  const showMandatory2faBanner =
    mandatory2faParam || mustEnrollTwoFactor(user, enforceAdmin2fa)
  const { data: branding } = useBranding()
  const safeCustomerBrandingUrl = safeBrandingImageUrl(branding?.logo_customer_url)
  const safeAdminBrandingUrl = safeBrandingImageUrl(branding?.logo_admin_url)
  const brandingCfgQ = useQuery({
    queryKey: ['branding-config'],
    enabled: isAdmin,
    queryFn: async () => (await api.get('/admin/settings/branding')).data as { max_upload_kb: number },
  })

  const profileM = useMutation({
    mutationFn: async (payload: { name: string; email: string; locale?: string }) =>
      api.patch('/user/profile', payload),
    onSuccess: (res) => {
      const u = (res.data as { user?: typeof user })?.user
      if (u) updateUser(u)
      toast.success(t('settings.profile_saved'))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const first = ax.response?.data?.errors
        ? Object.values(ax.response.data.errors)[0]?.[0]
        : undefined
      toast.error(first ?? ax.response?.data?.message ?? String(err))
    },
  })

  const brandM = useMutation({
    mutationFn: async (fd: FormData) => api.post('/admin/settings/branding', fd),
    onSuccess: () => {
      toast.success(t('settings.branding_saved'))
      qc.invalidateQueries({ queryKey: ['branding'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string; debug_error?: string }; status?: number } }
      if (ax.response?.status === 413) {
        toast.error(t('settings.branding_413_hint'))
        return
      }
      const d = ax.response?.data
      toast.error([d?.message, d?.hint, d?.debug_error].filter(Boolean).join(' — ') || String(err), { duration: 10000 })
    },
  })
  const brandCfgM = useMutation({
    mutationFn: async (max_upload_kb: number) => api.put('/admin/settings/branding', { max_upload_kb }),
    onSuccess: () => {
      toast.success(t('settings.branding_limit_saved'))
      qc.invalidateQueries({ queryKey: ['branding-config'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })
  const brandDiagM = useMutation({
    mutationFn: async () =>
      (await api.get('/admin/settings/branding/diagnostics')).data as {
        ok: boolean
        checks: { key: string; ok: boolean; message: string }[]
      },
    onSuccess: (data) => {
      const summary = data.checks.map((c) => `${c.ok ? 'OK' : 'ERR'}: ${c.message}`).join(' | ')
      toast.success((data.ok ? 'Tanılama başarılı' : 'Tanılamada sorun var') + ' — ' + summary, { duration: 12000 })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const twoFaStatusQ = useQuery({
    queryKey: ['twofa-status'],
    enabled: !!user,
    queryFn: async () => (await api.get('/auth/2fa/status')).data as { two_factor_enabled: boolean },
  })

  const [twofaSetup, setTwofaSetup] = useState<null | { otpauth_url: string; secret: string }>(null)
  const [twofaOtp, setTwofaOtp] = useState('')
  const [twofaBackupCodes, setTwofaBackupCodes] = useState<string[] | null>(null)
  const [twofaQrDataUrl, setTwofaQrDataUrl] = useState<string | null>(null)
  const [twofaDisableOpen, setTwofaDisableOpen] = useState(false)
  const [twofaDisablePassword, setTwofaDisablePassword] = useState('')

  useEffect(() => {
    if (!twofaSetup?.otpauth_url) {
      setTwofaQrDataUrl(null)
      return
    }

    let cancelled = false
    QRCode.toDataURL(twofaSetup.otpauth_url, {
      margin: 1,
      width: 220,
      errorCorrectionLevel: 'M',
    })
      .then((url: string) => {
        if (!cancelled) setTwofaQrDataUrl(url)
      })
      .catch(() => {
        if (!cancelled) setTwofaQrDataUrl(null)
      })

    return () => {
      cancelled = true
    }
  }, [twofaSetup])

  const setupM = useMutation({
    mutationFn: async () =>
      (await api.post('/auth/2fa/setup')).data as { two_factor_enabled: false; otpauth_url: string; secret: string },
    onSuccess: (res) => {
      setTwofaSetup({ otpauth_url: res.otpauth_url, secret: res.secret })
      setTwofaOtp('')
      setTwofaBackupCodes(null)
      toast.success(t('settings.two_factor_setup_ready_toast'))
      qc.invalidateQueries({ queryKey: ['twofa-status'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const verifyM = useMutation({
    mutationFn: async (otp: string) => (await api.post('/auth/2fa/verify', { otp })).data as { backup_codes: string[] },
    onSuccess: (res) => {
      setTwofaBackupCodes(res.backup_codes)
      setTwofaOtp('')
      setTwofaSetup(null)
      toast.success(t('settings.two_factor_enabled_toast'))
      updateUser({ two_factor_enabled: true })
      qc.invalidateQueries({ queryKey: ['twofa-status'] })
    },
    onError: (err: unknown) => {
      const ax = err as {
        response?: { data?: { message?: string; errors?: Record<string, string[]>; code?: string } }
      }
      const otpErr = ax.response?.data?.errors?.otp?.[0]
      toast.error(otpErr ?? ax.response?.data?.message ?? String(err))
    },
  })

  const regenBackupM = useMutation({
    mutationFn: async () =>
      (await api.post('/auth/2fa/backup-codes/regenerate')).data as {
        backup_codes: string[]
      },
    onSuccess: (res) => {
      setTwofaBackupCodes(res.backup_codes)
      toast.success(t('settings.two_factor_backup_regen_toast'))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const disable2faM = useMutation({
    mutationFn: async (password: string) =>
      (await api.post('/auth/2fa/disable', { password })).data as { two_factor_enabled: boolean },
    onSuccess: () => {
      setTwofaDisableOpen(false)
      setTwofaDisablePassword('')
      setTwofaSetup(null)
      setTwofaBackupCodes(null)
      updateUser({ two_factor_enabled: false })
      toast.success(t('settings.two_factor_disabled_toast'))
      qc.invalidateQueries({ queryKey: ['twofa-status'] })
    },
    onError: (err: unknown) => {
      const ax = err as {
        response?: { data?: { message?: string; errors?: Record<string, string[]> } }
      }
      const pwdErr = ax.response?.data?.errors?.password?.[0]
      toast.error(pwdErr ?? ax.response?.data?.message ?? String(err))
    },
  })

  const optimizeImageToLimit = async (file: File, maxKb: number): Promise<File> => {
    if (!file.type.startsWith('image/')) return file
    const maxBytes = Math.max(64 * 1024, maxKb * 1024)
    if (file.size <= maxBytes) return file
    const bmp = await createImageBitmap(file)
    let w = bmp.width
    let h = bmp.height
    const canvas = document.createElement('canvas')
    const ctx = canvas.getContext('2d')
    if (!ctx) return file
    let quality = 0.88
    let out: Blob | null = null
    for (let i = 0; i < 8; i += 1) {
      canvas.width = Math.max(1, Math.round(w))
      canvas.height = Math.max(1, Math.round(h))
      ctx.clearRect(0, 0, canvas.width, canvas.height)
      ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height)
      out = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality))
      if (out && out.size <= maxBytes) break
      quality = Math.max(0.52, quality - 0.08)
      w *= 0.9
      h *= 0.9
    }
    if (!out) return file
    return new File([out], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' })
  }

  const passM = useMutation({
    mutationFn: async (payload: {
      current_password: string
      password: string
      password_confirmation: string
    }) => api.post('/user/password', payload),
    onSuccess: () => {
      toast.success('Şifre güncellendi')
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { errors?: Record<string, string[]> } } }
      const first = ax.response?.data?.errors
        ? Object.values(ax.response.data.errors)[0]?.[0]
        : undefined
      toast.error(first ?? String(err))
    },
  })

  useEffect(() => {
    if (!mandatory2faParam && !showMandatory2faBanner) return
    const id = requestAnimationFrame(() => {
      document.getElementById('settings-twofa')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
    return () => cancelAnimationFrame(id)
  }, [mandatory2faParam, showMandatory2faBanner])

  return (
    <div className="space-y-6 max-w-3xl">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
        {t('settings.title')}
      </h1>

      {showMandatory2faBanner && (
        <div
          role="alert"
          className="rounded-xl border border-amber-400/70 bg-amber-50 dark:bg-amber-950/35 p-4 text-sm text-amber-950 dark:text-amber-100"
        >
          {t('settings.mandatory_2fa_banner')}
        </div>
      )}

      {isAdmin && (
        <div className="card p-6">
          <div className="flex items-center gap-3 mb-6">
            <ImageIcon className="h-5 w-5 text-gray-500" />
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                {t('settings.branding')}
              </h2>
              <p className="text-sm text-gray-500 dark:text-gray-400">{t('settings.branding_hint')}</p>
            </div>
          </div>
          {(safeCustomerBrandingUrl || safeAdminBrandingUrl) && (
            <div className="flex flex-wrap gap-6 mb-4">
              {safeCustomerBrandingUrl && (
                <div>
                  <p className="text-xs text-gray-500 mb-1">{t('settings.logo_customer')}</p>
                  <img src={safeCustomerBrandingUrl} alt="" className="h-12 object-contain" />
                </div>
              )}
              {safeAdminBrandingUrl && (
                <div>
                  <p className="text-xs text-gray-500 mb-1">{t('settings.logo_admin')}</p>
                  <img src={safeAdminBrandingUrl} alt="" className="h-12 object-contain" />
                </div>
              )}
            </div>
          )}
          <form
            className="space-y-4 max-w-xl"
            onSubmit={async (ev) => {
              ev.preventDefault()
              const form = ev.currentTarget
              try {
                const maxKb = brandingCfgQ.data?.max_upload_kb ?? 900
                const inCustomer = form.elements.namedItem('logo_customer') as HTMLInputElement | null
                const inAdmin = form.elements.namedItem('logo_admin') as HTMLInputElement | null
                const fd = new FormData()
                const customerFile = inCustomer?.files?.[0]
                const adminFile = inAdmin?.files?.[0]
                if (customerFile) fd.append('logo_customer', await optimizeImageToLimit(customerFile, maxKb))
                if (adminFile) fd.append('logo_admin', await optimizeImageToLimit(adminFile, maxKb))
                if (!fd.has('logo_customer') && !fd.has('logo_admin')) {
                  toast.error(t('settings.branding_choose_file'))
                  return
                }
                brandM.mutate(fd)
                form.reset()
              } catch (e) {
                toast.error(e instanceof Error ? e.message : String(e))
              }
            }}
          >
            <div>
              <label className="label">{t('settings.logo_customer')}</label>
              <input name="logo_customer" type="file" accept="image/*" className="input w-full text-sm" />
            </div>
            <div>
              <label className="label">{t('settings.logo_admin')}</label>
              <input name="logo_admin" type="file" accept="image/*" className="input w-full text-sm" />
            </div>
            <button type="submit" className="btn-primary" disabled={brandM.isPending}>
              {t('common.save')}
            </button>
            <button type="button" className="btn-secondary ml-2" onClick={() => brandDiagM.mutate()} disabled={brandDiagM.isPending}>
              Storage Tanılama
            </button>
            <p className="text-xs text-gray-500">
              {t('settings.branding_limit_hint', { kb: brandingCfgQ.data?.max_upload_kb ?? 900 })}
            </p>
          </form>
          <div className="mt-5 border-t pt-4 max-w-xl">
            <label className="label">{t('settings.branding_limit_label')}</label>
            <div className="flex items-center gap-2">
              <input
                type="number"
                min={128}
                max={2048}
                className="input w-36"
                defaultValue={brandingCfgQ.data?.max_upload_kb ?? 900}
                id="branding-max-upload-kb"
              />
              <button
                type="button"
                className="btn-secondary"
                onClick={() => {
                  const el = document.getElementById('branding-max-upload-kb') as HTMLInputElement | null
                  const v = Number(el?.value || brandingCfgQ.data?.max_upload_kb || 900)
                  brandCfgM.mutate(v)
                }}
                disabled={brandCfgM.isPending}
              >
                {t('settings.branding_limit_save')}
              </button>
            </div>
          </div>
        </div>
      )}

      <div className="card p-6">
        <div className="flex items-center gap-3 mb-6">
          <User className="h-5 w-5 text-gray-500" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            {t('settings.profile')}
          </h2>
        </div>
        <form
          className="grid grid-cols-1 sm:grid-cols-2 gap-4"
          onSubmit={(ev) => {
            ev.preventDefault()
            const fd = new FormData(ev.currentTarget)
            profileM.mutate({
              name: String(fd.get('name') || ''),
              email: String(fd.get('email') || ''),
              locale: i18n.language.split('-')[0],
            })
          }}
        >
          <div>
            <label className="label">Name</label>
            <input name="name" type="text" defaultValue={user?.name} className="input" required />
          </div>
          <div>
            <label className="label">Email</label>
            <input name="email" type="email" defaultValue={user?.email} className="input" required />
          </div>
          <div className="sm:col-span-2">
            <button type="submit" className="btn-primary" disabled={profileM.isPending}>
              {t('common.save')}
            </button>
          </div>
        </form>
      </div>

      <div className="card p-6">
        <div className="flex items-center gap-3 mb-6">
          {isDark ? <Moon className="h-5 w-5 text-gray-500" /> : <Sun className="h-5 w-5 text-gray-500" />}
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            {t('settings.theme')}
          </h2>
        </div>
        <div className="flex gap-4">
          <button
            type="button"
            onClick={() => isDark && toggleTheme()}
            className={`flex-1 p-4 rounded-xl border-2 transition-colors ${
              !isDark
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                : 'border-gray-200 dark:border-gray-700'
            }`}
          >
            <Sun className="h-6 w-6 mx-auto mb-2 text-yellow-500" />
            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
              {t('settings.light_mode')}
            </p>
          </button>
          <button
            type="button"
            onClick={() => !isDark && toggleTheme()}
            className={`flex-1 p-4 rounded-xl border-2 transition-colors ${
              isDark
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                : 'border-gray-200 dark:border-gray-700'
            }`}
          >
            <Moon className="h-6 w-6 mx-auto mb-2 text-blue-500" />
            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
              {t('settings.dark_mode')}
            </p>
          </button>
        </div>
      </div>

      <div className="card p-6">
        <div className="flex items-center gap-3 mb-6">
          <Globe className="h-5 w-5 text-gray-500" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            {t('settings.language')}
          </h2>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2">
          {languages.map((lang) => (
            <button
              key={lang.code}
              type="button"
              onClick={() => i18n.changeLanguage(lang.code)}
              className={`p-3 rounded-xl border text-sm font-medium transition-colors ${
                i18n.language.startsWith(lang.code)
                  ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-400'
                  : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'
              }`}
            >
              {lang.name}
            </button>
          ))}
        </div>
      </div>

      <div className="card p-6">
        <div className="flex items-center gap-3 mb-6">
          <Lock className="h-5 w-5 text-gray-500" />
          <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
            {t('settings.change_password')}
          </h2>
        </div>
        <form
          className="space-y-4 max-w-md"
          onSubmit={(ev) => {
            ev.preventDefault()
            const fd = new FormData(ev.currentTarget)
            passM.mutate({
              current_password: String(fd.get('current_password') || ''),
              password: String(fd.get('password') || ''),
              password_confirmation: String(fd.get('password_confirmation') || ''),
            })
            ev.currentTarget.reset()
          }}
        >
          <div>
            <label className="label">Mevcut şifre</label>
            <input name="current_password" type="password" className="input w-full" required autoComplete="current-password" />
          </div>
          <div>
            <label className="label">Yeni şifre</label>
            <input name="password" type="password" className="input w-full" required autoComplete="new-password" />
          </div>
          <div>
            <label className="label">Yeni şifre (tekrar)</label>
            <input name="password_confirmation" type="password" className="input w-full" required autoComplete="new-password" />
          </div>
          <button type="submit" className="btn-primary" disabled={passM.isPending}>
            {t('common.save')}
          </button>
        </form>
      </div>

      <div id="settings-twofa" className="card p-6 scroll-mt-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Smartphone className="h-5 w-5 text-gray-500" />
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                {t('settings.two_factor')}
              </h2>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                {twoFaStatusQ.data?.two_factor_enabled
                  ? t('settings.two_factor_sub_on')
                  : t('settings.two_factor_sub_off')}
              </p>
            </div>
          </div>
        </div>

        <div className="mt-5 space-y-4">
          {!twoFaStatusQ.isLoading && !twoFaStatusQ.data?.two_factor_enabled && (
            <>
              <button type="button" className="btn-primary" disabled={setupM.isPending} onClick={() => setupM.mutate()}>
                {t('settings.two_factor_start_setup')}
              </button>

              {twofaSetup && (
                <div className="rounded-xl border border-white/10 bg-white/5 p-4">
                  <div className="flex flex-col sm:flex-row gap-5 sm:items-start">
                    <div>
                      <p className="text-sm text-gray-300 mb-2">{t('settings.two_factor_qr_label')}</p>
                      <div className="w-[220px] h-[220px] bg-black/20 rounded-lg flex items-center justify-center">
                        {twofaQrDataUrl ? (
                          <img src={twofaQrDataUrl} alt="" className="w-[220px] h-[220px] object-contain" />
                        ) : (
                          <span className="text-xs text-gray-500">{t('settings.two_factor_qr_loading')}</span>
                        )}
                      </div>
                    </div>

                    <div className="flex-1 space-y-3">
                      <div>
                        <p className="text-sm text-gray-300 mb-1">{t('settings.two_factor_secret_label')}</p>
                        <div className="flex items-center gap-2">
                          <input
                            value={twofaSetup.secret}
                            readOnly
                            className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm"
                          />
                          <button
                            type="button"
                            className="btn-secondary"
                            onClick={async () => {
                              try {
                                await navigator.clipboard.writeText(twofaSetup.secret)
                                toast.success(t('settings.two_factor_secret_copied'))
                              } catch {
                                toast.error(t('settings.two_factor_secret_copy_fail'))
                              }
                            }}
                          >
                            {t('settings.two_factor_copy')}
                          </button>
                        </div>
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-gray-300 mb-1.5">
                          {t('settings.two_factor_otp_label')}
                        </label>
                        <input
                          value={twofaOtp}
                          onChange={(e) => setTwofaOtp(e.target.value)}
                          inputMode="numeric"
                          className="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                          placeholder={t('settings.two_factor_otp_placeholder')}
                        />
                      </div>

                      <div className="flex gap-2">
                        <button
                          type="button"
                          className="btn-primary flex-1"
                          disabled={verifyM.isPending || !/^\d{6}$/.test(twofaOtp.trim())}
                          onClick={() => {
                            const code = twofaOtp.trim()
                            if (!/^\d{6}$/.test(code)) {
                              toast.error(t('settings.two_factor_otp_invalid'))
                              return
                            }
                            verifyM.mutate(code)
                          }}
                        >
                          {t('settings.two_factor_activate')}
                        </button>
                        <button
                          type="button"
                          className="btn-secondary"
                          onClick={() => {
                            setTwofaSetup(null)
                            setTwofaOtp('')
                          }}
                        >
                          {t('settings.two_factor_cancel')}
                        </button>
                      </div>

                      <p className="text-xs text-gray-500">
                        {enforceAdmin2fa === true
                          ? t('settings.two_factor_hint_enforced')
                          : t('settings.two_factor_hint_optional')}
                      </p>
                    </div>
                  </div>
                </div>
              )}
            </>
          )}

          {!!twoFaStatusQ.data?.two_factor_enabled && (
            <div className="space-y-3">
              <div className="rounded-xl border border-white/10 bg-white/5 p-4">
                <p className="text-sm text-gray-300">{t('settings.two_factor_active_title')}</p>
                <p className="text-xs text-gray-500 mt-1">{t('settings.two_factor_active_backup_hint')}</p>
              </div>
              <button
                type="button"
                className="btn-secondary w-full"
                disabled={regenBackupM.isPending}
                onClick={() => regenBackupM.mutate()}
              >
                {t('settings.two_factor_regen_backup')}
              </button>
              <button
                type="button"
                className="btn-primary w-full"
                disabled={setupM.isPending}
                onClick={() => setupM.mutate()}
              >
                {t('settings.two_factor_restart_setup')}
              </button>
              <button
                type="button"
                className="w-full rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm font-medium text-red-200 hover:bg-red-500/15 disabled:opacity-50"
                disabled={disable2faM.isPending}
                onClick={() => {
                  setTwofaDisableOpen((o) => !o)
                  setTwofaDisablePassword('')
                }}
              >
                {twofaDisableOpen ? t('settings.two_factor_cancel') : t('settings.two_factor_disable')}
              </button>
              {twofaDisableOpen && (
                <div className="rounded-xl border border-red-500/30 bg-black/20 p-4 space-y-3">
                  <p className="text-sm text-gray-300">{t('settings.two_factor_disable_hint')}</p>
                  <label className="block text-sm font-medium text-gray-300">{t('settings.two_factor_disable_password_label')}</label>
                  <input
                    type="password"
                    autoComplete="current-password"
                    value={twofaDisablePassword}
                    onChange={(e) => setTwofaDisablePassword(e.target.value)}
                    className="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white"
                  />
                  <div className="flex gap-2">
                    <button
                      type="button"
                      className="flex-1 rounded-xl bg-red-600 hover:bg-red-500 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50"
                      disabled={disable2faM.isPending || !twofaDisablePassword}
                      onClick={() => disable2faM.mutate(twofaDisablePassword)}
                    >
                      {t('settings.two_factor_disable_confirm')}
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}

          {twofaBackupCodes && twofaBackupCodes.length > 0 && (
            <div className="rounded-xl border border-primary-500/30 bg-primary-500/10 p-4">
              <p className="text-sm font-semibold text-white mb-2">{t('settings.two_factor_backup_title')}</p>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                {twofaBackupCodes.map((c) => (
                  <div key={c} className="rounded-lg bg-black/20 border border-white/10 px-3 py-2 text-xs text-gray-100">
                    {c}
                  </div>
                ))}
              </div>
              <p className="text-xs text-gray-300 mt-3">{t('settings.two_factor_backup_footer')}</p>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
