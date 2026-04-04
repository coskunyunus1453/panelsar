import { NavLink, Outlet } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { FileText, LayoutTemplate, BookOpen, Newspaper } from 'lucide-react'

const tabs = [
  { to: '/admin/cms/landing', labelKey: 'cms_admin.tab_landing', icon: LayoutTemplate },
  { to: '/admin/cms/install', labelKey: 'cms_admin.tab_install', icon: FileText },
  { to: '/admin/cms/docs', labelKey: 'cms_admin.tab_docs', icon: BookOpen },
  { to: '/admin/cms/blog', labelKey: 'cms_admin.tab_blog', icon: Newspaper },
] as const

export default function AdminCmsLayout() {
  const { t } = useTranslation()

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('cms_admin.title')}</h1>
        <p className="text-sm text-gray-500 dark:text-gray-400">{t('cms_admin.subtitle')}</p>
      </div>

      <div className="flex flex-wrap gap-2 border-b border-gray-200 pb-2 dark:border-gray-800">
        {tabs.map(({ to, labelKey, icon: Icon }) => (
          <NavLink
            key={to}
            to={to}
            className={({ isActive }) =>
              `inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                isActive
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                  : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'
              }`
            }
          >
            <Icon className="h-4 w-4" />
            {t(labelKey)}
          </NavLink>
        ))}
      </div>

      <Outlet />
    </div>
  )
}
