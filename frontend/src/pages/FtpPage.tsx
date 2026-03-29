import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { HardDrive, Plus, Trash2 } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import { useDomainsList } from '../hooks/useDomains'

type FtpRow = {
  id: number
  username: string
  home_directory: string
  quota_mb: number
  status: string
}

type EngineFtpRow = {
  username?: string
  home_directory?: string
  quota_mb?: number
  password?: string
}

export default function FtpPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const domainsQ = useDomainsList()
  const [domainId, setDomainId] = useState<number | ''>('')
  const [showAdd, setShowAdd] = useState(false)

  const q = useQuery({
    queryKey: ['ftp', domainId],
    enabled: domainId !== '',
    queryFn: async () => (await api.get(`/domains/${domainId}/ftp`)).data,
  })

  const createM = useMutation({
    mutationFn: async (payload: {
      username: string
      home_directory: string
      quota_mb?: number
    }) => api.post(`/domains/${domainId}/ftp`, payload),
    onSuccess: (res) => {
      const plain = (res.data as { password_plain?: string })?.password_plain
      toast.success(
        plain ? `${t('ftp.created')} — ${t('databases.password_once')}: ${plain}` : t('ftp.created'),
        { duration: plain ? 22_000 : 4000 }
      )
      qc.invalidateQueries({ queryKey: ['ftp', domainId] })
      setShowAdd(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const deleteM = useMutation({
    mutationFn: async (id: number) => api.delete(`/ftp/${id}`),
    onSuccess: () => {
      toast.success(t('ftp.deleted'))
      qc.invalidateQueries({ queryKey: ['ftp', domainId] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const local: FtpRow[] = q.data?.local ?? []
  const engineList: EngineFtpRow[] = Array.isArray(q.data?.engine) ? q.data.engine : []

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <HardDrive className="h-8 w-8 text-slate-500" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.ftp')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm">{t('ftp.subtitle')}</p>
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
            <h2 className="text-lg font-semibold">FTP hesabı</h2>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                createM.mutate({
                  username: String(fd.get('username') || '').trim(),
                  home_directory: String(fd.get('home_directory') || 'public_html'),
                  quota_mb: fd.get('quota_mb') ? Number(fd.get('quota_mb')) : -1,
                })
              }}
            >
              <div>
                <label className="label">Kullanıcı adı</label>
                <input name="username" className="input w-full" required />
              </div>
              <div>
                <label className="label">Ev dizini</label>
                <input name="home_directory" className="input w-full" defaultValue="public_html" />
              </div>
              <div>
                <label className="label">Kota MB (-1 sınırsız)</label>
                <input name="quota_mb" type="number" className="input w-full" defaultValue={-1} />
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
        <h3 className="px-4 py-3 text-sm font-semibold border-b border-gray-100 dark:border-gray-800">
          Panel kayıtları
        </h3>
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-800/80">
            <tr>
              <th className="text-left px-4 py-2">Kullanıcı</th>
              <th className="text-left px-4 py-2">Dizin</th>
              <th className="text-left px-4 py-2">Kota</th>
              <th className="text-right px-4 py-2">{t('common.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {local.map((a) => (
              <tr key={a.id} className="border-t border-gray-100 dark:border-gray-800">
                <td className="px-4 py-2 font-mono">{a.username}</td>
                <td className="px-4 py-2 font-mono">{a.home_directory}</td>
                <td className="px-4 py-2">{a.quota_mb}</td>
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
        {domainId && !q.isLoading && local.length === 0 && (
          <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
        )}
      </div>

      {domainId && engineList.length > 0 && (
        <div className="card overflow-hidden">
          <h3 className="px-4 py-3 text-sm font-semibold border-b border-gray-100 dark:border-gray-800">
            Engine (senkron)
          </h3>
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-800/80">
              <tr>
                <th className="text-left px-4 py-2">Kullanıcı</th>
                <th className="text-left px-4 py-2">Dizin</th>
                <th className="text-left px-4 py-2">Kota</th>
              </tr>
            </thead>
            <tbody>
              {engineList.map((a, i) => (
                <tr key={`${a.username ?? i}`} className="border-t border-gray-100 dark:border-gray-800">
                  <td className="px-4 py-2 font-mono">{a.username ?? '—'}</td>
                  <td className="px-4 py-2 font-mono">{a.home_directory ?? '—'}</td>
                  <td className="px-4 py-2">{a.quota_mb ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
