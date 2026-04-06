import { createPortal } from 'react-dom'
import { useTranslation } from 'react-i18next'
import { Zap, UploadCloud } from 'lucide-react'
import clsx from 'clsx'

export type FileUploadProgressView = {
  totalFiles: number
  currentIndex: number
  currentName: string
  currentLoaded: number
  currentTotal: number
  overallLoaded: number
  overallTotal: number
  speedBps: number
}

function fmtRate(bps: number): string {
  if (!Number.isFinite(bps) || bps <= 0) return '—'
  if (bps < 1024) return `${Math.round(bps)} B`
  if (bps < 1024 * 1024) return `${(bps / 1024).toFixed(1)} KB`
  return `${(bps / (1024 * 1024)).toFixed(2)} MB`
}

function fmtEta(sec: number): string {
  if (!Number.isFinite(sec) || sec < 0 || sec > 86400) return '…'
  if (sec < 90) return `${Math.max(1, Math.ceil(sec))}s`
  const m = Math.floor(sec / 60)
  const s = Math.ceil(sec % 60)
  return `${m}m ${s}s`
}

type Props = {
  open: boolean
  state: FileUploadProgressView | null
}

export default function FileUploadProgressOverlay({ open, state }: Props) {
  const { t } = useTranslation()

  if (typeof document === 'undefined') return null
  if (!open || !state) return null

  const pct = Math.min(
    100,
    state.overallTotal > 0 ? Math.round((state.overallLoaded / state.overallTotal) * 100) : 0,
  )
  const filePct =
    state.currentTotal > 0
      ? Math.min(100, Math.round((state.currentLoaded / state.currentTotal) * 100))
      : 0
  const remaining = Math.max(0, state.overallTotal - state.overallLoaded)
  const etaSec = state.speedBps > 500 ? remaining / state.speedBps : NaN

  const ui = (
    <div
      className="fixed inset-0 z-[200] flex items-center justify-center p-4 sm:p-6"
      role="dialog"
      aria-modal="true"
      aria-labelledby="hostvim-upload-progress-title"
      aria-busy="true"
    >
      <div className="absolute inset-0 bg-slate-950/75 backdrop-blur-md" />
      <div
        className={clsx(
          'relative w-full max-w-md overflow-hidden rounded-2xl border border-white/10',
          'bg-gradient-to-b from-slate-900/95 via-slate-900 to-slate-950',
          'p-5 shadow-2xl shadow-primary-900/20 ring-1 ring-primary-500/25 sm:p-6',
        )}
      >
        <div
          className="pointer-events-none absolute -right-16 -top-16 h-40 w-40 rounded-full bg-primary-500/20 blur-3xl"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute -bottom-12 -left-12 h-36 w-36 rounded-full bg-cyan-500/15 blur-3xl"
          aria-hidden
        />

        <div className="relative flex items-start gap-4">
          <div className="relative flex-shrink-0">
            <div
              className={clsx(
                'flex h-14 w-14 items-center justify-center rounded-2xl',
                'bg-gradient-to-br from-primary-500 to-primary-700 text-white shadow-lg',
                'shadow-primary-500/40',
              )}
            >
              <UploadCloud className="h-7 w-7" strokeWidth={2} />
            </div>
            <div
              className="absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-lg border border-slate-800 bg-slate-900 text-amber-400 shadow-md"
              aria-hidden
            >
              <Zap className="h-4 w-4 animate-pulse" fill="currentColor" fillOpacity={0.35} />
            </div>
          </div>
          <div className="min-w-0 flex-1 pt-0.5">
            <p
              id="hostvim-upload-progress-title"
              className="bg-gradient-to-r from-white to-slate-300 bg-clip-text text-lg font-bold tracking-tight text-transparent sm:text-xl"
            >
              Hostvim
            </p>
            <p className="text-sm font-medium text-primary-300/90">{t('files.upload_progress_tagline')}</p>
            <p className="mt-1 truncate text-xs text-slate-400" title={state.currentName}>
              {t('files.upload_progress_file_label')}{' '}
              <span className="font-mono text-slate-200">{state.currentName || '—'}</span>
            </p>
          </div>
        </div>

        <div className="relative mt-5 space-y-2">
          <div className="flex items-center justify-between gap-2 text-xs text-slate-400">
            <span>
              {t('files.upload_progress_batch', {
                current: state.currentIndex + 1,
                total: state.totalFiles,
              })}
            </span>
            <span className="font-mono tabular-nums text-slate-300">{pct}%</span>
          </div>
          <div className="h-3 overflow-hidden rounded-full bg-slate-800/90 ring-1 ring-inset ring-white/5">
            <div
              className={clsx(
                'h-full rounded-full bg-gradient-to-r from-primary-500 via-cyan-500 to-emerald-400',
                'transition-[width] duration-200 ease-out',
                'relative overflow-hidden',
              )}
              style={{ width: `${pct}%` }}
            >
              <div
                className="hostvim-upload-shimmer absolute inset-0 bg-gradient-to-r from-transparent via-white/25 to-transparent"
                style={{ width: '55%' }}
              />
            </div>
          </div>
        </div>

        <div className="relative mt-3 space-y-1.5">
          <div className="flex items-center justify-between gap-2 text-[11px] uppercase tracking-wider text-slate-500">
            <span>{t('files.upload_progress_current_file_bar')}</span>
            <span className="font-mono tabular-nums normal-case text-slate-400">{filePct}%</span>
          </div>
          <div className="h-1.5 overflow-hidden rounded-full bg-slate-800/80">
            <div
              className="h-full rounded-full bg-slate-500/80 transition-[width] duration-150 ease-out"
              style={{ width: `${filePct}%` }}
            />
          </div>
        </div>

        <div className="relative mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-white/5 pt-4 text-sm">
          <div className="flex items-center gap-2 text-slate-400">
            <Zap className="h-4 w-4 text-amber-400/90" aria-hidden />
            <span>
              {t('files.upload_speed_label')}{' '}
              <span className="font-mono font-medium text-slate-200">{fmtRate(state.speedBps)}</span>
              <span className="text-slate-500">/s</span>
            </span>
          </div>
          <div className="text-slate-400">
            {t('files.upload_eta_label')}{' '}
            <span className="font-mono font-medium text-slate-200">{fmtEta(etaSec)}</span>
          </div>
        </div>
      </div>
      <style>{`
        @keyframes hostvim-shimmer {
          0% { transform: translateX(-120%); }
          100% { transform: translateX(320%); }
        }
        .hostvim-upload-shimmer {
          animation: hostvim-shimmer 1.15s ease-in-out infinite;
        }
      `}</style>
    </div>
  )

  return createPortal(ui, document.body)
}
