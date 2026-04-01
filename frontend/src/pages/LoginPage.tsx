import { useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAuthStore } from '../store/authStore'
import { authService } from '../services/authService'
import { useBranding } from '../hooks/useBranding'
import { Server, Eye, EyeOff } from 'lucide-react'
import toast from 'react-hot-toast'
import { isVendorProfile } from '../config/profile'

export default function LoginPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const location = useLocation()
  const setAuth = useAuthStore((s) => s.setAuth)
  const portal: 'customer' | 'vendor' =
    isVendorProfile && location.pathname.startsWith('/vendor') ? 'vendor' : 'customer'

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [loading, setLoading] = useState(false)
  const { data: branding } = useBranding()

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)

    try {
      const data = await authService.login(email, password, portal)
      setAuth(data.user, data.token, portal)
      toast.success(t('dashboard.welcome'))
      navigate(portal === 'vendor' ? '/admin/vendor-control' : '/dashboard')
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } }
      toast.error(error.response?.data?.message || t('common.error'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 via-blue-950 to-gray-900 px-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          {branding?.logo_customer_url ? (
            <div className="flex justify-center mb-4">
              <img src={branding.logo_customer_url} alt="" className="max-h-20 object-contain" />
            </div>
          ) : (
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-600 mb-4">
              <Server className="h-8 w-8 text-white" />
            </div>
          )}
          <h1 className="text-3xl font-bold text-white">Panelsar</h1>
          <p className="text-gray-400 mt-2">{t('auth.login_subtitle')}</p>
        </div>

        <div className="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-8">
          <h2 className="text-xl font-semibold text-white mb-6">
            {t('auth.login_title')}
          </h2>
          {portal === 'vendor' && (
            <p className="mb-4 rounded-lg border border-cyan-500/30 bg-cyan-500/10 px-3 py-2 text-xs text-cyan-100">
              Vendor girisi: yalnizca yazilim sahibi hesaplari.
            </p>
          )}

          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-1.5">
                {t('auth.email')}
              </label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                placeholder="admin@panelsar.com"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-300 mb-1.5">
                {t('auth.password')}
              </label>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all pr-12"
                  placeholder="••••••••"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white"
                >
                  {showPassword ? (
                    <EyeOff className="h-5 w-5" />
                  ) : (
                    <Eye className="h-5 w-5" />
                  )}
                </button>
              </div>
            </div>

            <div className="flex items-center">
              <label className="flex items-center gap-2 text-sm text-gray-400">
                <input
                  type="checkbox"
                  className="rounded border-gray-600 bg-white/5 text-primary-500 focus:ring-primary-500"
                />
                {t('auth.remember')}
              </label>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full py-3 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-gray-900"
            >
              {loading ? t('common.loading') : t('auth.login')}
            </button>
          </form>
        </div>

        <p className="text-center text-gray-500 text-sm mt-6">
          Panelsar v0.1.0 &mdash; {t('app.tagline')}
        </p>
      </div>
    </div>
  )
}
