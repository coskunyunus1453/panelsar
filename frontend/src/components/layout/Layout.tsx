import { useEffect } from 'react'
import { Outlet } from 'react-router-dom'
import Sidebar from './Sidebar'
import Header from './Header'
import { useThemeStore } from '../../store/themeStore'
import { useUiModeStore } from '../../store/uiModeStore'
import { useAuthStore } from '../../store/authStore'
import { authService } from '../../services/authService'

export default function Layout() {
  const sidebarCollapsed = useThemeStore((s) => s.sidebarCollapsed)
  const mobileSidebarOpen = useThemeStore((s) => s.mobileSidebarOpen)
  const closeMobileSidebar = useThemeStore((s) => s.closeMobileSidebar)
  const token = useAuthStore((s) => s.token)
  const updateUser = useAuthStore((s) => s.updateUser)
  const onboardingSeen = useUiModeStore((s) => s.onboardingSeen)
  const setMode = useUiModeStore((s) => s.setMode)
  const markOnboardingSeen = useUiModeStore((s) => s.markOnboardingSeen)

  useEffect(() => {
    if (!token) {
      return
    }
    authService.me().then((d) => updateUser(d.user)).catch(() => {})
  }, [token, updateUser])

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
              <p className="text-sm font-semibold text-gray-900 dark:text-white">Hızlı başlangıç modu</p>
              <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">
                Kolay mod temel özellikleri gösterir. Gelişmiş modda tüm teknik araçlar görünür.
              </p>
              <div className="mt-3 flex flex-wrap gap-2">
                <button className="btn-primary py-1.5 text-xs" onClick={() => { setMode('easy'); markOnboardingSeen() }}>
                  Kolay Modu Kullan
                </button>
                <button className="btn-secondary py-1.5 text-xs" onClick={() => { setMode('advanced'); markOnboardingSeen() }}>
                  Gelişmiş Moda Geç
                </button>
                <button className="btn-secondary py-1.5 text-xs" onClick={markOnboardingSeen}>
                  Kapat
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
