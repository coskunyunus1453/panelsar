import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import { Download } from 'lucide-react'
import toast from 'react-hot-toast'
import { useDomainsList } from '../hooks/useDomains'

type AppRow = { id: string; name: string; version: string; automated?: boolean }

const FALLBACK_APPS: AppRow[] = [
  { id: 'wordpress', name: 'WordPress', version: 'latest', automated: true },
  { id: 'joomla', name: 'Joomla', version: 'latest', automated: false },
  { id: 'laravel', name: 'Laravel', version: '11.x', automated: false },
  { id: 'drupal', name: 'Drupal', version: '10.x', automated: false },
  { id: 'prestashop', name: 'PrestaShop', version: '8.x', automated: false },
]

export default function InstallerPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const domainsQ = useDomainsList()
  const [domainId, setDomainId] = useState<number | ''>('')
  const [wpDatabaseId, setWpDatabaseId] = useState<number | ''>('')
  const [tablePrefix, setTablePrefix] = useState('wp_')

  const databasesQ = useQuery({
    queryKey: ['databases', 'paginated'],
    queryFn: async () => (await api.get('/databases')).data as { data?: { id: number; name: string; type: string }[] },
  })

  const q = useQuery({
    queryKey: ['installer-apps'],
    queryFn: async () => (await api.get('/installer/apps')).data as { apps: AppRow[] },
  })

  const installM = useMutation({
    mutationFn: async (payload: { app: string; database_id?: number; table_prefix?: string }) => {
      const { data } = await api.post(`/domains/${domainId}/installer`, payload)
      return data as { message?: string }
    },
    onSuccess: (data) => {
      toast.success(data.message ?? t('installer.done'))
      qc.invalidateQueries({ queryKey: ['domains'] })
    },
    onError: (err: unknown) => {
      const ax = err as {
        response?: { data?: { message?: string; hint?: string } }
      }
      const d = ax.response?.data
      const msg = d?.message ?? String(err)
      toast.error([msg, d?.hint].filter(Boolean).join(' — '), { duration: 10000 })
    },
  })

  const apps = q.data?.apps?.length ? q.data.apps : FALLBACK_APPS
  const mysqlDbs = (databasesQ.data?.data ?? []).filter((d) => d.type === 'mysql')

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Download className="h-8 w-8 text-indigo-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.installer')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('installer.subtitle')}</p>
        </div>
      </div>

      <div className="card p-4 flex flex-wrap gap-4 items-end">
        <div>
          <label className="label">{t('domains.name')}</label>
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
          <label className="label">{t('installer.wordpress_db')}</label>
          <select
            className="input min-w-[240px]"
            value={wpDatabaseId}
            onChange={(e) => setWpDatabaseId(e.target.value ? Number(e.target.value) : '')}
          >
            <option value="">{t('common.select')}</option>
            {mysqlDbs.map((d) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="label">{t('installer.table_prefix')}</label>
          <input
            className="input min-w-[120px]"
            value={tablePrefix}
            onChange={(e) => setTablePrefix(e.target.value)}
            placeholder="wp_"
          />
        </div>
      </div>

      <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {apps.map((app) => {
          const auto = app.automated === true
          const wp = app.id === 'wordpress'
          const disabled =
            !domainId ||
            installM.isPending ||
            !auto ||
            (wp && !wpDatabaseId)
          return (
            <div key={app.id} className="card p-4 flex flex-col gap-2">
              <h3 className="font-semibold text-gray-900 dark:text-white">{app.name}</h3>
              <p className="text-xs text-gray-500">{app.version}</p>
              {!auto && <p className="text-xs text-amber-600 dark:text-amber-400">{t('installer.manual_only')}</p>}
              <button
                type="button"
                className="btn-secondary text-sm mt-auto"
                disabled={disabled}
                title={!auto ? t('installer.manual_only') : undefined}
                onClick={() =>
                  installM.mutate({
                    app: app.id,
                    ...(wp && wpDatabaseId ? { database_id: wpDatabaseId as number, table_prefix: tablePrefix } : {}),
                  })
                }
              >
                {t('installer.install')}
              </button>
            </div>
          )
        })}
      </div>
    </div>
  )
}
