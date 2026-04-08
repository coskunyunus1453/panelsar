import { useCallback, useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  BookOpen,
  Copy,
  Info,
  KeyRound,
  Mail,
  Pencil,
  Plus,
  Send,
  Server,
  Trash2,
} from 'lucide-react'
import toast from 'react-hot-toast'
import clsx from 'clsx'
import api from '../services/api'
import { useDomainsList } from '../hooks/useDomains'
import { useAuthStore } from '../store/authStore'
import { safeExternalHttpUrl } from '../lib/urlSafety'

type MailRow = {
  id: number
  email: string
  quota_mb: number
  status: string
  forwarding_address?: string | null
  autoresponder_enabled?: boolean
  autoresponder_message?: string | null
}

type EngineMailbox = {
  email?: string
  quota_mb?: number
  password?: string
}

type ForwarderRow = {
  id: number
  source: string
  destination: string
  keep_copy?: boolean
}

function ModalFrame({
  title,
  children,
  onClose,
}: {
  title: string
  children: React.ReactNode
  onClose: () => void
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div
        className="card max-h-[90vh] w-full max-w-lg overflow-y-auto bg-white p-6 dark:bg-gray-900"
        role="dialog"
        aria-labelledby="email-modal-title"
      >
        <div className="mb-4 flex items-start justify-between gap-4">
          <h2 id="email-modal-title" className="text-lg font-semibold text-gray-900 dark:text-white">
            {title}
          </h2>
          <button
            type="button"
            className="rounded-lg p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
            onClick={onClose}
            aria-label="Close"
          >
            ×
          </button>
        </div>
        {children}
      </div>
    </div>
  )
}

export default function EmailPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [searchParams] = useSearchParams()
  const user = useAuthStore((s) => s.user)
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')

  const domainsQ = useDomainsList()
  const [domainId, setDomainId] = useState<number | ''>('')
  const [showAdd, setShowAdd] = useState(false)
  const [showAddForwarder, setShowAddForwarder] = useState(false)
  const [editing, setEditing] = useState<MailRow | null>(null)

  const [editQuota, setEditQuota] = useState(500)
  const [editForward, setEditForward] = useState('')
  const [editAutoEnabled, setEditAutoEnabled] = useState(false)
  const [editAutoMsg, setEditAutoMsg] = useState('')
  const [editPassword, setEditPassword] = useState('')
  const [editRegen, setEditRegen] = useState(false)

  useEffect(() => {
    const raw = searchParams.get('domain')
    if (!raw || !domainsQ.data?.length) return
    const id = Number(raw)
    if (!Number.isFinite(id)) return
    if (domainsQ.data.some((d) => d.id === id)) setDomainId(id)
  }, [searchParams, domainsQ.data])

  const domainName = useMemo(
    () => (domainsQ.data ?? []).find((d) => d.id === domainId)?.name ?? '',
    [domainsQ.data, domainId],
  )

  const suggestedHost = domainName ? `mail.${domainName}` : ''

  const copyHost = useCallback(
    async (value: string) => {
      if (!value) return
      try {
        await navigator.clipboard.writeText(value)
        toast.success(t('email.copied'))
      } catch {
        toast.error(t('email.copy_failed'))
      }
    },
    [t],
  )

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
          ? `${t('email.created')} — ${t('email.password_once')} ${plain}`
          : t('email.created'),
        { duration: plain ? 22_000 : 4000 },
      )
      qc.invalidateQueries({ queryKey: ['email', domainId] })
      setShowAdd(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const updateM = useMutation({
    mutationFn: async ({
      id,
      body,
    }: {
      id: number
      body: Record<string, unknown>
    }) => api.patch(`/email/${id}`, body),
    onSuccess: (res) => {
      const plain = (res.data as { password_plain?: string })?.password_plain
      toast.success(
        plain
          ? `${t('email.updated')} — ${t('email.password_once')} ${plain}`
          : t('email.updated'),
        { duration: plain ? 22_000 : 4000 },
      )
      qc.invalidateQueries({ queryKey: ['email', domainId] })
      setEditing(null)
      setEditPassword('')
      setEditRegen(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const d = ax.response?.data
      const msg =
        d?.message ??
        (d?.errors ? Object.values(d.errors).flat().join(', ') : String(err))
      toast.error(msg)
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

  const createForwarderM = useMutation({
    mutationFn: async (payload: { source: string; destination: string; keep_copy?: boolean }) =>
      api.post(`/domains/${domainId}/email/forwarders`, payload),
    onSuccess: () => {
      toast.success(t('email.forwarder_created'))
      qc.invalidateQueries({ queryKey: ['email', domainId] })
      setShowAddForwarder(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const deleteForwarderM = useMutation({
    mutationFn: async (id: number) => api.delete(`/email/forwarders/${id}`),
    onSuccess: () => {
      toast.success(t('email.forwarder_deleted'))
      qc.invalidateQueries({ queryKey: ['email', domainId] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const accounts: MailRow[] = q.data?.accounts ?? []
  const forwarders: ForwarderRow[] = q.data?.forwarders ?? []
  const webmailUrl: string | undefined = safeExternalHttpUrl(q.data?.webmail_url ?? '') ?? undefined
  const webmailStatus = q.data?.webmail_status as { host?: string; dns_ok?: boolean; hint?: string } | undefined
  const mailOv = q.data?.mail as
    | { mail_enabled?: boolean; mailboxes?: EngineMailbox[]; spf?: string; dmarc?: string }
    | undefined
  const engineBoxes: EngineMailbox[] = Array.isArray(mailOv?.mailboxes) ? mailOv.mailboxes : []

  const openEdit = (row: MailRow) => {
    setEditing(row)
    setEditQuota(row.quota_mb)
    setEditForward(row.forwarding_address ?? '')
    setEditAutoEnabled(!!row.autoresponder_enabled)
    setEditAutoMsg(row.autoresponder_message ?? '')
    setEditPassword('')
    setEditRegen(false)
  }

  const submitEdit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!editing) return
    const body: Record<string, unknown> = {
      quota_mb: editQuota,
      forwarding_address: editForward.trim() === '' ? null : editForward.trim(),
      autoresponder_enabled: editAutoEnabled,
      autoresponder_message: editAutoMsg.trim() === '' ? null : editAutoMsg.trim(),
    }
    if (editRegen) body.regenerate_password = true
    else if (editPassword.trim()) body.password = editPassword.trim()
    updateM.mutate({ id: editing.id, body })
  }

  return (
    <div className="space-y-8">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-500/10">
            <Mail className="h-7 w-7 text-violet-600 dark:text-violet-400" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.email')}</h1>
            <p className="mt-1 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{t('email.subtitle')}</p>
          </div>
        </div>
        <button
          type="button"
          className="btn-primary inline-flex items-center gap-2"
          disabled={!domainId}
          onClick={() => setShowAdd(true)}
        >
          <Plus className="h-4 w-4" />
          {t('common.create')}
        </button>
      </div>

      <div className="card p-5">
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
        <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">{t('email.dns_hint')}</p>
      </div>

      {domainId !== '' && suggestedHost && (
        <div className="card p-5">
          <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
            <KeyRound className="h-4 w-4 text-gray-500" />
            {t('email.connect_title')}
          </h3>
          <p className="mb-4 text-xs text-gray-500 dark:text-gray-400">{t('email.ports_hint')}</p>
          <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <div className="flex flex-1 min-w-[200px] items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800/50">
              <div>
                <div className="text-xs text-gray-500 dark:text-gray-400">{t('email.imap_label')}</div>
                <code className="text-sm font-mono text-gray-900 dark:text-white">{suggestedHost}</code>
              </div>
              <button
                type="button"
                className="btn-secondary shrink-0 py-1.5 text-xs"
                onClick={() => copyHost(suggestedHost)}
              >
                <Copy className="mr-1 inline h-3 w-3" />
                {t('email.copy_host')}
              </button>
            </div>
            <div className="flex flex-1 min-w-[200px] items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800/50">
              <div>
                <div className="text-xs text-gray-500 dark:text-gray-400">{t('email.smtp_label')}</div>
                <code className="text-sm font-mono text-gray-900 dark:text-white">{suggestedHost}</code>
              </div>
              <button
                type="button"
                className="btn-secondary shrink-0 py-1.5 text-xs"
                onClick={() => copyHost(suggestedHost)}
              >
                <Copy className="mr-1 inline h-3 w-3" />
                {t('email.copy_host')}
              </button>
            </div>
          </div>
          <p className="mt-4 text-xs text-gray-600 dark:text-gray-400">{t('email.auth_full_email_hint')}</p>

          <div className="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table className="w-full min-w-[320px] text-left text-xs">
              <caption className="border-b border-gray-200 bg-gray-50 px-3 py-2 text-left text-sm font-semibold text-gray-900 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white">
                {t('email.ports_table_title')}
              </caption>
              <thead>
                <tr className="border-b border-gray-100 bg-gray-50/80 text-gray-600 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                  <th className="px-3 py-2 font-medium">{t('email.port_col_service')}</th>
                  <th className="px-3 py-2 font-medium">{t('email.port_col_port')}</th>
                  <th className="px-3 py-2 font-medium">{t('email.port_col_encryption')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {(
                  [
                    ['port_imaps', '993', 'enc_ssl_tls'],
                    ['port_imap_starttls', '143', 'enc_starttls'],
                    ['port_smtp_submission', '587', 'enc_starttls'],
                    ['port_smtps', '465', 'enc_ssl_tls'],
                  ] as const
                ).map(([label, port, enc]) => (
                  <tr key={label}>
                    <td className="px-3 py-2 text-gray-900 dark:text-gray-100">{t(`email.${label}`)}</td>
                    <td className="px-3 py-2 font-mono text-gray-800 dark:text-gray-200">{port}</td>
                    <td className="px-3 py-2 text-gray-700 dark:text-gray-300">{t(`email.${enc}`)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="mt-4 rounded-lg border border-sky-200 bg-sky-50/80 p-3 text-xs text-sky-950 dark:border-sky-900/40 dark:bg-sky-950/25 dark:text-sky-100">
            <div className="font-semibold">{t('email.deliverability_title')}</div>
            <p className="mt-1 leading-relaxed">{t('email.deliverability_intro')}</p>
          </div>

          {isAdmin && (
            <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
              <span>{t('email.stack_webmail_hint')}</span>
              <Link to="/admin/stack" className="font-medium text-primary-600 hover:underline dark:text-primary-400">
                {t('email.stack_webmail_cta')} →
              </Link>
            </div>
          )}

          {webmailUrl && (
            <div className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50/70 px-3 py-2 dark:border-emerald-900/40 dark:bg-emerald-950/20">
              <div className="flex flex-wrap items-center gap-2">
                <a
                  href={webmailUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="btn-primary py-1.5 text-xs"
                >
                  {t('email.webmail_open')}
                </a>
                <code className="text-xs text-emerald-800 dark:text-emerald-200">{webmailUrl}</code>
                <button
                  type="button"
                  className="btn-secondary py-1.5 text-xs"
                  onClick={() => copyHost(webmailUrl)}
                >
                  <Copy className="mr-1 inline h-3 w-3" />
                  {t('email.copy_host')}
                </button>
              </div>
            </div>
          )}
          {!webmailUrl && webmailStatus?.host && (
            <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50/70 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-200">
              <div className="font-medium">Webmail linki hazır değil: <code>{webmailStatus.host}</code></div>
              <div className="mt-1">{webmailStatus.hint ?? 'DNS kontrolü başarısız.'}</div>
            </div>
          )}
        </div>
      )}

      {showAdd && domainId !== '' && (
        <ModalFrame title={t('email.add_mailbox_title')} onClose={() => setShowAdd(false)}>
          <form
            className="space-y-4"
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
              <label className="label">{t('email.local_part')}</label>
              <input name="local_part" className="input w-full" required placeholder="iletisim" />
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{t('email.local_part_hint')}</p>
            </div>
            <div>
              <label className="label">{t('email.quota_mb')}</label>
              <input name="quota_mb" type="number" className="input w-full" defaultValue={500} min={1} />
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
        </ModalFrame>
      )}

      {editing && (
        <ModalFrame title={t('email.edit_mailbox_title')} onClose={() => setEditing(null)}>
          <form className="space-y-4" onSubmit={submitEdit}>
            <p className="rounded-lg bg-gray-100 px-3 py-2 font-mono text-sm dark:bg-gray-800">{editing.email}</p>
            <div>
              <label className="label">{t('email.quota_mb')}</label>
              <input
                type="number"
                className="input w-full"
                min={1}
                value={editQuota}
                onChange={(e) => setEditQuota(Number(e.target.value) || 1)}
              />
            </div>
            <div>
              <label className="label">{t('email.forwarding')}</label>
              <input
                type="email"
                className="input w-full"
                value={editForward}
                onChange={(e) => setEditForward(e.target.value)}
                placeholder="ornek@diger.com"
              />
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{t('email.forwarding_hint')}</p>
            </div>
            <div className="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
              <label className="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-white">
                <input
                  type="checkbox"
                  checked={editAutoEnabled}
                  onChange={(e) => setEditAutoEnabled(e.target.checked)}
                />
                {t('email.autoresponder')}
              </label>
              {editAutoEnabled && (
                <textarea
                  className="input mt-2 min-h-[88px] w-full"
                  value={editAutoMsg}
                  onChange={(e) => setEditAutoMsg(e.target.value)}
                  placeholder={t('email.autoresponder_message')}
                />
              )}
            </div>
            <div className="rounded-lg border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-900/40 dark:bg-amber-950/20">
              <div className="mb-2 text-sm font-medium text-gray-900 dark:text-white">{t('email.password_section')}</div>
              <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                  type="checkbox"
                  checked={editRegen}
                  onChange={(e) => {
                    setEditRegen(e.target.checked)
                    if (e.target.checked) setEditPassword('')
                  }}
                />
                {t('email.regenerate_password')}
              </label>
              {!editRegen && (
                <>
                  <input
                    type="password"
                    className="input mt-2 w-full"
                    autoComplete="new-password"
                    value={editPassword}
                    onChange={(e) => setEditPassword(e.target.value)}
                    placeholder={t('email.new_password')}
                  />
                  <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">{t('email.new_password_hint')}</p>
                </>
              )}
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button type="button" className="btn-secondary" onClick={() => setEditing(null)}>
                {t('common.cancel')}
              </button>
              <button type="submit" className="btn-primary" disabled={updateM.isPending}>
                {t('email.save_changes')}
              </button>
            </div>
          </form>
        </ModalFrame>
      )}

      {showAddForwarder && domainId !== '' && (
        <ModalFrame title={t('email.forwarder_add_title')} onClose={() => setShowAddForwarder(false)}>
          <form
            className="space-y-4"
            onSubmit={(ev) => {
              ev.preventDefault()
              const fd = new FormData(ev.currentTarget)
              createForwarderM.mutate({
                source: String(fd.get('source') || '').trim(),
                destination: String(fd.get('destination') || '').trim(),
                keep_copy: fd.get('keep_copy') === 'on',
              })
            }}
          >
            <div>
              <label className="label">{t('email.forwarder_source')}</label>
              <input name="source" className="input w-full" required placeholder={`info@${domainName || 'domain.com'}`} />
            </div>
            <div>
              <label className="label">{t('email.forwarder_destination')}</label>
              <input name="destination" type="email" className="input w-full" required placeholder="target@example.com" />
            </div>
            <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
              <input name="keep_copy" type="checkbox" />
              {t('email.forwarder_keep_copy')}
            </label>
            <div className="flex justify-end gap-2 pt-2">
              <button type="button" className="btn-secondary" onClick={() => setShowAddForwarder(false)}>
                {t('common.cancel')}
              </button>
              <button type="submit" className="btn-primary" disabled={createForwarderM.isPending}>
                {t('common.create')}
              </button>
            </div>
          </form>
        </ModalFrame>
      )}

      <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div className="border-b border-gray-100 bg-gray-50/80 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/40">
          <h3 className="text-sm font-semibold text-gray-900 dark:text-white">{t('nav.email')}</h3>
        </div>
        {domainId === '' ? (
          <p className="p-10 text-center text-gray-500 dark:text-gray-400">{t('email.no_domain')}</p>
        ) : q.isLoading ? (
          <p className="p-10 text-center text-gray-500">{t('common.loading')}</p>
        ) : q.isError ? (
          <p className="p-10 text-center text-red-600 dark:text-red-400">{t('email.load_error')}</p>
        ) : accounts.length === 0 ? (
          <p className="p-10 text-center text-gray-500 dark:text-gray-400">{t('email.empty_mailboxes')}</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-100 bg-gray-50/50 text-left dark:border-gray-800 dark:bg-gray-800/30">
                  <th className="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">{t('email.table_email')}</th>
                  <th className="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">{t('email.table_quota')}</th>
                  <th className="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">{t('email.table_status')}</th>
                  <th className="w-28 px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-400">
                    {t('common.actions')}
                  </th>
                </tr>
              </thead>
              <tbody>
                {accounts.map((a) => (
                  <tr
                    key={a.id}
                    className="border-b border-gray-50 transition-colors hover:bg-gray-50/80 dark:border-gray-800 dark:hover:bg-gray-800/40"
                  >
                    <td className="px-4 py-3">
                      <span className="font-mono text-gray-900 dark:text-gray-100">{a.email}</span>
                      {a.forwarding_address ? (
                        <div className="mt-0.5 text-xs text-gray-500">→ {a.forwarding_address}</div>
                      ) : null}
                    </td>
                    <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{a.quota_mb}</td>
                    <td className="px-4 py-3">
                      <span
                        className={clsx(
                          'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                          a.status === 'active'
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                        )}
                      >
                        {a.status}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button
                        type="button"
                        className="mr-1 inline-flex rounded-lg p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                        onClick={() => openEdit(a)}
                        title={t('email.edit')}
                      >
                        <Pencil className="h-4 w-4" />
                      </button>
                      <button
                        type="button"
                        className="inline-flex rounded-lg p-2 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                        onClick={() => {
                          if (window.confirm(t('common.confirm_delete'))) deleteM.mutate(a.id)
                        }}
                        title={t('common.delete')}
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div className="flex items-center justify-between border-b border-gray-100 bg-gray-50/80 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/40">
          <h3 className="text-sm font-semibold text-gray-900 dark:text-white">{t('email.forwarders_title')}</h3>
          <button
            type="button"
            className="btn-primary inline-flex items-center gap-2 py-1.5 text-xs"
            disabled={domainId === ''}
            onClick={() => setShowAddForwarder(true)}
          >
            <Plus className="h-3.5 w-3.5" />
            {t('email.forwarder_add')}
          </button>
        </div>
        {domainId === '' ? (
          <p className="p-6 text-center text-gray-500 dark:text-gray-400">{t('email.no_domain')}</p>
        ) : forwarders.length === 0 ? (
          <p className="p-6 text-center text-gray-500 dark:text-gray-400">{t('email.forwarders_empty')}</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-100 bg-gray-50/50 text-left dark:border-gray-800 dark:bg-gray-800/30">
                  <th className="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">{t('email.forwarder_source')}</th>
                  <th className="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">{t('email.forwarder_destination')}</th>
                  <th className="w-28 px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-400">{t('common.actions')}</th>
                </tr>
              </thead>
              <tbody>
                {forwarders.map((f) => (
                  <tr key={f.id} className="border-b border-gray-50 dark:border-gray-800">
                    <td className="px-4 py-3 font-mono text-gray-900 dark:text-gray-100">{f.source}</td>
                    <td className="px-4 py-3 font-mono text-gray-700 dark:text-gray-300">{f.destination}</td>
                    <td className="px-4 py-3 text-right">
                      <button
                        type="button"
                        className="inline-flex rounded-lg p-2 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                        onClick={() => {
                          if (window.confirm(t('common.confirm_delete'))) deleteForwarderM.mutate(f.id)
                        }}
                        title={t('common.delete')}
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {domainId !== '' && mailOv != null && (
        <div className="card p-5 text-sm">
          <div className="mb-3 flex flex-wrap items-center gap-3 text-gray-700 dark:text-gray-300">
            <span className="font-medium">
              {mailOv.mail_enabled ? t('email.mail_status_on') : t('email.mail_status_off')}
            </span>
            {mailOv.spf != null && mailOv.spf !== '' && (
              <span className="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs dark:bg-gray-800">SPF: {mailOv.spf}</span>
            )}
            {mailOv.dmarc != null && mailOv.dmarc !== '' && (
              <span className="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs dark:bg-gray-800">
                DMARC: {mailOv.dmarc}
              </span>
            )}
          </div>
          {engineBoxes.length > 0 && (
            <>
              <p className="mb-2 font-semibold text-gray-900 dark:text-white">{t('email.engine_mailboxes_title')}</p>
              <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table className="w-full text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-800/80">
                    <tr>
                      <th className="px-3 py-2 text-left">{t('email.table_email')}</th>
                      <th className="px-3 py-2 text-left">{t('email.table_quota')}</th>
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
              </div>
            </>
          )}
        </div>
      )}

      <section className="rounded-xl border border-gray-200 bg-gradient-to-br from-gray-50 to-white p-5 dark:border-gray-700 dark:from-gray-900/80 dark:to-gray-900">
        <div className="mb-4 flex items-center gap-2 text-gray-900 dark:text-white">
          <Info className="h-5 w-5 text-violet-500" />
          <h2 className="text-base font-semibold">{t('email.guide_title')}</h2>
        </div>
        <p className="mb-5 text-sm text-gray-600 dark:text-gray-400">{t('email.guide_intro')}</p>
        <div className="grid gap-4 md:grid-cols-3">
          <div className="rounded-lg border border-gray-200 bg-white/80 p-4 dark:border-gray-600 dark:bg-gray-800/50">
            <div className="mb-2 flex items-center gap-2 font-medium text-gray-900 dark:text-white">
              <BookOpen className="h-4 w-4 text-secondary-500" />
              {t('email.card_incoming_title')}
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">{t('email.card_incoming_body')}</p>
          </div>
          <div className="rounded-lg border border-gray-200 bg-white/80 p-4 dark:border-gray-600 dark:bg-gray-800/50">
            <div className="mb-2 flex items-center gap-2 font-medium text-gray-900 dark:text-white">
              <Send className="h-4 w-4 text-amber-500" />
              {t('email.card_outgoing_title')}
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">{t('email.card_outgoing_body')}</p>
          </div>
          <div className="rounded-lg border border-gray-200 bg-white/80 p-4 dark:border-gray-600 dark:bg-gray-800/50">
            <div className="mb-2 flex items-center gap-2 font-medium text-gray-900 dark:text-white">
              <Server className="h-4 w-4 text-emerald-500" />
              {t('email.card_panel_mail_title')}
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">{t('email.card_panel_mail_body')}</p>
            {isAdmin && (
              <Link
                to="/admin/mail-settings"
                className="mt-3 inline-flex text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
              >
                {t('email.admin_mail_cta')} →
              </Link>
            )}
          </div>
        </div>
        <div className="mt-4 rounded-lg border border-violet-200 bg-violet-50/80 p-4 dark:border-violet-900/50 dark:bg-violet-950/30">
          <p className="text-sm font-medium text-violet-900 dark:text-violet-100">{t('email.webmail_note_title')}</p>
          <p className="mt-1 text-sm text-violet-800/90 dark:text-violet-200/90">{t('email.webmail_note_body')}</p>
        </div>
      </section>
    </div>
  )
}
