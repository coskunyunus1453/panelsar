import { useTranslation } from 'react-i18next'
import { useMutation } from '@tanstack/react-query'
import { useThemeStore } from '../store/themeStore'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { Sun, Moon, Globe, User, Lock, Smartphone } from 'lucide-react'
import toast from 'react-hot-toast'

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
  const { isDark, toggleTheme } = useThemeStore()
  const user = useAuthStore((s) => s.user)
  const updateUser = useAuthStore((s) => s.updateUser)

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

  return (
    <div className="space-y-6 max-w-3xl">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
        {t('settings.title')}
      </h1>

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

      <div className="card p-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Smartphone className="h-5 w-5 text-gray-500" />
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                {t('settings.two_factor')}
              </h2>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Yakında: TOTP ile iki aşamalı doğrulama
              </p>
            </div>
          </div>
          <button type="button" className="btn-secondary" disabled>
            Enable
          </button>
        </div>
      </div>
    </div>
  )
}
