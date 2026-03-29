export interface User {
  id: number
  name: string
  email: string
  locale: string
  status: 'active' | 'suspended' | 'pending' | 'disabled'
  roles: Role[]
  hosting_package?: HostingPackage
  /** Admin panelden paket atanırsa true; otomatik fatura senkronu bu kullanıcıda paketi güncellemez. */
  hosting_package_manual_override?: boolean
  created_at: string
}

export interface Role {
  id: number
  name: string
}

export interface Domain {
  id: number
  user_id: number
  name: string
  document_root: string
  php_version: string
  ssl_enabled: boolean
  ssl_expiry: string | null
  status: 'active' | 'pending' | 'suspended' | 'disabled'
  is_primary: boolean
  server_type: 'nginx' | 'apache'
  ssl_certificate?: SslCertificate
  created_at: string
}

export interface Database {
  id: number
  user_id: number
  domain_id: number | null
  name: string
  type: 'mysql' | 'postgresql'
  username: string
  host: string
  port: number
  size_mb: number
  status: string
  created_at: string
}

export interface HostingPackage {
  id: number
  name: string
  slug: string
  description: string
  disk_space_mb: number
  bandwidth_mb: number
  max_domains: number
  max_databases: number
  max_email_accounts: number
  max_ftp_accounts: number
  max_cron_jobs: number
  php_versions: string[]
  ssl_enabled: boolean
  backup_enabled: boolean
  price_monthly: number
  price_yearly: number
  currency: string
  is_active: boolean
}

export interface SslCertificate {
  id: number
  domain_id: number
  provider: string
  type: string
  status: string
  issued_at: string
  expires_at: string
  auto_renew: boolean
}

export interface EmailAccount {
  id: number
  user_id: number
  domain_id: number
  email: string
  quota_mb: number
  used_mb: number
  status: string
}

export interface Subscription {
  id: number
  user_id: number
  hosting_package_id: number
  stripe_subscription_id?: string | null
  payment_provider?: string
  external_subscription_id?: string | null
  status: string
  billing_cycle: string
  amount: number
  currency: string
  starts_at: string
  ends_at: string | null
}

export interface SystemStats {
  cpu_usage?: number
  memory_total?: number
  memory_used?: number
  memory_percent?: number
  disk_total?: number
  disk_used?: number
  disk_percent?: number
  uptime?: number
  hostname?: string
  os?: string
}

export interface ServiceInfo {
  name: string
  status: 'running' | 'stopped' | 'error' | 'unknown'
  enabled: boolean
}

export interface DashboardData {
  domains_count: number
  databases_count: number
  email_accounts_count: number
  active_subscriptions: number
  total_users?: number
  total_domains?: number
  system_stats?: SystemStats
}

export interface ApiResponse<T> {
  data: T
  message?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}
