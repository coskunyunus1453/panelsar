import { useEffect } from 'react'
import { Outlet } from 'react-router-dom'
import Sidebar from './Sidebar'
import Header from './Header'
import { useThemeStore } from '../../store/themeStore'

export default function Layout() {
  const sidebarCollapsed = useThemeStore((s) => s.sidebarCollapsed)
  const mobileSidebarOpen = useThemeStore((s) => s.mobileSidebarOpen)
  const closeMobileSidebar = useThemeStore((s) => s.closeMobileSidebar)

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
          <Outlet />
        </main>
      </div>
    </div>
  )
}
