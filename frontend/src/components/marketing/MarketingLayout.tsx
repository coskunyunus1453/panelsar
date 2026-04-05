import { Link, NavLink, Outlet } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useBranding } from '../../hooks/useBranding'
import { safeBrandingImageUrl } from '../../lib/urlSafety'
import { Server } from 'lucide-react'

export default function MarketingLayout() {
  const { t } = useTranslation()
  const { data: branding } = useBranding()
  const marketingLogoUrl = safeBrandingImageUrl(branding?.logo_customer_url)

  const navClass = ({ isActive }: { isActive: boolean }) =>
    `text-sm font-medium transition-colors ${
      isActive ? 'text-primary-600 dark:text-primary-400' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
    }`

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-950">
      <header className="border-b border-gray-200 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4">
          <Link to="/" className="flex items-center gap-2">
            {marketingLogoUrl ? (
              <img src={marketingLogoUrl} alt="" className="h-9 max-w-[160px] object-contain" />
            ) : (
              <>
                <Server className="h-8 w-8 text-primary-600" />
                <span className="text-lg font-bold text-gray-900 dark:text-white">{t('app.name')}</span>
              </>
            )}
          </Link>
          <nav className="flex flex-wrap items-center gap-4">
            <NavLink to="/" end className={navClass}>
              {t('marketing.nav_home')}
            </NavLink>
            <NavLink to="/pricing" className={navClass}>
              {t('marketing.nav_pricing')}
            </NavLink>
            <NavLink to="/install" className={navClass}>
              {t('marketing.nav_install')}
            </NavLink>
            <NavLink to="/docs" className={navClass}>
              {t('marketing.nav_docs')}
            </NavLink>
            <NavLink to="/blog" className={navClass}>
              {t('marketing.nav_blog')}
            </NavLink>
            <Link
              to="/login"
              className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"
            >
              {t('marketing.nav_login')}
            </Link>
          </nav>
        </div>
      </header>
      <main className="mx-auto max-w-6xl px-4 py-10">
        <Outlet />
      </main>
      <footer className="border-t border-gray-200 py-8 text-center text-xs text-gray-500 dark:border-gray-800 dark:text-gray-500">
        {t('app.name')} — {t('app.tagline')}
      </footer>
    </div>
  )
}
