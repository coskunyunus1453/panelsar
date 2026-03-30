import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import api from '../services/api'
import toast from 'react-hot-toast'
import clsx from 'clsx'
import {
  ExternalLink,
  Globe,
  Loader2,
  Plus,
  Search,
  Shield,
  ShieldCheck,
} from 'lucide-react'

const PHP_OPTIONS = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'] as const

type DomainRow = {
  id: number
  name: string
  php_version: string
  server_type: string
  status: string
  ssl_enabled?: boolean
}

type Busy = {
  php?: boolean
  server?: boolean
  ssl?: boolean
  status?: boolean
}

export default function DomainsPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [showAdd, setShowAdd] = useState(false)
  const [busy, setBusy] = useState<Record<number, Busy>>({})

  const domainsQ = useQuery({
    queryKey: ['domains', 'paginated'],
    queryFn: async () => (await api.get('/domains')).data,
  })

  const setBusyFlag = (domainId: number, key: keyof Busy, value: boolean) => {
    setBusy((prev) => ({
      ...prev,
      [domainId]: { ...(prev[domainId] ?? {}), [key]: value },
    }))
  }

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

  const phpM = useMutation({
    mutationFn: async (vars: { id: number; php_version: string }) =>
      api.post(`/domains/${vars.id}/php`, { php_version: vars.php_version }),
    onMutate: (vars) => setBusyFlag(vars.id, 'php', true),
    onSuccess: (_, vars) => {
      toast.success(t('domains.php_switched'))
      qc.invalidateQueries({ queryKey: ['domains'] })
      setBusyFlag(vars.id, 'php', false)
    },
    onError: (err: unknown, vars) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
      setBusyFlag(vars.id, 'php', false)
    },
  })

  const serverM = useMutation({
    mutationFn: async (vars: { id: number; server_type: string }) =>
      api.post(`/domains/${vars.id}/server`, { server_type: vars.server_type }),
    onMutate: (vars) => setBusyFlag(vars.id, 'server', true),
    onSuccess: (_, vars) => {
      toast.success(t('domains.server_switched'))
      qc.invalidateQueries({ queryKey: ['domains'] })
      setBusyFlag(vars.id, 'server', false)
    },
    onError: (err: unknown, vars) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
      setBusyFlag(vars.id, 'server', false)
    },
  })

  const statusM = useMutation({
    mutationFn: async (vars: { id: number; status: 'active' | 'suspended' }) =>
      api.post(`/domains/${vars.id}/status`, { status: vars.status }),
    onMutate: (vars) => setBusyFlag(vars.id, 'status', true),
    onSuccess: (_, vars) => {
      toast.success(t('domains.status_updated'))
      qc.invalidateQueries({ queryKey: ['domains'] })
      setBusyFlag(vars.id, 'status', false)
    },
    onError: (err: unknown, vars) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
      setBusyFlag(vars.id, 'status', false)
    },
  })

  const sslIssueM = useMutation({
    mutationFn: async (vars: { id: number }) => api.post(`/domains/${vars.id}/ssl/issue`, {}),
    onMutate: (vars) => setBusyFlag(vars.id, 'ssl', true),
    onSuccess: (_, vars) => {
      toast.success(t('ssl.issued'))
      qc.invalidateQueries({ queryKey: ['domains'] })
      setBusyFlag(vars.id, 'ssl', false)
    },
    onError: (err: unknown, vars) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
      setBusyFlag(vars.id, 'ssl', false)
    },
  })

  const list: DomainRow[] = domainsQ.data?.data ?? []
  const total = (domainsQ.data?.total as number | undefined) ?? list.length
  const filtered = list.filter((d) => d.name.toLowerCase().includes(search.toLowerCase()))

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('domains.title')}</h1>
          <p className="mt-1 text-gray-500 dark:text-gray-400">
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

      {showAdd && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-md w-full space-y-4 p-6 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('domains.new_title')}</h2>
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
              <div className="flex justify-end gap-2 pt-2">
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

      <div className="card">
        <div className="p-4 border-b border-gray-200 dark:border-panel-border">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
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
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  {t('domains.name')}
                </th>
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  {t('domains.php_version')}
                </th>
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  {t('domains.ssl_status')}
                </th>
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  {t('domains.server_type')}
                </th>
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  {t('common.status')}
                </th>
                <th className="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
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
                filtered.map((domain) => {
                  const b = busy[domain.id] ?? {}
                  const sslEnabled = !!domain.ssl_enabled
                  const canToggle = domain.status === 'active' || domain.status === 'suspended'

                  const statusBadge = clsx(
                    'px-2.5 py-1 text-xs font-medium rounded-full',
                    domain.status === 'active' &&
                      'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400',
                    domain.status === 'suspended' &&
                      'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
                    domain.status === 'pending' &&
                      'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400',
                  )

                  const nextStatus = domain.status === 'active' ? 'suspended' : 'active'
                  const nextStatusLabel =
                    nextStatus === 'suspended' ? t('domains.suspended') : t('common.active')

                  return (
                    <tr
                      key={domain.id}
                      className="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                    >
                      <td className="px-6 py-4">
                        <Link
                          to={`/files?domain=${domain.id}`}
                          className="flex items-center gap-3"
                        >
                          <Globe className="h-5 w-5 text-primary-500" />
                          <span className="font-medium text-gray-900 dark:text-white">
                            {domain.name}
                          </span>
                        </Link>
                      </td>

                      <td className="px-6 py-4">
                        <select
                          className="input w-[120px]"
                          value={domain.php_version}
                          disabled={!!b.php}
                          onChange={(e) => {
                            const next = e.target.value
                            if (next === domain.php_version) return
                            if (
                              !window.confirm(
                                t('domains.confirm_php_change', { php: next }),
                              )
                            ) {
                              return
                            }
                            phpM.mutate({ id: domain.id, php_version: next })
                          }}
                        >
                          {PHP_OPTIONS.map((v) => (
                            <option key={v} value={v}>
                              PHP {v}
                            </option>
                          ))}
                        </select>
                      </td>

                      <td className="px-6 py-4">
                        {sslEnabled ? (
                          <div className="flex items-center gap-1.5 text-green-600 dark:text-green-400">
                            <ShieldCheck className="h-4 w-4" />
                            <span className="text-sm">{t('domains.ssl_active')}</span>
                          </div>
                        ) : (
                          <div className="flex items-center gap-2">
                            <div className="flex items-center gap-1.5 text-gray-400">
                              <Shield className="h-4 w-4" />
                              <span className="text-sm">{t('domains.ssl_none')}</span>
                            </div>
                            <button
                              type="button"
                              className="btn-secondary px-2.5 py-1.5 text-xs disabled:opacity-70"
                              disabled={!!b.ssl}
                              onClick={() => {
                                if (
                                  !window.confirm(
                                    t('domains.confirm_ssl_issue'),
                                  )
                                ) {
                                  return
                                }
                                sslIssueM.mutate({ id: domain.id })
                              }}
                            >
                              {b.ssl ? <Loader2 className="h-4 w-4 animate-spin" /> : t('domains.ssl_add_letsencrypt')}
                            </button>
                          </div>
                        )}
                      </td>

                      <td className="px-6 py-4">
                        <select
                          className="input w-[130px]"
                          value={domain.server_type}
                          disabled={!!b.server}
                          onChange={(e) => {
                            const next = e.target.value
                            if (next === domain.server_type) return
                            const nextLabel = next === 'apache' ? 'Apache' : 'Nginx'
                            if (
                              !window.confirm(
                                t('domains.confirm_server_change', { server: nextLabel }),
                              )
                            ) {
                              return
                            }
                            serverM.mutate({ id: domain.id, server_type: next })
                          }}
                        >
                          <option value="nginx">nginx</option>
                          <option value="apache">Apache</option>
                        </select>
                      </td>

                      <td className="px-6 py-4">
                        {canToggle ? (
                          <button
                            type="button"
                            className={statusBadge}
                            disabled={!!b.status}
                            onClick={() => {
                              if (
                                !window.confirm(
                                  t('domains.confirm_status_change', { status: nextStatusLabel }),
                                )
                              ) {
                                return
                              }
                              statusM.mutate({
                                id: domain.id,
                                status: nextStatus,
                              })
                            }}
                          >
                            {domain.status === 'active'
                              ? t('common.active')
                              : domain.status === 'suspended'
                                ? t('domains.suspended')
                                : domain.status}
                          </button>
                        ) : (
                          <span className={statusBadge}>
                            {domain.status === 'pending' ? t('common.pending') : domain.status}
                          </span>
                        )}
                      </td>

                      <td className="px-6 py-4 text-right">
                        <button
                          type="button"
                          title={t('domains.open_site')}
                          className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                          onClick={() => window.open(`http://${domain.name}`, '_blank')}
                        >
                          <ExternalLink className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  )
                })}
            </tbody>
          </table>
        </div>

        {!domainsQ.isLoading && filtered.length === 0 && (
          <div className="py-12 text-center text-gray-500 dark:text-gray-400">{t('common.no_data')}</div>
        )}
      </div>
    </div>
  )
}
