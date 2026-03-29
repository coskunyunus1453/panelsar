import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Mail, Plus, Trash2 } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import { useDomainsList } from '../hooks/useDomains'

type MailRow = {
  id: number
  email: string
  quota_mb: number
  status: string
}

type EngineMailbox = {
  email?: string
  quota_mb?: number
  password?: string
}

export default function EmailPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const domainsQ = useDomainsList()
  const [domainId, setDomainId] = useState<number | ''>('')
  const [showAdd, setShowAdd] = useState(false)

  const q = useQuery({
    queryKey: ['email', domainId],
    enabled: domainId !== '',
    queryFn: async () => (await api.get(`/domains/${domainId}/email`)).data,
  })

  const createM = useMutation({
    mutationFn: async (payload: { local_part: string; quota_mb?: number }) =>
      api.post(`/domains/${domainId}/email`, payload),
    onSuccess: (res) => {
      const plain = (res.data as { password_plain?: string })?.password_plain
      toast.success(
        plain
          ? `${t('email.created')} — ${t('databases.password_once')}: ${plain}`
          : t('email.created'),
        { duration: plain ? 22_000 : 4000 }
      )
      qc.invalidateQueries({ queryKey: ['email', domainId] })
      setShowAdd(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const deleteM = useMutation({
    mutationFn: async (id: number) => api.delete(`/email/${id}`),
    onSuccess: () => {
      toast.success(t('email.deleted'))
      qc.invalidateQueries({ queryKey: ['email', domainId] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const accounts: MailRow[] = q.data?.accounts ?? []
  const mailOv = q.data?.mail as
    | { mail_enabled?: boolean; mailboxes?: EngineMailbox[]; spf?: string; dmarc?: string }
    | undefined
  const engineBoxes: EngineMailbox[] = Array.isArray(mailOv?.mailboxes) ? mailOv.mailboxes : []

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Mail className="h-8 w-8 text-purple-500" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.email')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm">{t('email.subtitle')}</p>
          </div>
        </div>
        <button
          type="button"
          className="btn-primary flex items-center gap-2"
          disabled={!domainId}
          onClick={() => setShowAdd(true)}
        >
          <Plus className="h-4 w-4" />
          {t('common.create')}
        </button>
      </div>

      <div className="card p-4">
        <label className="label">{t('domains.name')}</label>
        <select
          className="input max-w-md"
          value={domainId}
          onChange={(e) => setDomainId(e.target.value ? Number(e.target.value) : '')}
        >
          <option value="">{t('common.select')}</option>
          {(domainsQ.data ?? []).map((d) => (
            <option key={d.id} value={d.id}>
              {d.name}
            </option>
          ))}
        </select>
      </div>

      {showAdd && domainId && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-md w-full p-6 space-y-4 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold">Posta kutusu</h2>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                createM.mutate({
                  local_part: String(fd.get('local_part') || '').trim(),
                  quota_mb: fd.get('quota_mb') ? Number(fd.get('quota_mb')) : 500,
                })
              }}
            >
              <div>
                <label className="label">Yerel kısım</label>
                <input name="local_part" className="input w-full" required placeholder="iletisim" />
              </div>
              <div>
                <label className="label">Kota (MB)</label>
                <input name="quota_mb" type="number" className="input w-full" defaultValue={500} />
              </div>
              <div className="flex justify-end gap-2">
                <button type="button" className="btn-secondary" onClick={() => setShowAdd(false)}>
                  {t('common.cancel')}
                </button>
                <button type="submit" className="btn-primary" disabled={createM.isPending}>
                  {t('common.create')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-800/80">
            <tr>
              <th className="text-left px-4 py-2">E-posta</th>
              <th className="text-left px-4 py-2">Kota MB</th>
              <th className="text-left px-4 py-2">{t('common.status')}</th>
              <th className="text-right px-4 py-2">{t('common.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {accounts.map((a) => (
              <tr key={a.id} className="border-t border-gray-100 dark:border-gray-800">
                <td className="px-4 py-2 font-mono">{a.email}</td>
                <td className="px-4 py-2">{a.quota_mb}</td>
                <td className="px-4 py-2">{a.status}</td>
                <td className="px-4 py-2 text-right">
                  <button
                    type="button"
                    className="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20"
                    onClick={() => {
                      if (window.confirm(t('common.confirm_delete'))) deleteM.mutate(a.id)
                    }}
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {!domainId && <p className="p-6 text-center text-gray-500">{t('common.select')}</p>}
        {domainId && !q.isLoading && accounts.length === 0 && (
          <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
        )}
      </div>

      {domainId && mailOv != null && (
        <div className="card p-4 space-y-3 text-sm">
          <div className="flex flex-wrap gap-4 text-gray-600 dark:text-gray-400">
            <span>
              Posta: <strong>{mailOv.mail_enabled ? 'açık' : 'kapalı'}</strong>
            </span>
            {mailOv.spf != null && mailOv.spf !== '' && (
              <span className="font-mono text-xs">SPF: {mailOv.spf}</span>
            )}
            {mailOv.dmarc != null && mailOv.dmarc !== '' && (
              <span className="font-mono text-xs">DMARC: {mailOv.dmarc}</span>
            )}
          </div>
          {engineBoxes.length > 0 && (
            <>
              <p className="font-semibold text-gray-900 dark:text-white">Engine (senkron) kutular</p>
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-800/80">
                  <tr>
                    <th className="text-left px-3 py-2">E-posta</th>
                    <th className="text-left px-3 py-2">Kota MB</th>
                  </tr>
                </thead>
                <tbody>
                  {engineBoxes.map((m, i) => (
                    <tr key={`${m.email ?? i}`} className="border-t border-gray-100 dark:border-gray-800">
                      <td className="px-3 py-2 font-mono">{m.email ?? '—'}</td>
                      <td className="px-3 py-2">{m.quota_mb ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </>
          )}
        </div>
      )}
    </div>
  )
}
