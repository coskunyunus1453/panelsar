import { useEffect, useMemo, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useThemeStore } from '../../store/themeStore'
import { useUiModeStore } from '../../store/uiModeStore'
import { useAuthStore } from '../../store/authStore'
import { tokenHasAbility } from '../../lib/abilities'
import { useBranding } from '../../hooks/useBranding'
import { safeBrandingImageUrl } from '../../lib/urlSafety'
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
  X,
  TerminalSquare,
  Layers,
  Send,
  Tags,
  ServerCog,
  FileCode,
  BookMarked,
  ChevronDown,
  Rocket,
  Sparkles,
  Store,
} from 'lucide-react'

type NavLeaf = {
  path: string
  icon: typeof LayoutDashboard
  label: string
  ability: string | null
}

type NavGroup = {
  id: string
  title: string
  items: NavLeaf[]
}

export default function Sidebar() {
  const { t } = useTranslation()
  const {
    sidebarCollapsed,
    toggleSidebar,
    mobileSidebarOpen,
    closeMobileSidebar,
  } = useThemeStore()
  const location = useLocation()
  const user = useAuthStore((s) => s.user)
  const mode = useUiModeStore((s) => s.mode)
  const abilities = user?.abilities
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')
  const canWebserverSettings =
    tokenHasAbility(abilities, 'webserver:read') || tokenHasAbility(abilities, 'webserver:write')
  const canPhpSettings =
    tokenHasAbility(abilities, 'php:read') || tokenHasAbility(abilities, 'php:write')
  const { data: branding } = useBranding()
  const isAdminSection = location.pathname.startsWith('/admin')
  const headerLogoRaw =
    isAdminSection && branding?.logo_admin_url
      ? branding.logo_admin_url
      : branding?.logo_customer_url || branding?.logo_admin_url
  const headerLogo = safeBrandingImageUrl(headerLogoRaw)

  const navOk = (ability: string | null, path: string) => {
    if (path === '/reseller') {
      return (
        tokenHasAbility(abilities, 'reseller:users') ||
        tokenHasAbility(abilities, 'reseller:packages') ||
        tokenHasAbility(abilities, 'reseller:roles')
      )
    }
    return tokenHasAbility(abilities, ability)
  }

  const customerGroups: NavGroup[] = [
    {
      id: 'overview',
      title: 'nav.group_overview',
      items: [{ path: '/dashboard', icon: LayoutDashboard, label: 'nav.dashboard', ability: 'dashboard:read' }],
    },
    {
      id: 'hosting',
      title: 'nav.group_hosting',
      items: [
        { path: '/domains', icon: Globe, label: 'nav.domains', ability: 'domains:read' },
        { path: '/dns', icon: Network, label: 'nav.dns', ability: 'dns:read' },
        { path: '/databases', icon: Database, label: 'nav.databases', ability: 'databases:read' },
        { path: '/email', icon: Mail, label: 'nav.email', ability: 'email:read' },
        { path: '/files', icon: FolderOpen, label: 'nav.files', ability: 'files:read' },
        { path: '/ftp', icon: HardDrive, label: 'nav.ftp', ability: 'ftp:read' },
        { path: '/ssl', icon: Lock, label: 'nav.ssl', ability: 'ssl:read' },
      ],
    },
    {
      id: 'operations',
      title: 'nav.group_operations',
      items: [
        { path: '/backups', icon: Shield, label: 'nav.backups', ability: 'backups:read' },
        { path: '/cron', icon: Clock, label: 'nav.cron', ability: 'cron:read' },
        { path: '/monitoring', icon: Activity, label: 'nav.monitoring', ability: 'monitoring:read' },
        { path: '/security', icon: Shield, label: 'nav.security', ability: 'security:read' },
        { path: '/installer', icon: Download, label: 'nav.installer', ability: 'installer:read' },
        { path: '/site-tools', icon: Terminal, label: 'nav.site_tools', ability: 'tools:run' },
        { path: '/deploy', icon: Rocket, label: 'nav.deploy', ability: 'tools:run' },
        { path: '/plugins', icon: Store, label: 'nav.plugins_store', ability: 'dashboard:read' },
        { path: '/ai-advisor', icon: Sparkles, label: 'nav.ai_advisor', ability: 'dashboard:read' },
        { path: '/billing', icon: CreditCard, label: 'nav.billing', ability: 'billing:read' },
      ],
    },
    {
      id: 'account',
      title: 'nav.group_account',
      items: [
        { path: '/reseller', icon: Users, label: 'nav.reseller', ability: '__reseller__' },
        { path: '/settings', icon: Settings, label: 'nav.settings', ability: null },
      ],
    },
  ]

  const adminSubmenus = [
    {
      id: 'admin-server',
      title: 'nav.admin_server',
      icon: Gauge,
      items: [
        { path: '/admin/system', icon: Gauge, label: 'nav.system', allow: isAdmin },
        { path: '/admin/webserver', icon: ServerCog, label: 'nav.webserver_settings', allow: isAdmin || canWebserverSettings },
        { path: '/admin/php-settings', icon: FileCode, label: 'nav.php_settings', allow: isAdmin || canPhpSettings },
        { path: '/admin/stack', icon: Layers, label: 'nav.stack', allow: isAdmin },
        { path: '/admin/terminal', icon: TerminalSquare, label: 'nav.terminal', allow: isAdmin },
        { path: '/admin/mail-settings', icon: Send, label: 'nav.outbound_mail', allow: isAdmin },
      ],
    },
    {
      id: 'admin-access',
      title: 'nav.admin_access',
      icon: Users,
      items: [
        { path: '/admin/users', icon: Users, label: 'nav.users', allow: isAdmin },
        { path: '/admin/roles', icon: Tags, label: 'nav.roles', allow: isAdmin },
        { path: '/admin/packages', icon: Package, label: 'nav.packages', allow: isAdmin },
        { path: '/admin/cms', icon: BookMarked, label: 'nav.cms', allow: isAdmin },
        { path: '/admin/license', icon: KeyRound, label: 'nav.license', allow: isAdmin },
      ],
    },
  ]

  const [openAdminMenus, setOpenAdminMenus] = useState<Record<string, boolean>>({
    'admin-server': true,
    'admin-access': true,
  })

  const easyHiddenPaths = new Set([
    '/dns',
    '/ftp',
    '/monitoring',
    '/security',
    '/cron',
    '/site-tools',
    '/deploy',
    '/plugins',
    '/ai-advisor',
    '/billing',
    '/reseller',
  ])

  const visibleCustomerGroups = customerGroups
    .map((g) => ({
      ...g,
      items: g.items.filter((item) => {
        if (!navOk(item.ability, item.path)) return false
        if (mode === 'easy' && easyHiddenPaths.has(item.path)) return false
        return true
      }),
    }))
    .filter((g) => g.items.length > 0)

  const visibleAdminMenus = useMemo(
    () =>
      adminSubmenus
        .map((m) => ({ ...m, items: m.items.filter((i) => i.allow) }))
        .map((m) => ({ ...m, items: mode === 'easy' ? [] : m.items }))
        .filter((m) => m.items.length > 0),
    [isAdmin, canWebserverSettings, canPhpSettings, mode],
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
          {headerLogo ? (
            <img src={headerLogo} alt="" className="h-8 max-w-[140px] object-contain" />
          ) : (
            <Server className="h-7 w-7 shrink-0 text-primary-500" />
          )}
          {!headerLogo && (
            <span className="truncate text-xl font-bold text-gray-900 dark:text-white">
              Hostvim
            </span>
          )}
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
        {visibleCustomerGroups.map((group) => (
          <div key={group.id} className="mb-4">
            <p
              className={`mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-gray-400 ${
                showNavText ? '' : 'max-md:block md:hidden'
              }`}
            >
              {t(group.title)}
            </p>
            <ul className="space-y-1">
              {group.items.map((item) => (
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
          </div>
        ))}

        {visibleAdminMenus.length > 0 && (
          <>
            <div className="my-4 border-t border-gray-200 dark:border-gray-800" />
            <p
              className={`mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-gray-400 ${
                showNavText ? '' : 'max-md:block md:hidden'
              }`}
            >
              Admin
            </p>
            <div className="space-y-2">
              {visibleAdminMenus.map((menu) => {
                const isOpen = openAdminMenus[menu.id] ?? true
                return (
                  <div key={menu.id} className="rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <button
                      type="button"
                      onClick={() =>
                        setOpenAdminMenus((prev) => ({ ...prev, [menu.id]: !isOpen }))
                      }
                      className="w-full flex items-center justify-between gap-3 px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-50/80 dark:bg-gray-800/60 hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                      <span className="flex items-center gap-2">
                        <menu.icon className="h-4 w-4" />
                        <span className={showNavText ? '' : 'max-md:inline md:hidden'}>{t(menu.title)}</span>
                      </span>
                      <ChevronDown className={`h-4 w-4 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
                    </button>
                    {isOpen && (
                      <ul className="space-y-1 p-2">
                        {menu.items.map((item) => (
                          <li key={item.path}>
                            <NavLink
                              to={item.path}
                              className={({ isActive }) =>
                                `flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                                  isActive
                                    ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400'
                                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'
                                }`
                              }
                              title={t(item.label)}
                            >
                              <item.icon className="h-4 w-4 flex-shrink-0" />
                              <span className={showNavText ? '' : 'max-md:inline md:hidden'}>
                                {t(item.label)}
                              </span>
                            </NavLink>
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                )
              })}
            </div>
          </>
        )}
      </nav>
    </aside>
  )
}
