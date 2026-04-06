import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '../../store/authStore'
import { useThemeStore } from '../../store/themeStore'
import { useUiModeStore } from '../../store/uiModeStore'
import { useNotificationsStore } from '../../store/notificationsStore'
import { authService } from '../../services/authService'
import { useQuery } from '@tanstack/react-query'
import api from '../../services/api'
import {
  Sun,
  Moon,
  LogOut,
  Bell,
  Globe,
  User,
  Menu,
  SlidersHorizontal,
  LayoutGrid,
} from 'lucide-react'
import clsx from 'clsx'
import { useEffect, useState } from 'react'

const languages = [
  { code: 'en', name: 'English', flag: '🇬🇧' },
  { code: 'tr', name: 'Türkçe', flag: '🇹🇷' },
  { code: 'de', name: 'Deutsch', flag: '🇩🇪' },
  { code: 'fr', name: 'Français', flag: '🇫🇷' },
  { code: 'es', name: 'Español', flag: '🇪🇸' },
]

export default function Header() {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const { user, logout: logoutStore } = useAuthStore()
  const { isDark, toggleTheme, openMobileSidebar } = useThemeStore()
  const { mode, setMode, advancedTipsSeen, markAdvancedTipsSeen } = useUiModeStore()
  const [showAdvancedTips, setShowAdvancedTips] = useState(false)
  const [showLangMenu, setShowLangMenu] = useState(false)
  const [showNotifMenu, setShowNotifMenu] = useState(false)
  const { items, markAllRead, clear, remove, mergeFromServer } = useNotificationsStore()
  const [levelFilter, setLevelFilter] = useState<'all' | 'error' | 'info' | 'success'>('all')
  const [unreadOnly, setUnreadOnly] = useState(false)
  const isSafeInternalPath = (p: string): boolean => /^\/[a-zA-Z0-9/_-]*$/.test(p)
  const feedQ = useQuery({
    queryKey: ['notifications-feed'],
    queryFn: async () => (await api.get('/notifications/feed')).data as { items: Array<{ id: string; title: string; message?: string; path?: string; level: 'info' | 'success' | 'error'; created_at?: string }> },
    refetchInterval: 10000,
  })
  useEffect(() => {
    if (feedQ.data?.items) {
      mergeFromServer(feedQ.data.items)
    }
  }, [feedQ.data, mergeFromServer])
  const unread = items.filter((i) => !i.read).length
  const visibleItems = items.filter((i) => {
    if (levelFilter !== 'all' && i.level !== levelFilter) return false
    if (unreadOnly && i.read) return false
    return true
  })

  const handleLogout = async () => {
    try {
      await authService.logout()
    } catch {
      // ignore
    }
    logoutStore()
    navigate('/login')
  }

  const changeLanguage = (code: string) => {
    i18n.changeLanguage(code)
    setShowLangMenu(false)
  }

  useEffect(() => {
    if (mode === 'advanced' && !advancedTipsSeen) {
      setShowAdvancedTips(true)
    }
  }, [mode, advancedTipsSeen])

  return (
    <header className="relative z-20 flex h-16 shrink-0 items-center justify-between gap-2 border-b border-gray-200 bg-white px-3 dark:border-gray-800 dark:bg-gray-900 sm:gap-3 sm:px-6">
      {/* Mobilde flex-1 + min-w-0 sol blok menüyü sıfır genişliğe sıkıştırabiliyordu; alan ayırıcı olarak bırakıldı */}
      <div className="hidden min-w-0 flex-1 md:block" aria-hidden />

      <div className="relative z-10 flex min-w-0 flex-1 flex-nowrap items-center justify-end gap-1 overflow-x-auto py-0 [scrollbar-width:none] md:flex-initial md:gap-3 md:overflow-visible [&::-webkit-scrollbar]:hidden">
        <button
          type="button"
          onClick={() => {
            const next = mode === 'easy' ? 'advanced' : 'easy'
            setMode(next)
            if (next === 'easy') {
              setShowAdvancedTips(false)
            }
          }}
          className={clsx(
            'relative z-0 shrink-0 rounded-xl border p-2 transition-colors',
            mode === 'easy'
              ? 'border-emerald-400/70 bg-emerald-50 text-emerald-700 shadow-sm shadow-emerald-500/10 hover:bg-emerald-100/90 dark:border-emerald-600/50 dark:bg-emerald-950/35 dark:text-emerald-300 dark:hover:bg-emerald-950/55'
              : 'border-blue-400/70 bg-blue-50 text-blue-700 shadow-sm shadow-blue-500/10 hover:bg-blue-100/90 dark:border-blue-600/50 dark:bg-blue-950/35 dark:text-blue-300 dark:hover:bg-blue-950/55',
          )}
          title={mode === 'easy' ? t('ui_mode.switch_to_advanced') : t('ui_mode.switch_to_easy')}
          aria-label={mode === 'easy' ? t('ui_mode.easy') : t('ui_mode.advanced')}
        >
          {mode === 'easy' ? (
            <LayoutGrid className="h-5 w-5 shrink-0" strokeWidth={2.2} />
          ) : (
            <SlidersHorizontal className="h-5 w-5 shrink-0" strokeWidth={2.2} />
          )}
        </button>
        {showAdvancedTips && (
          <>
            <div className="hidden md:block absolute right-4 top-16 z-50 w-80 rounded-xl border border-primary-200 dark:border-primary-900/40 bg-white dark:bg-gray-900 shadow-lg p-3">
              <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('ui_mode.advanced_tips_title')}</p>
              <ul className="mt-2 text-xs text-gray-600 dark:text-gray-300 list-disc pl-4 space-y-1">
                <li>{t('ui_mode.tip_1')}</li>
                <li>{t('ui_mode.tip_2')}</li>
                <li>{t('ui_mode.tip_3')}</li>
              </ul>
              <div className="mt-3 flex justify-end">
                <button
                  type="button"
                  className="btn-secondary py-1.5 text-xs"
                  onClick={() => {
                    markAdvancedTipsSeen()
                    setShowAdvancedTips(false)
                  }}
                >
                  {t('common.ok')}
                </button>
              </div>
            </div>

            <div className="md:hidden fixed inset-0 z-50 bg-black/40" onClick={() => { markAdvancedTipsSeen(); setShowAdvancedTips(false) }}>
              <div
                className="absolute inset-x-0 bottom-0 rounded-t-2xl border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-2xl"
                onClick={(e) => e.stopPropagation()}
              >
                <div className="mx-auto mb-3 h-1.5 w-12 rounded-full bg-gray-300 dark:bg-gray-700" />
                <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('ui_mode.advanced_tips_title')}</p>
                <ul className="mt-2 text-xs text-gray-600 dark:text-gray-300 list-disc pl-4 space-y-1">
                  <li>{t('ui_mode.tip_1')}</li>
                  <li>{t('ui_mode.tip_2')}</li>
                  <li>{t('ui_mode.tip_3')}</li>
                </ul>
                <button
                  type="button"
                  className="btn-primary mt-4 w-full"
                  onClick={() => {
                    markAdvancedTipsSeen()
                    setShowAdvancedTips(false)
                  }}
                >
                  {t('common.ok')}
                </button>
              </div>
            </div>
          </>
        )}

        <button
          onClick={toggleTheme}
          className="shrink-0 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400"
          title={isDark ? t('settings.light_mode') : t('settings.dark_mode')}
        >
          {isDark ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
        </button>

        <div className="relative shrink-0">
          <button
            onClick={() => setShowLangMenu(!showLangMenu)}
            className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400"
            title={t('settings.language')}
          >
            <Globe className="h-5 w-5" />
          </button>

          {showLangMenu && (
            <div className="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50">
              {languages.map((lang) => (
                <button
                  key={lang.code}
                  onClick={() => changeLanguage(lang.code)}
                  className={`w-full flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors ${
                    i18n.language === lang.code
                      ? 'text-primary-600 dark:text-primary-400 font-medium'
                      : 'text-gray-700 dark:text-gray-300'
                  }`}
                >
                  <span className="text-lg">{lang.flag}</span>
                  <span>{lang.name}</span>
                </button>
              ))}
            </div>
          )}
        </div>

        <div className="relative shrink-0">
          <button
            onClick={() => {
              const next = !showNotifMenu
              setShowNotifMenu(next)
              if (next) markAllRead()
            }}
            className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400 relative"
            title="Bildirimler"
          >
            <Bell className="h-5 w-5" />
            {unread > 0 && <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" />}
          </button>
          {showNotifMenu && (
            <>
              <div className="md:hidden fixed inset-0 z-40 bg-black/40" onClick={() => setShowNotifMenu(false)} />
              <div className="fixed md:absolute z-50 inset-x-3 bottom-3 md:inset-auto md:right-0 md:bottom-auto md:mt-2 w-auto md:w-80 max-h-[70vh] md:max-h-[420px] overflow-y-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl md:rounded-lg shadow-lg">
              <div className="sticky top-0 flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700 bg-white/95 dark:bg-gray-800/95 backdrop-blur">
                <span className="text-sm font-semibold text-gray-700 dark:text-gray-200">Bildirimler</span>
                <div className="flex items-center gap-2">
                  <select
                    className="text-xs rounded border border-gray-200 dark:border-gray-700 bg-transparent px-1 py-0.5"
                    value={levelFilter}
                    onChange={(e) => setLevelFilter(e.target.value as 'all' | 'error' | 'info' | 'success')}
                  >
                    <option value="all">Tümü</option>
                    <option value="error">Hata</option>
                    <option value="info">Bilgi</option>
                    <option value="success">Başarılı</option>
                  </select>
                  <label className="text-[10px] text-gray-500 inline-flex items-center gap-1">
                    <input type="checkbox" checked={unreadOnly} onChange={(e) => setUnreadOnly(e.target.checked)} />
                    Okunmamış
                  </label>
                  <button onClick={clear} className="text-xs text-gray-500 hover:text-red-600">Temizle</button>
                </div>
              </div>
              <div className="p-2 space-y-2">
                {visibleItems.length === 0 && (
                  <p className="px-2 py-3 text-xs text-gray-500">Yeni bildirim yok.</p>
                )}
                {visibleItems.map((n) => (
                  <div key={n.id} className="rounded-md border border-gray-200 dark:border-gray-700 p-2">
                    <div className="flex items-start gap-2">
                      <span className={`mt-1 inline-block h-2 w-2 rounded-full ${
                        n.level === 'error' ? 'bg-red-500' : n.level === 'success' ? 'bg-emerald-500' : 'bg-blue-500'
                      }`} />
                      <div className="min-w-0 flex-1">
                        <button
                          type="button"
                          className="text-xs font-medium text-gray-800 dark:text-gray-100 hover:underline text-left"
                          onClick={() => {
                            if (n.path && isSafeInternalPath(n.path)) {
                              navigate(n.path)
                              setShowNotifMenu(false)
                            }
                          }}
                        >
                          {n.title}
                        </button>
                        {n.message && <p className="text-xs text-gray-500 break-words">{n.message}</p>}
                        <p className="text-[10px] text-gray-400 mt-1">{new Date(n.createdAt).toLocaleString()}</p>
                      </div>
                      <button onClick={() => remove(n.id)} className="text-[10px] text-gray-400 hover:text-red-500">x</button>
                    </div>
                  </div>
                ))}
              </div>
              </div>
            </>
          )}
        </div>

        <div className="flex shrink-0 items-center gap-2 border-l border-gray-200 pl-2 dark:border-gray-700 sm:ml-2 sm:pl-4">
          <div className="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
            <User className="h-4 w-4 text-primary-600 dark:text-primary-400" />
          </div>
          <span className="text-sm font-medium text-gray-700 dark:text-gray-300 hidden sm:block">
            {user?.name}
          </span>
        </div>

        <button
          onClick={handleLogout}
          className="shrink-0 p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
          title={t('auth.logout')}
        >
          <LogOut className="h-5 w-5" />
        </button>

        <button
          type="button"
          onClick={openMobileSidebar}
          className="relative z-[60] -mr-0.5 shrink-0 rounded-lg p-2 text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800 md:hidden"
          aria-label={t('nav.open_menu')}
        >
          <Menu className="h-5 w-5" />
        </button>
      </div>
    </header>
  )
}
