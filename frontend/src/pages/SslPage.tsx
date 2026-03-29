import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import { Lock, RefreshCw, ShieldOff } from 'lucide-react'
import toast from 'react-hot-toast'

type Cert = {
  id: number
  domain_id: number
  provider: string
  status: string
  expires_at: string | null
  domain?: { id: number; name: string }
}

type DomainOpt = { id: number; name: string }

export default function SslPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()

  const sslQ = useQuery({
    queryKey: ['ssl'],
    queryFn: async () => (await api.get('/ssl')).data as { certificates: Cert[] },
  })

  const domainsQ = useQuery({
    queryKey: ['domains'],
    queryFn: async () => (await api.get('/domains')).data,
  })

  const certs = sslQ.data?.certificates ?? []
  const domains: DomainOpt[] = domainsQ.data?.data ?? []

  const certByDomain = new Map<number, Cert>()
  for (const c of certs) {
    certByDomain.set(c.domain_id, c)
  }

  const issueM = useMutation({
    mutationFn: async (id: number) => api.post(`/domains/${id}/ssl/issue`),
    onSuccess: () => {
      toast.success(t('ssl.issued'))
      qc.invalidateQueries({ queryKey: ['ssl'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const renewM = useMutation({
    mutationFn: async (id: number) => api.post(`/domains/${id}/ssl/renew`),
    onSuccess: () => {
      toast.success(t('ssl.renewed'))
      qc.invalidateQueries({ queryKey: ['ssl'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const revokeM = useMutation({
    mutationFn: async (id: number) => api.post(`/domains/${id}/ssl/revoke`),
    onSuccess: () => {
      toast.success(t('ssl.revoked'))
      qc.invalidateQueries({ queryKey: ['ssl'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Lock className="h-8 w-8 text-green-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.ssl')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('ssl.subtitle')}</p>
        </div>
      </div>

      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-800/80">
            <tr>
              <th className="text-left px-4 py-2">{t('domains.name')}</th>
              <th className="text-left px-4 py-2">{t('common.status')}</th>
              <th className="text-left px-4 py-2">Bitiş</th>
              <th className="text-right px-4 py-2">{t('common.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {domainsQ.isLoading || sslQ.isLoading ? (
              <tr>
                <td colSpan={4} className="px-4 py-8 text-center text-gray-500">
                  {t('common.loading')}
                </td>
              </tr>
            ) : (
              domains.map((d) => {
                const c = certByDomain.get(d.id)
                return (
                  <tr key={d.id} className="border-t border-gray-100 dark:border-gray-800">
                    <td className="px-4 py-2 font-medium">{d.name}</td>
                    <td className="px-4 py-2">{c?.status ?? '—'}</td>
                    <td className="px-4 py-2 text-gray-500">
                      {c?.expires_at ? new Date(c.expires_at).toLocaleString() : '—'}
                    </td>
                    <td className="px-4 py-2 text-right space-x-1">
                      <button
                        type="button"
                        className="btn-secondary text-xs py-1 px-2"
                        disabled={issueM.isPending}
                        onClick={() => issueM.mutate(d.id)}
                      >
                        Issue
                      </button>
                      <button
                        type="button"
                        className="btn-secondary text-xs py-1 px-2"
                        disabled={!c || renewM.isPending}
                        onClick={() => renewM.mutate(d.id)}
                      >
                        <RefreshCw className="h-3 w-3 inline" /> Renew
                      </button>
                      <button
                        type="button"
                        className="btn-secondary text-xs py-1 px-2 text-red-600"
                        disabled={!c || revokeM.isPending}
                        onClick={() => {
                          if (window.confirm(t('common.confirm_delete'))) revokeM.mutate(d.id)
                        }}
                      >
                        <ShieldOff className="h-3 w-3 inline" /> Revoke
                      </button>
                    </td>
                  </tr>
                )
              })
            )}
          </tbody>
        </table>
        {!domainsQ.isLoading && domains.length === 0 && (
          <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
        )}
      </div>
    </div>
  )
}
