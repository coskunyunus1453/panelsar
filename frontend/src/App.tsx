import { Routes, Route, Navigate } from 'react-router-dom'
import { useEffect } from 'react'
import { useAuthStore } from './store/authStore'
import { useThemeStore } from './store/themeStore'
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

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated)
  if (!isAuthenticated) return <Navigate to="/login" replace />
  return <>{children}</>
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
        <Route path="cron" element={<CronPage />} />
        <Route path="monitoring" element={<MonitoringPage />} />
        <Route path="security" element={<SecurityPage />} />
        <Route path="installer" element={<InstallerPage />} />
        <Route path="site-tools" element={<SiteToolsPage />} />
        <Route path="deploy" element={<DeployPage />} />
        <Route path="billing" element={<BillingPage />} />
        <Route path="reseller" element={<ResellerPage />} />
        <Route path="ai-advisor" element={<AiAdvisorPage />} />
        <Route path="plugins" element={<PluginsStorePage />} />
        <Route path="admin/users" element={<AdminUsersPage />} />
        <Route path="admin/roles" element={<AdminRolesPage />} />
        <Route path="admin/packages" element={<AdminPackagesPage />} />
        <Route path="admin/system" element={<AdminSystemPage />} />
        <Route path="admin/license" element={<AdminLicensePage />} />
        <Route path="admin/terminal" element={<TerminalPage />} />
        <Route path="admin/stack" element={<AdminStackPage />} />
        <Route path="admin/mail-settings" element={<AdminMailSettingsPage />} />
        <Route path="admin/webserver" element={<AdminWebServerSettingsPage />} />
        <Route path="admin/php-settings" element={<AdminPhpSettingsPage />} />
        <Route path="settings" element={<SettingsPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
