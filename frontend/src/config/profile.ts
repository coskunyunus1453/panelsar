const appProfile = (((import.meta as any).env?.VITE_APP_PROFILE as string) || 'customer').toLowerCase()

export const isVendorProfile = appProfile === 'vendor'
export const isCustomerProfile = appProfile !== 'vendor'
export const effectiveLoginPath = isVendorProfile ? '/vendor/login' : '/login'
