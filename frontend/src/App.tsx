import { Routes, Route, Navigate } from 'react-router-dom'
import { lazy, Suspense, useEffect } from 'react'
import { useAuthStore } from './store/authStore'
import { useThemeStore } from './store/themeStore'
import { useUiModeStore } from './store/uiModeStore'
import Layout from './components/layout/Layout'
import LoginPage from './pages/LoginPage'
import DashboardPage from './pages/DashboardPage'
import DomainsPage from './pages/DomainsPage'
import DatabasesPage from './pages/DatabasesPage'
import DnsPage from './pages/DnsPage'
import FileManagerPage from './pages/FileManagerPage'
import FtpPage from './pages/FtpPage'
import EmailPage from './pages/EmailPage'
import SslPage from './pages/SslPage'
import BackupsPage from './pages/BackupsPage'
import CronPage from './pages/CronPage'
import MonitoringPage from './pages/MonitoringPage'
import SecurityPage from './pages/SecurityPage'
import InstallerPage from './pages/InstallerPage'
import SiteToolsPage from './pages/SiteToolsPage'
import DeployPage from './pages/DeployPage'
import BillingPage from './pages/BillingPage'
import SettingsPage from './pages/SettingsPage'
import AdminUsersPage from './pages/AdminUsersPage'
import AdminPackagesPage from './pages/AdminPackagesPage'
import AdminSystemPage from './pages/AdminSystemPage'
import AdminLicensePage from './pages/AdminLicensePage'
import TerminalPage from './pages/TerminalPage'
import AdminStackPage from './pages/AdminStackPage'
import AdminMailSettingsPage from './pages/AdminMailSettingsPage'
import AdminRolesPage from './pages/AdminRolesPage'
import AdminWebServerSettingsPage from './pages/AdminWebServerSettingsPage'
import AdminPhpSettingsPage from './pages/AdminPhpSettingsPage'
import ResellerPage from './pages/ResellerPage'
import AiAdvisorPage from './pages/AiAdvisorPage'
import PluginsStorePage from './pages/PluginsStorePage'
import { isVendorProfile } from './config/profile'

const AdminVendorControlPage = lazy(() => import('./pages/AdminVendorControlPage'))

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated)
  if (!isAuthenticated) return <Navigate to="/login" replace />
  return <>{children}</>
}

function AdvancedRoute({ children }: { children: React.ReactNode }) {
  const { mode, setMode } = useUiModeStore()
  if (mode === 'advanced') return <>{children}</>
  return (
    <div className="max-w-2xl rounded-xl border border-amber-200 dark:border-amber-900/40 bg-amber-50/80 dark:bg-amber-950/20 p-5">
      <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Gelişmiş mod gerekli</h2>
      <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
        Bu sayfa teknik/ileri ayarlar içerir. Kolay modda gizlenir.
      </p>
      <button className="btn-primary mt-3" onClick={() => setMode('advanced')}>
        Gelişmiş Moda Geç
      </button>
    </div>
  )
}

export default function App() {
  const isDark = useThemeStore((s) => s.isDark)

  useEffect(() => {
    if (isDark) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
  }, [isDark])

  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      {isVendorProfile && <Route path="/vendor/login" element={<LoginPage />} />}
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <Layout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route path="dashboard" element={<DashboardPage />} />
        <Route path="domains" element={<DomainsPage />} />
        <Route path="dns" element={<DnsPage />} />
        <Route path="databases" element={<DatabasesPage />} />
        <Route path="email" element={<EmailPage />} />
        <Route path="files" element={<FileManagerPage />} />
        <Route path="ftp" element={<FtpPage />} />
        <Route path="ssl" element={<SslPage />} />
        <Route path="backups" element={<BackupsPage />} />
        <Route path="cron" element={<AdvancedRoute><CronPage /></AdvancedRoute>} />
        <Route path="monitoring" element={<AdvancedRoute><MonitoringPage /></AdvancedRoute>} />
        <Route path="security" element={<AdvancedRoute><SecurityPage /></AdvancedRoute>} />
        <Route path="installer" element={<InstallerPage />} />
        <Route path="site-tools" element={<AdvancedRoute><SiteToolsPage /></AdvancedRoute>} />
        <Route path="deploy" element={<AdvancedRoute><DeployPage /></AdvancedRoute>} />
        <Route path="billing" element={<AdvancedRoute><BillingPage /></AdvancedRoute>} />
        <Route path="reseller" element={<AdvancedRoute><ResellerPage /></AdvancedRoute>} />
        <Route path="ai-advisor" element={<AdvancedRoute><AiAdvisorPage /></AdvancedRoute>} />
        <Route path="plugins" element={<AdvancedRoute><PluginsStorePage /></AdvancedRoute>} />
        <Route path="admin/users" element={<AdvancedRoute><AdminUsersPage /></AdvancedRoute>} />
        <Route path="admin/roles" element={<AdvancedRoute><AdminRolesPage /></AdvancedRoute>} />
        <Route path="admin/packages" element={<AdvancedRoute><AdminPackagesPage /></AdvancedRoute>} />
        <Route path="admin/system" element={<AdvancedRoute><AdminSystemPage /></AdvancedRoute>} />
        <Route path="admin/license" element={<AdvancedRoute><AdminLicensePage /></AdvancedRoute>} />
        <Route path="admin/terminal" element={<AdvancedRoute><TerminalPage /></AdvancedRoute>} />
        <Route path="admin/stack" element={<AdvancedRoute><AdminStackPage /></AdvancedRoute>} />
        <Route path="admin/mail-settings" element={<AdvancedRoute><AdminMailSettingsPage /></AdvancedRoute>} />
        <Route path="admin/webserver" element={<AdvancedRoute><AdminWebServerSettingsPage /></AdvancedRoute>} />
        <Route path="admin/php-settings" element={<AdvancedRoute><AdminPhpSettingsPage /></AdvancedRoute>} />
        {isVendorProfile && (
          <Route
            path="admin/vendor-control"
            element={<AdvancedRoute><Suspense fallback={null}><AdminVendorControlPage /></Suspense></AdvancedRoute>}
          />
        )}
        {isVendorProfile && (
          <Route
            path="vendor/control"
            element={<AdvancedRoute><Suspense fallback={null}><AdminVendorControlPage /></Suspense></AdvancedRoute>}
          />
        )}
        <Route path="settings" element={<SettingsPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
