import { Navigate } from 'react-router-dom'
import { useAuthStore } from '../store/authStore'
import MarketingLayout from '../components/marketing/MarketingLayout'

export default function PublicMarketingGate() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated)
  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />
  }
  return <MarketingLayout />
}
