import { useEffect, useState } from 'react'
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAuthStore } from '../store/authStore'
import { authService } from '../services/authService'
import { useBranding } from '../hooks/useBranding'
import { Server, Eye, EyeOff } from 'lucide-react'
import toast from 'react-hot-toast'
import { isVendorProfile } from '../config/profile'
import { mustEnrollTwoFactor } from '../lib/authRoles'

export default function LoginPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const location = useLocation()
  const setAuth = useAuthStore((s) => s.setAuth)
  const portal: 'customer' | 'vendor' =
    isVendorProfile && location.pathname.startsWith('/vendor') ? 'vendor' : 'customer'

  const [step, setStep] = useState<'password' | 'twofa'>('password')
  const [otp, setOtp] = useState('')
  const [backupCode, setBackupCode] = useState('')
  const [twofaMethod, setTwofaMethod] = useState<'otp' | 'backup'>('otp')

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [loading, setLoading] = useState(false)
  const { data: branding } = useBranding()

  useEffect(() => {
    const pid = searchParams.get('package_id')
    const cycle = searchParams.get('billing_cycle') ?? searchParams.get('cycle')
    if (pid) {
      const n = Number(pid)
      if (!Number.isNaN(n)) {
        sessionStorage.setItem(
          'pendingCheckout',
          JSON.stringify({
            package_id: n,
            billing_cycle: cycle === 'yearly' || cycle === 'annual' ? 'yearly' : 'monthly',
          }),
        )
      }
    }
  }, [searchParams])

  const navigateAfterLogin = () => {
    if (sessionStorage.getItem('pendingCheckout')) {
      navigate('/billing?autoCheckout=1')
      return
    }
    if (portal === 'vendor') {
      navigate('/admin/vendor-control')
      return
    }
    navigate('/dashboard')
  }

  const finishLogin = (data: Awaited<ReturnType<typeof authService.login>>) => {
    setAuth(data.user, data.token, portal, { enforce_admin_2fa: data.enforce_admin_2fa })
    toast.success(t('dashboard.welcome'))
    const enforce = data.enforce_admin_2fa ?? null
    if (mustEnrollTwoFactor(data.user, enforce)) {
      navigate('/settings?mandatory2fa=1')
      return
    }
    navigateAfterLogin()
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (loading) return
    setLoading(true)

    try {
      if (step === 'password') {
        const data = await authService.login(email.trim(), password, portal)
        finishLogin(data)
        return
      }

      if (twofaMethod === 'otp') {
        const code = otp.trim()
        if (!/^\d{6}$/.test(code)) {
          toast.error('Geçerli 6 haneli OTP kodu girin.')
          return
        }

        const data = await authService.login(email.trim(), password, portal, { otp: code })
        finishLogin(data)
        return
      }

      const bc = backupCode.trim()
      if (!/^(\d{5}-\d{5}|\d{10})$/.test(bc)) {
        toast.error('Yedek kodu formatı: 12345-67890')
        return
      }

      const data = await authService.login(email.trim(), password, portal, { backupCode: bc })
      finishLogin(data)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string; code?: string } } }
      const code = error.response?.data?.code
      const message = error.response?.data?.message || t('common.error')

      if (step === 'password' && code === 'twofa_required') {
        // OTP/backup adımı göster
        setStep('twofa')
        setOtp('')
        setBackupCode('')
        return
      }

      toast.error(message)
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
          <h1 className="text-3xl font-bold text-white">{t('app.name')}</h1>
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
            {step === 'password' ? (
              <>
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-1.5">{t('auth.email')}</label>
                  <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                    placeholder="admin@hostvim.com"
                    required
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-1.5">{t('auth.password')}</label>
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
                      {showPassword ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
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
              </>
            ) : (
              <>
                <div className="rounded-lg border border-white/10 bg-white/5 p-4">
                  <p className="text-white text-sm font-medium mb-1">2FA doğrulama gerekli</p>
                  <p className="text-gray-400 text-xs">OTP kodu veya yedek kod ile giriş yapın.</p>
                </div>

                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() => setTwofaMethod('otp')}
                    className={`flex-1 py-2 rounded-xl border text-sm transition-colors ${
                      twofaMethod === 'otp'
                        ? 'border-primary-500 bg-primary-600/20 text-white'
                        : 'border-white/10 bg-white/5 text-gray-300 hover:bg-white/10'
                    }`}
                  >
                    OTP
                  </button>
                  <button
                    type="button"
                    onClick={() => setTwofaMethod('backup')}
                    className={`flex-1 py-2 rounded-xl border text-sm transition-colors ${
                      twofaMethod === 'backup'
                        ? 'border-primary-500 bg-primary-600/20 text-white'
                        : 'border-white/10 bg-white/5 text-gray-300 hover:bg-white/10'
                    }`}
                  >
                    Yedek Kod
                  </button>
                </div>

                {twofaMethod === 'otp' ? (
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-1.5">OTP (6 hane)</label>
                    <input
                      value={otp}
                      onChange={(e) => setOtp(e.target.value)}
                      inputMode="numeric"
                      className="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                      placeholder="123456"
                      required
                    />
                  </div>
                ) : (
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-1.5">Yedek Kod</label>
                    <input
                      value={backupCode}
                      onChange={(e) => setBackupCode(e.target.value)}
                      className="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                      placeholder="12345-67890"
                      required
                    />
                  </div>
                )}

                <button
                  type="submit"
                  disabled={loading}
                  className="w-full py-3 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-gray-900"
                >
                  {loading ? t('common.loading') : 'Doğrula ve Giriş Yap'}
                </button>

                <button
                  type="button"
                  onClick={() => {
                    setStep('password')
                    setOtp('')
                    setBackupCode('')
                  }}
                  className="w-full text-center text-sm text-gray-300 hover:text-white"
                >
                  Şifre ekranına geri dön
                </button>
              </>
            )}
          </form>
        </div>

        <p className="text-center text-gray-500 text-sm mt-6">
          {t('app.name')} v0.1.0 &mdash; {t('app.tagline')}
        </p>
      </div>
    </div>
  )
}
