import type { User } from '../types'

const VENDOR_OPERATOR_ROLES = ['admin', 'vendor_admin', 'vendor_support', 'vendor_finance', 'vendor_devops'] as const

/** Sunucu / engine istatistikleri ve sistem sayfası (admin veya vendor operatör). */
export function isServerAdminUI(user: User | null): boolean {
  if (!user?.roles?.length) return false
  const names = new Set(user.roles.map((r) => r.name))
  return VENDOR_OPERATOR_ROLES.some((n) => names.has(n))
}

/** Yalnızca barındırma süper admini (tenant sayıları, reboot gibi). */
export function isHostingSuperAdmin(user: User | null): boolean {
  return !!user?.roles?.some((r) => r.name === 'admin')
}

/** Sunucu ENFORCE_ADMIN_2FA=true iken henüz 2FA kurulmamış admin/vendor operatörleri Ayarlar’a yönlendirilir (varsayılan politika kapalı). */
export function mustEnrollTwoFactor(user: User | null, enforceAdmin2fa: boolean | null): boolean {
  if (!user || enforceAdmin2fa !== true) return false
  if (user.two_factor_enabled) return false
  return isServerAdminUI(user)
}
