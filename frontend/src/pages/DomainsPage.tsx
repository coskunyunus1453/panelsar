import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import DomainQuickSettingsModal, {
  type DomainQuickRow,
} from '../components/domains/DomainQuickSettingsModal'
import {
  Globe,
  Plus,
  Search,
  Shield,
  ShieldCheck,
  ExternalLink,
  Settings2,
} from 'lucide-react'
import toast from 'react-hot-toast'
import clsx from 'clsx'

const PHP_OPTIONS = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'] as const

export default function DomainsPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [showAdd, setShowAdd] = useState(false)
  const [quickDomain, setQuickDomain] = useState<DomainQuickRow | null>(null)

  const domainsQ = useQuery({
    queryKey: ['domains', 'paginated'],
    queryFn: async () => (await api.get('/domains')).data,
  })

  const createM = useMutation({
    mutationFn: async (payload: { name: string; php_version: string; server_type: string }) => {
      await api.post('/domains', payload)
    },
    onSuccess: () => {
      toast.success(t('domains.created'))
      qc.invalidateQueries({ queryKey: ['domains'] })
      setShowAdd(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const list: DomainQuickRow[] = domainsQ.data?.data ?? []
  const total = (domainsQ.data?.total as number | undefined) ?? list.length
  const filtered = list.filter((d) => d.name.toLowerCase().includes(search.toLowerCase()))

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            {t('domains.title')}
          </h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">
            {total} {t('nav.domains').toLowerCase()}
          </p>
        </div>
        <button
          type="button"
          className="btn-primary flex items-center gap-2"
          onClick={() => setShowAdd(true)}
        >
          <Plus className="h-4 w-4" />
          {t('domains.add')}
        </button>
      </div>

      <DomainQuickSettingsModal
        domain={quickDomain}
        open={quickDomain !== null}
        onClose={() => setQuickDomain(null)}
      />

      {showAdd && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-md w-full p-6 space-y-4 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
              {t('domains.new_title')}
            </h2>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                createM.mutate({
                  name: String(fd.get('name') || '').trim(),
                  php_version: String(fd.get('php_version') || '8.2'),
                  server_type: String(fd.get('server_type') || 'nginx'),
                })
              }}
            >
              <div>
                <label className="label">{t('domains.name')}</label>
                <input name="name" className="input w-full" required placeholder="ornek.local" />
              </div>
              <div>
                <label className="label">{t('domains.php_version')}</label>
                <select name="php_version" className="input w-full" defaultValue="8.2">
                  {PHP_OPTIONS.map((v) => (
                    <option key={v} value={v}>
                      PHP {v}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">{t('domains.server_type')}</label>
                <select name="server_type" className="input w-full" defaultValue="nginx">
                  <option value="nginx">nginx</option>
                  <option value="apache">Apache</option>
                </select>
              </div>
              <div className="flex gap-2 justify-end pt-2">
                <button
                  type="button"
                  className="btn-secondary"
                  onClick={() => setShowAdd(false)}
                >
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

      <div className="card">
        <div className="p-4 border-b border-gray-200 dark:border-panel-border">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={t('common.search')}
              className="input pl-10"
            />
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-200 dark:border-panel-border">
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('domains.name')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('domains.php_version')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('domains.ssl_status')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('domains.server_type')}
                </th>
                <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('common.status')}
                </th>
                <th className="text-right px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {t('common.actions')}
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-panel-border">
              {domainsQ.isLoading && (
                <tr>
                  <td colSpan={6} className="px-6 py-8 text-center text-gray-500">
                    {t('common.loading')}
                  </td>
                </tr>
              )}
              {!domainsQ.isLoading &&
                filtered.map((domain) => (
                  <tr
                    key={domain.id}
                    className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                  >
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <Globe className="h-5 w-5 text-primary-500" />
                        <span className="font-medium text-gray-900 dark:text-white">
                          {domain.name}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span className="px-2.5 py-1 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 text-xs font-medium rounded-full">
                        PHP {domain.php_version}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      {domain.ssl_enabled ? (
                        <div className="flex items-center gap-1.5 text-green-600 dark:text-green-400">
                          <ShieldCheck className="h-4 w-4" />
                          <span className="text-sm">{t('domains.ssl_active')}</span>
                        </div>
                      ) : (
                        <div className="flex items-center gap-1.5 text-gray-400">
                          <Shield className="h-4 w-4" />
                          <span className="text-sm">{t('domains.ssl_none')}</span>
                        </div>
                      )}
                    </td>
                    <td className="px-6 py-4">
                      <span className="text-sm text-gray-600 dark:text-gray-400 capitalize">
                        {domain.server_type}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <span
                        className={clsx(
                          'px-2.5 py-1 text-xs font-medium rounded-full',
                          domain.status === 'active' &&
                            'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400',
                          domain.status === 'suspended' &&
                            'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
                          domain.status === 'pending' &&
                            'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400',
                        )}
                      >
                        {domain.status === 'active'
                          ? t('common.active')
                          : domain.status === 'suspended'
                            ? t('domains.suspended')
                            : domain.status === 'pending'
                              ? t('common.pending')
                              : domain.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <button
                          type="button"
                          title={t('domains.quick_settings')}
                          className="p-1.5 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 text-primary-600 dark:text-primary-400"
                          onClick={() => setQuickDomain(domain)}
                        >
                          <Settings2 className="h-4 w-4" />
                        </button>
                        <button
                          type="button"
                          title={t('domains.open_site')}
                          className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                          onClick={() => window.open(`http://${domain.name}`, '_blank')}
                        >
                          <ExternalLink className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>

        {!domainsQ.isLoading && filtered.length === 0 && (
          <div className="text-center py-12 text-gray-500 dark:text-gray-400">
            {t('common.no_data')}
          </div>
        )}
      </div>
    </div>
  )
}
