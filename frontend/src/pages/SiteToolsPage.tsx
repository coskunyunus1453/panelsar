import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation } from '@tanstack/react-query'
import api from '../services/api'
import { Terminal } from 'lucide-react'
import toast from 'react-hot-toast'
import { useDomainsList } from '../hooks/useDomains'

export default function SiteToolsPage() {
  const { t } = useTranslation()
  const domainsQ = useDomainsList()
  const [domainId, setDomainId] = useState<number | ''>('')
  const [tool, setTool] = useState<'composer' | 'npm'>('composer')
  const [action, setAction] = useState('install')
  const [lastOut, setLastOut] = useState('')

  const actionOptions = useMemo(() => {
    if (tool === 'composer') {
      return [
        { v: 'install', l: 'install' },
        { v: 'update', l: 'update' },
        { v: 'dump-autoload', l: 'dump-autoload' },
      ]
    }
    return [
      { v: 'install', l: 'install' },
      { v: 'ci', l: 'ci' },
    ]
  }, [tool])

  const runM = useMutation({
    mutationFn: async () => {
      const { data } = await api.post(`/domains/${domainId}/tools`, { tool, action })
      return data as { output?: string; message?: string }
    },
    onSuccess: (data) => {
      setLastOut(data.output ?? data.message ?? '')
      toast.success(t('tools.completed'))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; output?: string } } }
      const d = ax.response?.data
      if (d?.output) setLastOut(d.output)
      toast.error(d?.message ?? String(err))
    },
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Terminal className="h-8 w-8 text-amber-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('tools.title')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('tools.subtitle')}</p>
        </div>
      </div>

      <div className="card p-4 flex flex-wrap gap-4 items-end">
        <div>
          <label className="label">{t('tools.domain')}</label>
          <select
            className="input min-w-[240px]"
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
        <div>
          <label className="label">{t('tools.tool')}</label>
          <select
            className="input min-w-[160px]"
            value={tool}
            onChange={(e) => {
              const nt = e.target.value as 'composer' | 'npm'
              setTool(nt)
              setAction(nt === 'composer' ? 'install' : 'install')
            }}
          >
            <option value="composer">composer</option>
            <option value="npm">npm</option>
          </select>
        </div>
        <div>
          <label className="label">{t('tools.action')}</label>
          <select
            className="input min-w-[160px]"
            value={action}
            onChange={(e) => setAction(e.target.value)}
          >
            {actionOptions.map((o) => (
              <option key={o.v} value={o.v}>
                {o.l}
              </option>
            ))}
          </select>
        </div>
        <button
          type="button"
          className="btn-primary"
          disabled={!domainId || runM.isPending}
          onClick={() => runM.mutate()}
        >
          {t('tools.run')}
        </button>
      </div>

      {lastOut && (
        <div className="card p-4">
          <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
            {t('tools.output')}
          </h2>
          <pre className="text-xs font-mono whitespace-pre-wrap break-all bg-gray-50 dark:bg-gray-950 p-3 rounded-lg max-h-96 overflow-auto">
            {lastOut}
          </pre>
        </div>
      )}
    </div>
  )
}
