import { useEffect } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useThemeStore } from '../../store/themeStore'
import { useAuthStore } from '../../store/authStore'
import {
  LayoutDashboard,
  Globe,
  Database,
  Mail,
  FolderOpen,
  Shield,
  Clock,
  HardDrive,
  Activity,
  Lock,
  Settings,
  Users,
  Package,
  Download,
  Terminal,
  ChevronLeft,
  ChevronRight,
  Server,
  CreditCard,
  Network,
  Gauge,
  KeyRound,
  Wrench,
  X,
  TerminalSquare,
  Layers,
  Send,
} from 'lucide-react'

const navItems = [
  { path: '/dashboard', icon: LayoutDashboard, label: 'nav.dashboard' },
  { path: '/domains', icon: Globe, label: 'nav.domains' },
  { path: '/dns', icon: Network, label: 'nav.dns' },
  { path: '/databases', icon: Database, label: 'nav.databases' },
  { path: '/email', icon: Mail, label: 'nav.email' },
  { path: '/files', icon: FolderOpen, label: 'nav.files' },
  { path: '/ftp', icon: HardDrive, label: 'nav.ftp' },
  { path: '/ssl', icon: Lock, label: 'nav.ssl' },
  { path: '/backups', icon: Shield, label: 'nav.backups' },
  { path: '/cron', icon: Clock, label: 'nav.cron' },
  { path: '/monitoring', icon: Activity, label: 'nav.monitoring' },
  { path: '/security', icon: Shield, label: 'nav.security' },
  { path: '/installer', icon: Download, label: 'nav.installer' },
  { path: '/site-tools', icon: Terminal, label: 'nav.site_tools' },
  { path: '/billing', icon: CreditCard, label: 'nav.billing' },
  { path: '/reseller', icon: Users, label: 'nav.reseller' },
  { path: '/settings', icon: Settings, label: 'nav.settings' },
]

const adminItems = [
  { path: '/admin/server-setup', icon: Wrench, label: 'nav.server_setup' },
  { path: '/admin/stack', icon: Layers, label: 'nav.stack' },
  { path: '/admin/mail-settings', icon: Send, label: 'nav.outbound_mail' },
  { path: '/admin/terminal', icon: TerminalSquare, label: 'nav.terminal' },
  { path: '/admin/system', icon: Gauge, label: 'nav.system' },
  { path: '/admin/users', icon: Users, label: 'nav.users' },
  { path: '/admin/packages', icon: Package, label: 'nav.packages' },
  { path: '/admin/license', icon: KeyRound, label: 'nav.license' },
]

export default function Sidebar() {
  const { t } = useTranslation()
  const location = useLocation()
  const {
    sidebarCollapsed,
    toggleSidebar,
    mobileSidebarOpen,
    closeMobileSidebar,
  } = useThemeStore()
  const user = useAuthStore((s) => s.user)
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')
  const isReseller = user?.roles?.some((r) => r.name === 'reseller')
  const visibleNav = navItems.filter(
    (item) => item.path !== '/reseller' || isReseller,
  )

  useEffect(() => {
    closeMobileSidebar()
  }, [location.pathname, closeMobileSidebar])

  useEffect(() => {
    const mq = window.matchMedia('(min-width: 768px)')
    const onChange = () => {
      if (mq.matches) {
        closeMobileSidebar()
      }
    }
    mq.addEventListener('change', onChange)
    return () => mq.removeEventListener('change', onChange)
  }, [closeMobileSidebar])

  const showNavText = !sidebarCollapsed

  return (
    <aside
      className={`fixed left-0 top-0 z-50 flex h-screen min-h-0 w-64 flex-col border-r border-gray-200 bg-white transition-transform duration-300 ease-out dark:border-gray-800 dark:bg-gray-900 md:transition-[width] ${
        mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'
      } md:translate-x-0 ${sidebarCollapsed ? 'md:w-16' : 'md:w-64'}`}
    >
      <div className="flex h-16 w-full flex-none shrink-0 items-center gap-2 border-b border-gray-200 px-3 dark:border-gray-800 sm:px-4">
        <div
          className={`min-w-0 flex-1 items-center gap-2 ${
            sidebarCollapsed ? 'hidden max-md:flex' : 'flex'
          }`}
        >
          <Server className="h-7 w-7 shrink-0 text-primary-500" />
          <span className="truncate text-xl font-bold text-gray-900 dark:text-white">
            Panelsar
          </span>
        </div>
        <div className="ml-auto flex shrink-0 items-center gap-1">
          <button
            type="button"
            onClick={closeMobileSidebar}
            className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 md:hidden"
            aria-label={t('nav.close_menu')}
          >
            <X className="h-5 w-5" />
          </button>
          <button
            type="button"
            onClick={toggleSidebar}
            className="hidden rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 md:inline-flex"
            aria-label={t('nav.toggle_sidebar')}
          >
            {sidebarCollapsed ? (
              <ChevronRight className="h-5 w-5" />
            ) : (
              <ChevronLeft className="h-5 w-5" />
            )}
          </button>
        </div>
      </div>

      <nav className="min-h-0 flex-1 overflow-y-auto overflow-x-hidden py-4 px-2 overscroll-contain">
        <ul className="space-y-1">
          {visibleNav.map((item) => (
            <li key={item.path}>
              <NavLink
                to={item.path}
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400'
                      : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'
                  }`
                }
                title={t(item.label)}
              >
                <item.icon className="h-5 w-5 flex-shrink-0" />
                <span className={showNavText ? '' : 'max-md:inline md:hidden'}>
                  {t(item.label)}
                </span>
              </NavLink>
            </li>
          ))}
        </ul>

        {isAdmin && (
          <>
            <div className="my-4 border-t border-gray-200 dark:border-gray-800" />
            <p
              className={`mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-gray-400 ${
                showNavText ? '' : 'max-md:block md:hidden'
              }`}
            >
              Admin
            </p>
            <ul className="space-y-1">
              {adminItems.map((item) => (
                <li key={item.path}>
                  <NavLink
                    to={item.path}
                    className={({ isActive }) =>
                      `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                        isActive
                          ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400'
                          : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'
                      }`
                    }
                    title={t(item.label)}
                  >
                    <item.icon className="h-5 w-5 flex-shrink-0" />
                    <span className={showNavText ? '' : 'max-md:inline md:hidden'}>
                      {t(item.label)}
                    </span>
                  </NavLink>
                </li>
              ))}
            </ul>
          </>
        )}
      </nav>
    </aside>
  )
}
