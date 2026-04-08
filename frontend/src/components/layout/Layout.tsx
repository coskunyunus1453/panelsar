import { useEffect } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import Sidebar from './Sidebar'
import Header from './Header'
import { useThemeStore } from '../../store/themeStore'
import { useUiModeStore } from '../../store/uiModeStore'
import { useAuthStore } from '../../store/authStore'
import { authService } from '../../services/authService'
import { mustEnrollTwoFactor } from '../../lib/authRoles'

export default function Layout() {
  const { t } = useTranslation()
  const sidebarCollapsed = useThemeStore((s) => s.sidebarCollapsed)
  const mobileSidebarOpen = useThemeStore((s) => s.mobileSidebarOpen)
  const closeMobileSidebar = useThemeStore((s) => s.closeMobileSidebar)
  const token = useAuthStore((s) => s.token)
  const user = useAuthStore((s) => s.user)
  const enforceAdmin2fa = useAuthStore((s) => s.enforceAdmin2fa)
  const updateUser = useAuthStore((s) => s.updateUser)
  const setEnforceAdmin2fa = useAuthStore((s) => s.setEnforceAdmin2fa)
  const setWhiteLabelUi = useAuthStore((s) => s.setWhiteLabelUi)
  const whiteLabel = useAuthStore((s) => s.whiteLabel)
  const location = useLocation()
  const onboardingSeen = useUiModeStore((s) => s.onboardingSeen)
  const setMode = useUiModeStore((s) => s.setMode)
  const markOnboardingSeen = useUiModeStore((s) => s.markOnboardingSeen)

  useEffect(() => {
    if (!token) {
      return
    }
    authService
      .me()
      .then((d) => {
        updateUser(d.user)
        setWhiteLabelUi(d.white_label ?? null)
        if (typeof d.enforce_admin_2fa === 'boolean') {
          setEnforceAdmin2fa(d.enforce_admin_2fa)
        }
      })
      .catch(() => {})
  }, [token, updateUser, setEnforceAdmin2fa, setWhiteLabelUi])

  useEffect(() => {
    const root = document.documentElement
    if (whiteLabel?.primary_color) {
      root.style.setProperty('--wl-primary', whiteLabel.primary_color)
    } else {
      root.style.removeProperty('--wl-primary')
    }
    if (whiteLabel?.secondary_color) {
      root.style.setProperty('--wl-secondary', whiteLabel.secondary_color)
    } else {
      root.style.removeProperty('--wl-secondary')
    }
    return () => {
      root.style.removeProperty('--wl-primary')
      root.style.removeProperty('--wl-secondary')
    }
  }, [whiteLabel])

  useEffect(() => {
    if (!mobileSidebarOpen) {
      return
    }
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        closeMobileSidebar()
      }
    }
    window.addEventListener('keydown', onKey)
    const prevOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      window.removeEventListener('keydown', onKey)
      document.body.style.overflow = prevOverflow
    }
  }, [mobileSidebarOpen, closeMobileSidebar])

  const needsResellerCustomerOnboarding =
    !!user &&
    !!user.parent_id &&
    !user.onboarding_completed_at &&
    !user.roles?.some((r) => r.name === 'admin' || r.name === 'reseller')

  if (needsResellerCustomerOnboarding && location.pathname !== '/onboarding') {
    return <Navigate to="/onboarding" replace />
  }

  if (user && mustEnrollTwoFactor(user, enforceAdmin2fa) && !location.pathname.startsWith('/settings')) {
    return <Navigate to="/settings?mandatory2fa=1" replace />
  }

  return (
    <div className="flex h-screen overflow-hidden bg-gray-50 dark:bg-panel-bg">
      {mobileSidebarOpen && (
        <button
          type="button"
          className="fixed inset-0 z-40 bg-black/50 md:hidden"
          aria-label="Close menu"
          onClick={closeMobileSidebar}
        />
      )}
      <Sidebar />
      <div
        className={`flex flex-1 flex-col overflow-hidden transition-[margin] duration-300 ml-0 ${
          sidebarCollapsed ? 'md:ml-16' : 'md:ml-64'
        }`}
      >
        <Header />
        <main className="flex-1 overflow-y-auto p-4 sm:p-6">
          {!onboardingSeen && (
            <div className="mb-4 rounded-xl border border-primary-200 dark:border-primary-900/40 bg-primary-50/80 dark:bg-primary-950/20 p-4">
              <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('ui_mode.onboarding_title')}</p>
              <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">
                {t('ui_mode.onboarding_desc')}
              </p>
              <div className="mt-3 flex flex-wrap gap-2">
                <button className="btn-primary py-1.5 text-xs" onClick={() => { setMode('easy'); markOnboardingSeen() }}>
                  {t('ui_mode.use_easy')}
                </button>
                <button className="btn-secondary py-1.5 text-xs" onClick={() => { setMode('advanced'); markOnboardingSeen() }}>
                  {t('ui_mode.switch_to_advanced')}
                </button>
                <button className="btn-secondary py-1.5 text-xs" onClick={markOnboardingSeen}>
                  {t('common.close')}
                </button>
              </div>
            </div>
          )}
          <Outlet />
        </main>
      </div>
    </div>
  )
}
