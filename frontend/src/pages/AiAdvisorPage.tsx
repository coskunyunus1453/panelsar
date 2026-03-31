import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation } from '@tanstack/react-query'
import { Sparkles } from 'lucide-react'
import api from '../services/api'
import { useDomainsList } from '../hooks/useDomains'

export default function AiAdvisorPage() {
  const { t } = useTranslation()
  const [domainId, setDomainId] = useState<number | ''>('')
  const [filePath, setFilePath] = useState('index.php')
  const [fileContent, setFileContent] = useState('')

  const domainsQ = useDomainsList()

  const cronBackupQ = useQuery({
    queryKey: ['ai', 'cron-backup'],
    queryFn: async () => (await api.get('/ai/cron-backup')).data as { suggestions: string[] },
  })
  const monitoringQ = useQuery({
    queryKey: ['ai', 'monitoring'],
    queryFn: async () => (await api.get('/ai/monitoring')).data as { alerts: string[] },
  })
  const accessQ = useQuery({
    queryKey: ['ai', 'access'],
    queryFn: async () => (await api.get('/ai/access')).data as { alerts: string[]; suggested_model?: string },
  })
  const deployQ = useQuery({
    queryKey: ['ai', 'deploy', domainId],
    enabled: domainId !== '',
    queryFn: async () => (await api.get(`/domains/${domainId}/ai/deploy`)).data as { suggestions: string[] },
  })
  const fileM = useMutation({
    mutationFn: async () =>
      (await api.post(`/domains/${domainId}/ai/file-editor`, { path: filePath, content: fileContent })).data as {
        syntax_ok: boolean
        issues: string[]
        suggestions: string[]
        auto_save_seconds: number
      },
  })

  const renderList = (rows?: string[]) => (
    <ul className="space-y-1 text-sm text-gray-700 dark:text-gray-300">
      {(rows ?? []).map((r, i) => <li key={`${r}-${i}`}>- {r}</li>)}
    </ul>
  )

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Sparkles className="h-8 w-8 text-fuchsia-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.ai_advisor')}</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">{t('ai.subtitle')}</p>
        </div>
      </div>

      <div className="card p-5">
        <h3 className="mb-2 text-sm font-semibold text-gray-900 dark:text-white">{t('ai.file_editor')}</h3>
        <div className="grid gap-2 md:grid-cols-2">
          <select className="input w-full" value={domainId} onChange={(e) => setDomainId(e.target.value ? Number(e.target.value) : '')}>
            <option value="">{t('domains.name')}</option>
            {(domainsQ.data ?? []).map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
          </select>
          <input className="input w-full" value={filePath} onChange={(e) => setFilePath(e.target.value)} placeholder="index.php" />
        </div>
        <textarea className="input mt-2 min-h-[120px] w-full font-mono text-xs" value={fileContent} onChange={(e) => setFileContent(e.target.value)} />
        <button type="button" className="btn-primary mt-2" disabled={domainId === '' || fileM.isPending} onClick={() => fileM.mutate()}>
          {t('ai.run_check')}
        </button>
        {fileM.data && (
          <div className="mt-2 text-sm">
            <p className={fileM.data.syntax_ok ? 'text-emerald-600' : 'text-red-600'}>
              {fileM.data.syntax_ok ? t('ai.syntax_ok') : t('ai.syntax_error')}
            </p>
            {renderList([...(fileM.data.issues ?? []), ...(fileM.data.suggestions ?? [])])}
          </div>
        )}
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <div className="card p-5">
          <h3 className="mb-2 text-sm font-semibold text-gray-900 dark:text-white">{t('ai.deploy')}</h3>
          {deployQ.isSuccess ? renderList(deployQ.data.suggestions) : <p className="text-sm text-gray-500">{t('ai.select_domain_for_deploy')}</p>}
        </div>
        <div className="card p-5">
          <h3 className="mb-2 text-sm font-semibold text-gray-900 dark:text-white">{t('ai.cron_backup')}</h3>
          {renderList(cronBackupQ.data?.suggestions)}
        </div>
        <div className="card p-5">
          <h3 className="mb-2 text-sm font-semibold text-gray-900 dark:text-white">{t('ai.monitoring')}</h3>
          {renderList(monitoringQ.data?.alerts)}
        </div>
        <div className="card p-5">
          <h3 className="mb-2 text-sm font-semibold text-gray-900 dark:text-white">{t('ai.access')}</h3>
          {renderList(accessQ.data?.alerts)}
          {accessQ.data?.suggested_model && (
            <p className="mt-2 text-xs text-gray-500">{accessQ.data.suggested_model}</p>
          )}
        </div>
      </div>
    </div>
  )
}
