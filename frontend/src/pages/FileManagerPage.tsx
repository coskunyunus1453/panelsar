import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import { FolderOpen, RefreshCw, Upload } from 'lucide-react'
import toast from 'react-hot-toast'

export default function FileManagerPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [domainId, setDomainId] = useState<number | ''>('')
  const [path, setPath] = useState('')

  const domainsQ = useQuery({
    queryKey: ['domains'],
    queryFn: async () => (await api.get('/domains')).data,
  })

  const filesQ = useQuery({
    queryKey: ['files', domainId, path],
    enabled: domainId !== '',
    queryFn: async () =>
      (await api.get(`/domains/${domainId}/files`, { params: { path } })).data,
  })

  const mkdirM = useMutation({
    mutationFn: async (name: string) => {
      await api.post(`/domains/${domainId}/files/mkdir`, { path: path ? `${path}/${name}`.replace(/^\//, '') : name })
    },
    onSuccess: () => {
      toast.success(t('files.folder_created'))
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
    },
  })

  const uploadM = useMutation({
    mutationFn: async (file: File) => {
      const fd = new FormData()
      fd.append('file', file)
      fd.append('path', path)
      await api.post(`/domains/${domainId}/files/upload`, fd)
    },
    onSuccess: () => {
      toast.success(t('files.upload_ok'))
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { error?: string; message?: string } } }
      toast.error(ax.response?.data?.error ?? ax.response?.data?.message ?? t('files.upload_err'))
    },
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <FolderOpen className="h-8 w-8 text-primary-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.files')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('files.subtitle')}</p>
        </div>
      </div>

      <div className="card p-4 flex flex-wrap gap-4 items-end">
        <div>
          <label className="label">{t('domains.name')}</label>
          <select
            className="input min-w-[200px]"
            value={domainId}
            onChange={(e) => setDomainId(e.target.value ? Number(e.target.value) : '')}
          >
            <option value="">{t('common.select')}</option>
            {(domainsQ.data?.data ?? []).map((d: { id: number; name: string }) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>
        </div>
        <div className="flex-1 min-w-[200px]">
          <label className="label">{t('files.path')}</label>
          <input className="input" value={path} onChange={(e) => setPath(e.target.value)} placeholder="public_html" />
        </div>
        <button
          type="button"
          className="btn-secondary flex items-center gap-2"
          onClick={() => {
            void domainsQ.refetch()
            void filesQ.refetch()
          }}
        >
          <RefreshCw className="h-4 w-4" />
          {t('common.refresh')}
        </button>
        <label className="btn-primary flex items-center gap-2 cursor-pointer">
          <Upload className="h-4 w-4" />
          {t('files.upload')}
          <input
            type="file"
            className="hidden"
            disabled={!domainId || uploadM.isPending}
            onChange={(e) => {
              const f = e.target.files?.[0]
              e.target.value = ''
              if (f && domainId) uploadM.mutate(f)
            }}
          />
        </label>
      </div>

      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-800/80">
            <tr>
              <th className="text-left px-4 py-2">{t('files.name')}</th>
              <th className="text-left px-4 py-2">{t('files.type')}</th>
              <th className="text-left px-4 py-2">{t('files.size')}</th>
            </tr>
          </thead>
          <tbody>
            {(filesQ.data?.entries ?? []).map((e: { name: string; is_dir: boolean; size: number }) => (
              <tr key={e.name} className="border-t border-gray-100 dark:border-gray-800">
                <td className="px-4 py-2 font-mono text-gray-900 dark:text-gray-100">{e.name}</td>
                <td className="px-4 py-2">{e.is_dir ? t('files.directory') : t('files.file')}</td>
                <td className="px-4 py-2">{e.size}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {domainId && !filesQ.isLoading && (filesQ.data?.entries ?? []).length === 0 && (
          <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
        )}
      </div>

      <div className="card p-4">
        <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">{t('files.quick_mkdir')}</p>
        <form
          className="flex gap-2"
          onSubmit={(ev) => {
            ev.preventDefault()
            const fd = new FormData(ev.currentTarget)
            const name = String(fd.get('folder') || '')
            if (name && domainId) mkdirM.mutate(name)
            ev.currentTarget.reset()
          }}
        >
          <input name="folder" className="input flex-1" placeholder="new-folder" />
          <button type="submit" className="btn-primary" disabled={!domainId || mkdirM.isPending}>
            {t('files.mkdir')}
          </button>
        </form>
      </div>
    </div>
  )
}
