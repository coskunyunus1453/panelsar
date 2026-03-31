import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
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
  const [manualDomainId, setManualDomainId] = useState<number | null>(null)
  const [manualCert, setManualCert] = useState('')
  const [manualKey, setManualKey] = useState('')

  const sslQ = useQuery({
    queryKey: ['ssl'],
    queryFn: async () => (await api.get('/ssl')).data as { certificates: Cert[] },
  })

  const domainsQ = useQuery({
    queryKey: ['domains', 'paginated'],
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

  const manualM = useMutation({
    mutationFn: async (vars: { id: number; certificate: string; private_key: string }) =>
      api.post(`/domains/${vars.id}/ssl/manual`, {
        certificate: vars.certificate,
        private_key: vars.private_key,
      }),
    onSuccess: () => {
      toast.success(t('ssl.manual_uploaded'))
      setManualDomainId(null)
      setManualCert('')
      setManualKey('')
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
                      <button
                        type="button"
                        className="btn-secondary text-xs py-1 px-2"
                        onClick={() => setManualDomainId(d.id)}
                      >
                        {t('ssl.manual_upload')}
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

      {manualDomainId !== null && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card w-full max-w-3xl bg-white p-6 dark:bg-gray-900">
            <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{t('ssl.manual_upload')}</h3>
            <div className="space-y-3">
              <div>
                <label className="label">Certificate (PEM)</label>
                <textarea
                  className="input min-h-[140px] w-full font-mono text-xs"
                  value={manualCert}
                  onChange={(e) => setManualCert(e.target.value)}
                  placeholder="-----BEGIN CERTIFICATE-----"
                />
              </div>
              <div>
                <label className="label">Private Key (PEM)</label>
                <textarea
                  className="input min-h-[140px] w-full font-mono text-xs"
                  value={manualKey}
                  onChange={(e) => setManualKey(e.target.value)}
                  placeholder="-----BEGIN PRIVATE KEY-----"
                />
              </div>
            </div>
            <div className="mt-4 flex justify-end gap-2">
              <button
                type="button"
                className="btn-secondary"
                onClick={() => {
                  setManualDomainId(null)
                  setManualCert('')
                  setManualKey('')
                }}
              >
                {t('common.cancel')}
              </button>
              <button
                type="button"
                className="btn-primary"
                disabled={manualM.isPending || !manualCert.trim() || !manualKey.trim()}
                onClick={() => {
                  if (manualDomainId === null) return
                  manualM.mutate({
                    id: manualDomainId,
                    certificate: manualCert.trim(),
                    private_key: manualKey.trim(),
                  })
                }}
              >
                {t('common.save')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
