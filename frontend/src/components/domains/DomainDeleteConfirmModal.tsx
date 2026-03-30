import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import api from '../../services/api'
import toast from 'react-hot-toast'
import { X, AlertTriangle, Trash2, Loader2 } from 'lucide-react'

export type DomainDeleteTarget = {
  id: number
  name: string
}

type Props = {
  open: boolean
  domain: DomainDeleteTarget | null
  onClose: () => void
  /** Silme başarılı olunca (liste yenilenir); ek olarak drawer/modal kapatmak için */
  onDeleted?: () => void
}

export default function DomainDeleteConfirmModal({ open, domain, onClose, onDeleted }: Props) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [phrase, setPhrase] = useState('')

  useEffect(() => {
    if (open) setPhrase('')
  }, [open, domain?.id])

  const deleteM = useMutation({
    mutationFn: async () => {
      if (!domain) return
      await api.delete(`/domains/${domain.id}`, {
        data: { confirmation: phrase.trim() },
      })
    },
    onSuccess: () => {
      toast.success(t('domains.deleted'))
      void qc.invalidateQueries({ queryKey: ['domains'] })
      onDeleted?.()
      onClose()
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  if (!open || !domain) return null

  const expectedPhrase = t('domains.delete_confirm_expected')

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
      <div
        className="card max-w-lg w-full space-y-4 bg-white p-6 dark:bg-gray-900"
        role="dialog"
        aria-modal="true"
        aria-labelledby="domain-delete-title"
      >
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-950/60">
              <Trash2 className="h-5 w-5 text-red-700 dark:text-red-400" />
            </div>
            <div>
              <h2
                id="domain-delete-title"
                className="text-lg font-semibold text-gray-900 dark:text-white"
              >
                {t('domains.delete_modal_title')}
              </h2>
              <p className="mt-1 font-mono text-sm text-primary-600 dark:text-primary-400">
                {domain.name}
              </p>
            </div>
          </div>
          <button
            type="button"
            className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
            aria-label={t('common.cancel')}
            onClick={onClose}
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <p className="text-sm text-amber-800 dark:text-amber-200/90">{t('domains.delete_modal_lead')}</p>

        <div className="flex gap-2 rounded-lg border border-red-200 bg-red-50/60 p-3 text-red-900 dark:border-red-900/40 dark:bg-red-950/25 dark:text-red-100">
          <AlertTriangle className="h-5 w-5 shrink-0" />
          <p className="text-sm leading-relaxed">{t('domains.delete_warning')}</p>
        </div>

        <div className="space-y-2">
          <p className="text-xs text-gray-600 dark:text-gray-400">{t('domains.delete_type_phrase')}</p>
          <code className="block rounded bg-gray-100 px-2 py-1.5 text-sm font-mono dark:bg-gray-800">
            {expectedPhrase}
          </code>
          <input
            className="input w-full font-mono text-sm"
            value={phrase}
            onChange={(e) => setPhrase(e.target.value)}
            placeholder={expectedPhrase}
            autoComplete="off"
          />
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
          <button type="button" className="btn-secondary" onClick={onClose}>
            {t('common.cancel')}
          </button>
          <button
            type="button"
            className="inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
            disabled={deleteM.isPending}
            onClick={() => deleteM.mutate()}
          >
            {deleteM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
            {t('domains.delete_forever')}
          </button>
        </div>
      </div>
    </div>
  )
}
