import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import {
  BookOpen,
  Clock,
  Info,
  Pencil,
  Plus,
  Trash2,
  Wand2,
} from 'lucide-react'
import toast from 'react-hot-toast'
import clsx from 'clsx'
import { CRON_PRESETS, joinCronFields, parseCronFields, presetIdForSchedule } from '../utils/cronHumanize'

type CronRow = {
  id: number
  schedule: string
  command: string
  description: string | null
  status: string
  engine_job_id?: string | null
}

type QuotaSummary = {
  quota: { used: number; max: number | null; unlimited: boolean }
  timezone_hint: string
}

export default function CronPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [modal, setModal] = useState<'create' | 'edit' | null>(null)
  const [editing, setEditing] = useState<CronRow | null>(null)
  const [mode, setMode] = useState<'preset' | 'custom'>('preset')
  const [presetKey, setPresetKey] = useState('every_5')
  const [customLine, setCustomLine] = useState('*/5 * * * *')
  const [fields, setFields] = useState(['*/5', '*', '*', '*', '*'])
  const [command, setCommand] = useState('')
  const [description, setDescription] = useState('')

  const q = useQuery({
    queryKey: ['cron'],
    queryFn: async () => (await api.get('/cron')).data,
  })

  const sumQ = useQuery({
    queryKey: ['cron-summary'],
    queryFn: async () => (await api.get<QuotaSummary>('/cron/summary')).data,
  })

  const currentSchedule = useMemo(() => {
    if (mode === 'preset') {
      const p = CRON_PRESETS.find((x) => x.id === presetKey)
      return p?.schedule ?? '*/5 * * * *'
    }
    const lineParts = customLine.trim().split(/\s+/).filter(Boolean)
    if (lineParts.length === 5) {
      return lineParts.join(' ')
    }
    const f = fields.map((x) => x.trim() || '*')
    return joinCronFields(f)
  }, [mode, presetKey, fields, customLine])

  useEffect(() => {
    if (modal === 'edit' && editing) {
      setCommand(editing.command)
      setDescription(editing.description ?? '')
      const pid = presetIdForSchedule(editing.schedule)
      if (pid) {
        setMode('preset')
        setPresetKey(pid)
      } else {
        setMode('custom')
        setCustomLine(editing.schedule)
        const pr = parseCronFields(editing.schedule)
        if (pr) setFields(pr)
      }
    }
    if (modal === 'create') {
      setCommand('')
      setDescription('')
      setMode('preset')
      setPresetKey('every_5')
      setCustomLine('*/5 * * * *')
      setFields(['*/5', '*', '*', '*', '*'])
    }
  }, [modal, editing])

  const humanLabel = useMemo(() => {
    const pid = presetIdForSchedule(currentSchedule)
    if (pid) return t(`cron.presets.${pid}`)
    const p = parseCronFields(currentSchedule)
    if (!p) return t('cron.human_invalid')
    return t('cron.human_custom', {
      m: p[0],
      h: p[1],
      dom: p[2],
      mon: p[3],
      dow: p[4],
    })
  }, [currentSchedule, t])

  const createM = useMutation({
    mutationFn: async (payload: { schedule: string; command: string; description?: string }) =>
      api.post('/cron', payload),
    onSuccess: () => {
      toast.success(t('cron.created'))
      qc.invalidateQueries({ queryKey: ['cron'] })
      qc.invalidateQueries({ queryKey: ['cron-summary'] })
      setModal(null)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const updateM = useMutation({
    mutationFn: async (payload: { id: number; schedule: string; command: string; description?: string }) => {
      const { id, ...body } = payload
      return api.patch(`/cron/${id}`, body)
    },
    onSuccess: () => {
      toast.success(t('cron.updated'))
      qc.invalidateQueries({ queryKey: ['cron'] })
      setModal(null)
      setEditing(null)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const deleteM = useMutation({
    mutationFn: async (id: number) => api.delete(`/cron/${id}`),
    onSuccess: () => {
      toast.success(t('cron.deleted'))
      qc.invalidateQueries({ queryKey: ['cron'] })
      qc.invalidateQueries({ queryKey: ['cron-summary'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const rows: CronRow[] = q.data?.data ?? []
  const quota = sumQ.data?.quota

  const openCreate = () => {
    setEditing(null)
    setModal('create')
  }

  const openEdit = (job: CronRow) => {
    setEditing(job)
    setModal('edit')
  }

  const submitForm = () => {
    const parts = currentSchedule.trim().split(/\s+/).filter(Boolean)
    if (parts.length !== 5) {
      toast.error(t('cron.invalid_schedule_short'))
      return
    }
    const payload = {
      schedule: parts.join(' '),
      command: command.trim(),
      description: description.trim() || undefined,
    }
    if (modal === 'edit' && editing) {
      updateM.mutate({ id: editing.id, ...payload })
    } else {
      createM.mutate(payload)
    }
  }

  const insertSnippet = (snippet: string) => {
    setCommand((c) => (c ? `${c.trimEnd()}\n${snippet}` : snippet))
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Clock className="h-8 w-8 text-orange-500" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.cron')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm">{t('cron.subtitle')}</p>
          </div>
        </div>
        <button type="button" className="btn-primary flex items-center gap-2" onClick={openCreate}>
          <Plus className="h-4 w-4" />
          {t('cron.add_task')}
        </button>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="card p-5 space-y-3 border-l-4 border-primary-500">
          <div className="flex items-center gap-2 font-semibold text-gray-900 dark:text-white">
            <Info className="h-5 w-5 text-primary-500" />
            {t('cron.info_title')}
          </div>
          <ul className="list-disc space-y-2 pl-5 text-sm text-gray-600 dark:text-gray-400">
            <li>{t('cron.info_fields')}</li>
            <li>{t('cron.info_timezone', { tz: sumQ.data?.timezone_hint ?? 'UTC' })}</li>
            <li>{t('cron.info_engine')}</li>
            <li>{t('cron.info_paths')}</li>
          </ul>
        </div>
        <div className="card p-5 space-y-2">
          <div className="flex items-center gap-2 font-semibold text-gray-900 dark:text-white">
            <BookOpen className="h-5 w-5 text-amber-500" />
            {t('cron.examples_title')}
          </div>
          <pre className="overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100 dark:bg-black/40">
            {t('cron.examples_body')}
          </pre>
        </div>
      </div>

      {quota && (
        <div
          className={clsx(
            'rounded-lg border px-4 py-3 text-sm',
            quota.unlimited
              ? 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50'
              : quota.max !== null && quota.used >= quota.max
                ? 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30'
                : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900',
          )}
        >
          {quota.unlimited ? (
            <span>{t('cron.quota_unlimited', { used: quota.used })}</span>
          ) : (
            <span>
              {t('cron.quota_count', { used: quota.used, max: quota.max ?? 0 })}
            </span>
          )}
        </div>
      )}

      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-800/80">
            <tr>
              <th className="text-left px-4 py-2">{t('cron.col_schedule')}</th>
              <th className="text-left px-4 py-2">{t('cron.col_human')}</th>
              <th className="text-left px-4 py-2">{t('cron.col_command')}</th>
              <th className="text-left px-4 py-2">{t('cron.col_note')}</th>
              <th className="text-left px-4 py-2">{t('cron.col_engine')}</th>
              <th className="text-right px-4 py-2">{t('common.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((job) => {
              const pid = presetIdForSchedule(job.schedule)
              const human = pid
                ? t(`cron.presets.${pid}`)
                : (() => {
                    const p = parseCronFields(job.schedule)
                    return p
                      ? t('cron.human_custom', {
                          m: p[0],
                          h: p[1],
                          dom: p[2],
                          mon: p[3],
                          dow: p[4],
                        })
                      : t('cron.human_invalid')
                  })()
              return (
                <tr key={job.id} className="border-t border-gray-100 dark:border-gray-800">
                  <td className="px-4 py-2 font-mono text-xs whitespace-nowrap">{job.schedule}</td>
                  <td className="px-4 py-2 text-gray-700 dark:text-gray-300 max-w-[200px]">{human}</td>
                  <td className="px-4 py-2 font-mono text-xs break-all max-w-md">{job.command}</td>
                  <td className="px-4 py-2 text-gray-600 dark:text-gray-400 max-w-[140px] truncate">
                    {job.description ?? '—'}
                  </td>
                  <td className="px-4 py-2 font-mono text-xs text-gray-500">
                    {job.engine_job_id?.trim() ? job.engine_job_id : '—'}
                  </td>
                  <td className="px-4 py-2 text-right whitespace-nowrap">
                    <button
                      type="button"
                      className="mr-1 inline-flex p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800"
                      title={t('common.edit')}
                      onClick={() => openEdit(job)}
                    >
                      <Pencil className="h-4 w-4" />
                    </button>
                    <button
                      type="button"
                      className="inline-flex p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20"
                      title={t('common.delete')}
                      onClick={() => {
                        if (window.confirm(t('common.confirm_delete'))) deleteM.mutate(job.id)
                      }}
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
        {q.isLoading && <p className="p-6 text-center text-gray-500">{t('common.loading')}</p>}
        {!q.isLoading && rows.length === 0 && (
          <p className="p-6 text-center text-gray-500">{t('cron.empty_hint')}</p>
        )}
      </div>

      {modal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
          <div className="card my-8 max-w-2xl w-full p-6 space-y-4 bg-white dark:bg-gray-900 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center gap-2">
              <Wand2 className="h-5 w-5 text-primary-500" />
              <h2 className="text-lg font-semibold">
                {modal === 'edit' ? t('cron.modal_edit') : t('cron.modal_create')}
              </h2>
            </div>

            <div className="flex gap-2 flex-wrap">
              <button
                type="button"
                className={clsx(
                  'rounded-lg px-3 py-1.5 text-sm font-medium',
                  mode === 'preset'
                    ? 'bg-primary-600 text-white'
                    : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
                )}
                onClick={() => setMode('preset')}
              >
                {t('cron.mode_preset')}
              </button>
              <button
                type="button"
                className={clsx(
                  'rounded-lg px-3 py-1.5 text-sm font-medium',
                  mode === 'custom'
                    ? 'bg-primary-600 text-white'
                    : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
                )}
                onClick={() => setMode('custom')}
              >
                {t('cron.mode_custom')}
              </button>
            </div>

            {mode === 'preset' ? (
              <div>
                <label className="label">{t('cron.preset_label')}</label>
                <select
                  className="input w-full"
                  value={presetKey}
                  onChange={(e) => setPresetKey(e.target.value)}
                >
                  {CRON_PRESETS.map((p) => (
                    <option key={p.id} value={p.id}>
                      {t(`cron.presets.${p.id}`)} ({p.schedule})
                    </option>
                  ))}
                </select>
              </div>
            ) : (
              <div className="space-y-3">
                <div>
                  <label className="label">{t('cron.custom_line')}</label>
                  <input
                    className="input w-full font-mono"
                    value={customLine}
                    onChange={(e) => {
                      const v = e.target.value
                      setCustomLine(v)
                      const p = v.trim().split(/\s+/).filter(Boolean)
                      if (p.length === 5) {
                        setFields(p)
                      }
                    }}
                    placeholder="0 0 * * *"
                  />
                </div>
                <p className="text-xs text-gray-500">{t('cron.custom_fields_hint')}</p>
                <div className="grid grid-cols-2 sm:grid-cols-5 gap-2">
                  {(['m', 'h', 'dom', 'mon', 'dow'] as const).map((key, i) => (
                    <div key={key}>
                      <label className="label text-xs">{t(`cron.field_${key}`)}</label>
                      <input
                        className="input w-full font-mono text-sm"
                        value={fields[i] ?? '*'}
                        onChange={(e) => {
                          const next = [...fields]
                          next[i] = e.target.value
                          setFields(next)
                          setCustomLine(joinCronFields([next[0], next[1], next[2], next[3], next[4]]))
                        }}
                      />
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div className="rounded-lg bg-primary-50 dark:bg-primary-900/20 px-3 py-2 text-sm text-primary-900 dark:text-primary-100">
              <span className="font-mono font-semibold">{currentSchedule}</span>
              <span className="mx-2">—</span>
              {humanLabel}
            </div>

            <div>
              <label className="label">{t('cron.command_label')}</label>
              <textarea
                className="input w-full min-h-[100px] font-mono text-sm"
                value={command}
                onChange={(e) => setCommand(e.target.value)}
                required
              />
              <div className="mt-2 flex flex-wrap gap-2">
                <button
                  type="button"
                  className="rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-600"
                  onClick={() =>
                    insertSnippet(
                      '/usr/bin/php /var/www/ORNEK/public_html/artisan schedule:run >> /dev/null 2>&1',
                    )
                  }
                >
                  {t('cron.snippet_laravel')}
                </button>
                <button
                  type="button"
                  className="rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-600"
                  onClick={() => insertSnippet('wget -q -O - https://ornek.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1')}
                >
                  {t('cron.snippet_wp')}
                </button>
                <button
                  type="button"
                  className="rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-600"
                  onClick={() => insertSnippet('/usr/bin/php /path/to/script.php')}
                >
                  {t('cron.snippet_php')}
                </button>
              </div>
            </div>

            <div>
              <label className="label">{t('cron.description_label')}</label>
              <input
                className="input w-full"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                className="btn-secondary"
                onClick={() => {
                  setModal(null)
                  setEditing(null)
                }}
              >
                {t('common.cancel')}
              </button>
              <button
                type="button"
                className="btn-primary"
                disabled={createM.isPending || updateM.isPending || !command.trim()}
                onClick={submitForm}
              >
                {modal === 'edit' ? t('common.save') : t('common.create')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
