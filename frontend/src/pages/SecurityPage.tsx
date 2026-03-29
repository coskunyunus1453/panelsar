import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import { Shield } from 'lucide-react'
import toast from 'react-hot-toast'
import { useAuthStore } from '../store/authStore'

export default function SecurityPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const isAdmin = useAuthStore((s) => s.user?.roles?.some((r) => r.name === 'admin'))

  const q = useQuery({
    queryKey: ['security-overview'],
    queryFn: async () => (await api.get('/security/overview')).data,
  })

  const fwM = useMutation({
    mutationFn: async (payload: {
      action: string
      protocol: string
      port?: string
      source?: string
    }) => api.post('/security/firewall', payload),
    onSuccess: () => {
      toast.success('Kural gönderildi')
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string }; status?: number } }
      if (ax.response?.status === 403) toast.error('Yalnızca yönetici')
      else toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  type Overview = {
    fail2ban?: { enabled?: boolean; jails?: string[] }
    firewall?: {
      backend?: string
      default_policy?: string
      recent_rules?: Record<string, unknown>[]
    }
    modsecurity?: { enabled?: boolean }
    clamav?: { last_scan?: unknown }
  }

  const overview = q.data?.overview as Overview | undefined
  const fwRules = overview?.firewall?.recent_rules ?? []

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Shield className="h-8 w-8 text-red-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.security')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('security.subtitle')}</p>
        </div>
      </div>

      <div className="card p-6 space-y-4">
        <h3 className="text-sm font-semibold">Engine özeti</h3>
        {q.isLoading ? (
          <p className="text-gray-500">{t('common.loading')}</p>
        ) : q.isError ? (
          <p className="text-sm text-amber-600">Özet alınamadı.</p>
        ) : (
          <>
            <div className="grid sm:grid-cols-2 gap-3 text-sm">
              <div className="rounded-lg border border-gray-100 dark:border-gray-800 p-3">
                <p className="text-gray-500 text-xs mb-1">Fail2ban</p>
                <p>
                  Durum:{' '}
                  <strong>{overview?.fail2ban?.enabled ? 'açık' : 'kapalı'}</strong>
                </p>
                {overview?.fail2ban?.jails && overview.fail2ban.jails.length > 0 && (
                  <p className="text-xs text-gray-500 mt-1 font-mono">
                    {overview.fail2ban.jails.join(', ')}
                  </p>
                )}
              </div>
              <div className="rounded-lg border border-gray-100 dark:border-gray-800 p-3">
                <p className="text-gray-500 text-xs mb-1">Güvenlik duvarı</p>
                <p>
                  <span className="font-mono">{overview?.firewall?.backend ?? '—'}</span>
                  {' · '}
                  <span className="font-mono">{overview?.firewall?.default_policy ?? '—'}</span>
                </p>
              </div>
              <div className="rounded-lg border border-gray-100 dark:border-gray-800 p-3">
                <p className="text-gray-500 text-xs mb-1">ModSecurity</p>
                <p>
                  <strong>{overview?.modsecurity?.enabled ? 'açık' : 'kapalı'}</strong>
                </p>
              </div>
              <div className="rounded-lg border border-gray-100 dark:border-gray-800 p-3">
                <p className="text-gray-500 text-xs mb-1">ClamAV</p>
                <p className="font-mono text-xs">
                  {overview?.clamav?.last_scan != null
                    ? String(overview.clamav.last_scan)
                    : 'Tarama kaydı yok'}
                </p>
              </div>
            </div>
            {fwRules.length > 0 && (
              <div>
                <p className="text-sm font-semibold mb-2">Son güvenlik duvarı kuralları</p>
                <div className="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                  <table className="w-full text-sm">
                    <thead className="bg-gray-50 dark:bg-gray-800/80">
                      <tr>
                        <th className="text-left px-3 py-2">Eylem</th>
                        <th className="text-left px-3 py-2">Protokol</th>
                        <th className="text-left px-3 py-2">Port</th>
                        <th className="text-left px-3 py-2">Kaynak</th>
                        <th className="text-left px-3 py-2">Zaman</th>
                      </tr>
                    </thead>
                    <tbody>
                      {fwRules.map((r, i) => (
                        <tr key={i} className="border-t border-gray-100 dark:border-gray-800">
                          <td className="px-3 py-2 font-mono">{String(r.action ?? '—')}</td>
                          <td className="px-3 py-2 font-mono">{String(r.protocol ?? '—')}</td>
                          <td className="px-3 py-2 font-mono">{String(r.port ?? '—')}</td>
                          <td className="px-3 py-2 font-mono text-xs">{String(r.source ?? '—')}</td>
                          <td className="px-3 py-2 text-xs text-gray-500">
                            {String(r.applied_at ?? '—')}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {isAdmin && (
        <div className="card p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Güvenlik duvarı kuralı (admin)
          </h3>
          <form
            className="grid sm:grid-cols-2 gap-4 max-w-xl"
            onSubmit={(ev) => {
              ev.preventDefault()
              const fd = new FormData(ev.currentTarget)
              fwM.mutate({
                action: String(fd.get('action')),
                protocol: String(fd.get('protocol')),
                port: String(fd.get('port') || '') || undefined,
                source: String(fd.get('source') || '') || undefined,
              })
            }}
          >
            <div>
              <label className="label">Eylem</label>
              <select name="action" className="input w-full">
                <option value="allow">allow</option>
                <option value="deny">deny</option>
              </select>
            </div>
            <div>
              <label className="label">Protokol</label>
              <select name="protocol" className="input w-full">
                <option value="tcp">tcp</option>
                <option value="udp">udp</option>
                <option value="icmp">icmp</option>
                <option value="any">any</option>
              </select>
            </div>
            <div>
              <label className="label">Port</label>
              <input name="port" className="input w-full" placeholder="443" />
            </div>
            <div>
              <label className="label">Kaynak</label>
              <input name="source" className="input w-full" placeholder="0.0.0.0/0" />
            </div>
            <div className="sm:col-span-2">
              <button type="submit" className="btn-primary" disabled={fwM.isPending}>
                Uygula
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  )
}
