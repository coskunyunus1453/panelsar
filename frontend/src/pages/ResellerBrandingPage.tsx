import { useEffect, useState } from 'react'
import { Navigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import { tokenHasAbility } from '../lib/abilities'
import api from '../services/api'
import { safeBrandingImageUrl } from '../lib/urlSafety'
import toast from 'react-hot-toast'
import { Palette } from 'lucide-react'

type WhiteLabelDto = {
  user_id: number
  slug: string | null
  hostname: string | null
  primary_color: string | null
  secondary_color: string | null
  logo_customer_url: string | null
  logo_admin_url: string | null
  login_title: string | null
  login_subtitle: string | null
  mail_footer_plain: string | null
  onboarding_html: string | null
}

export default function ResellerBrandingPage() {
  const { t } = useTranslation()
  const user = useAuthStore((s) => s.user)
  const abilities = user?.abilities
  const can = tokenHasAbility(abilities, 'reseller:white_label')
  const qc = useQueryClient()

  const [slug, setSlug] = useState('')
  const [hostname, setHostname] = useState('')
  const [primaryColor, setPrimaryColor] = useState('')
  const [secondaryColor, setSecondaryColor] = useState('')
  const [loginTitle, setLoginTitle] = useState('')
  const [loginSubtitle, setLoginSubtitle] = useState('')
  const [mailFooter, setMailFooter] = useState('')
  const [onboardingHtml, setOnboardingHtml] = useState('')
  const [logoCustomer, setLogoCustomer] = useState<File | null>(null)
  const [logoAdmin, setLogoAdmin] = useState<File | null>(null)

  const q = useQuery({
    queryKey: ['reseller-white-label'],
    queryFn: async () => (await api.get<{ white_label: WhiteLabelDto }>('/reseller/white-label')).data,
    enabled: !!can,
  })

  useEffect(() => {
    const w = q.data?.white_label
    if (!w) return
    setSlug(w.slug ?? '')
    setHostname(w.hostname ?? '')
    setPrimaryColor(w.primary_color ?? '')
    setSecondaryColor(w.secondary_color ?? '')
    setLoginTitle(w.login_title ?? '')
    setLoginSubtitle(w.login_subtitle ?? '')
    setMailFooter(w.mail_footer_plain ?? '')
    setOnboardingHtml(w.onboarding_html ?? '')
  }, [q.data])

  const saveM = useMutation({
    mutationFn: async () => {
      const fd = new FormData()
      if (slug.trim()) fd.append('slug', slug.trim())
      else fd.append('slug', '')
      if (hostname.trim()) fd.append('hostname', hostname.trim())
      else fd.append('hostname', '')
      if (primaryColor.trim()) fd.append('primary_color', primaryColor.trim())
      if (secondaryColor.trim()) fd.append('secondary_color', secondaryColor.trim())
      if (loginTitle.trim()) fd.append('login_title', loginTitle.trim())
      if (loginSubtitle.trim()) fd.append('login_subtitle', loginSubtitle.trim())
      if (mailFooter.trim()) fd.append('mail_footer_plain', mailFooter)
      if (onboardingHtml.trim()) fd.append('onboarding_html', onboardingHtml)
      if (logoCustomer) fd.append('logo_customer', logoCustomer)
      if (logoAdmin) fd.append('logo_admin', logoAdmin)
      return api.post('/reseller/white-label', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    },
    onSuccess: () => {
      toast.success(t('reseller.white_label_saved'))
      qc.invalidateQueries({ queryKey: ['reseller-white-label'] })
      qc.invalidateQueries({ queryKey: ['branding'] })
      setLogoCustomer(null)
      setLogoAdmin(null)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  if (!can) {
    return <Navigate to="/dashboard" replace />
  }

  const wl = q.data?.white_label
  const cu = safeBrandingImageUrl(wl?.logo_customer_url ?? null)
  const au = safeBrandingImageUrl(wl?.logo_admin_url ?? null)
  const origin =
    typeof window !== 'undefined' ? `${window.location.origin}${window.location.pathname.replace(/\/admin\/?$/, '/')}` : ''
  const loginHint = slug.trim()
    ? `${origin}login?wl=${encodeURIComponent(slug.trim())}`
    : ''

  return (
    <div className="max-w-3xl space-y-6">
      <div className="flex items-start gap-3">
        <div className="rounded-xl bg-primary-600/15 p-3 text-primary-600 dark:text-primary-400">
          <Palette className="h-7 w-7" />
        </div>
        <div>
          <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">{t('reseller.white_label_title')}</h1>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{t('reseller.white_label_intro')}</p>
        </div>
      </div>

      <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-panel-card p-6 space-y-5">
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('reseller.white_label_slug')}
            </label>
            <input
              value={slug}
              onChange={(e) => setSlug(e.target.value)}
              className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
              placeholder="ornek-bayi"
            />
            {loginHint ? (
              <p className="mt-1 text-xs text-gray-500 break-all">{t('reseller.white_label_login_hint')}: {loginHint}</p>
            ) : null}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('reseller.white_label_hostname')}
            </label>
            <input
              value={hostname}
              onChange={(e) => setHostname(e.target.value)}
              className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
              placeholder="panel.ornek.com"
            />
            <p className="mt-1 text-xs text-gray-500">{t('reseller.white_label_hostname_help')}</p>
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('reseller.white_label_primary')}
            </label>
            <input
              type="color"
              value={primaryColor || '#ea580c'}
              onChange={(e) => setPrimaryColor(e.target.value)}
              className="h-10 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('reseller.white_label_secondary')}
            </label>
            <input
              type="color"
              value={secondaryColor || '#0f766e'}
              onChange={(e) => setSecondaryColor(e.target.value)}
              className="h-10 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white"
            />
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('reseller.white_label_logo_customer')}
            </label>
            {cu ? (
              <img src={cu} alt="" className="mb-2 h-12 object-contain" />
            ) : null}
            <input
              type="file"
              accept="image/*"
              onChange={(e) => setLogoCustomer(e.target.files?.[0] ?? null)}
              className="text-sm"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('reseller.white_label_logo_admin')}
            </label>
            {au ? (
              <img src={au} alt="" className="mb-2 h-12 object-contain" />
            ) : null}
            <input
              type="file"
              accept="image/*"
              onChange={(e) => setLogoAdmin(e.target.files?.[0] ?? null)}
              className="text-sm"
            />
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {t('reseller.white_label_login_title')}
          </label>
          <input
            value={loginTitle}
            onChange={(e) => setLoginTitle(e.target.value)}
            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {t('reseller.white_label_login_subtitle')}
          </label>
          <input
            value={loginSubtitle}
            onChange={(e) => setLoginSubtitle(e.target.value)}
            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {t('reseller.white_label_mail_footer')}
          </label>
          <textarea
            value={mailFooter}
            onChange={(e) => setMailFooter(e.target.value)}
            rows={3}
            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
            placeholder={t('reseller.white_label_mail_footer_ph')}
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {t('reseller.white_label_onboarding_html')}
          </label>
          <textarea
            value={onboardingHtml}
            onChange={(e) => setOnboardingHtml(e.target.value)}
            rows={5}
            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm font-mono text-xs"
            placeholder={t('reseller.white_label_onboarding_ph')}
          />
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <button
            type="button"
            className="btn-primary"
            disabled={saveM.isPending || q.isLoading}
            onClick={() => saveM.mutate()}
          >
            {saveM.isPending ? t('common.loading') : t('common.save')}
          </button>
        </div>
      </div>
    </div>
  )
}
