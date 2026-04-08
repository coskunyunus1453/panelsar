import { createPortal } from 'react-dom'
import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Archive, FileArchive } from 'lucide-react'
import clsx from 'clsx'

type Props = {
  open: boolean
  kind: 'zip' | 'unzip' | null
  /** API tamamlandığında true — çubuk %100’e gider ve kısa süre sonra overlay kapanır */
  complete: boolean
}

/** Sunucu yanıtı gelene kadar üst sınıra yaklaşan yumuşak ilerleme (süre gerçek; yüzde tahmini). */
function easeProgress(seconds: number): number {
  return Math.min(94, 100 * (1 - Math.exp(-seconds / 2.15)))
}

export default function FileArchiveProgressOverlay({ open, kind, complete }: Props) {
  const { t } = useTranslation()
  const [pct, setPct] = useState(0)
  const startRef = useRef<number | null>(null)
  const rafRef = useRef<number>(0)

  useEffect(() => {
    if (!open || !kind) {
      setPct(0)
      startRef.current = null
      return
    }
    if (complete) {
      setPct(100)
      return
    }
    startRef.current = performance.now()
    const tick = () => {
      if (startRef.current == null) return
      const sec = (performance.now() - startRef.current) / 1000
      setPct(easeProgress(sec))
      rafRef.current = requestAnimationFrame(tick)
    }
    rafRef.current = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(rafRef.current)
  }, [open, kind, complete])

  if (typeof document === 'undefined') return null
  if (!open || !kind) return null

  const title = kind === 'zip' ? t('files.archive_progress_zip') : t('files.archive_progress_unzip')
  const subtitle =
    kind === 'zip' ? t('files.archive_progress_zip_hint') : t('files.archive_progress_unzip_hint')

  const ui = (
    <div
      className="fixed inset-0 z-[200] flex items-center justify-center p-4 sm:p-6"
      role="dialog"
      aria-modal="true"
      aria-labelledby="hostvim-archive-progress-title"
      aria-busy={!complete}
    >
      <div className="absolute inset-0 bg-slate-950/80 backdrop-blur-md" />
      <div
        className={clsx(
          'relative w-full max-w-md overflow-hidden rounded-2xl border border-white/10',
          'bg-gradient-to-b from-slate-900/95 via-slate-900 to-slate-950',
          'p-5 shadow-2xl shadow-violet-900/25 ring-1 ring-violet-500/20 sm:p-6',
        )}
      >
        <div className="pointer-events-none absolute -right-20 -top-20 h-44 w-44 rounded-full bg-violet-500/25 blur-3xl" />
        <div className="pointer-events-none absolute -bottom-16 -left-16 h-40 w-40 rounded-full bg-fuchsia-500/15 blur-3xl" />

        <div className="relative flex items-start gap-4">
          <div
            className={clsx(
              'flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-white shadow-lg',
              kind === 'zip'
                ? 'bg-gradient-to-br from-violet-500 to-violet-700 shadow-violet-500/35'
                : 'bg-gradient-to-br from-fuchsia-500 to-violet-600 shadow-fuchsia-500/30',
            )}
          >
            {kind === 'zip' ? <Archive className="h-7 w-7" /> : <FileArchive className="h-7 w-7" />}
          </div>
          <div className="min-w-0 flex-1 pt-0.5">
            <p
              id="hostvim-archive-progress-title"
              className="bg-gradient-to-r from-white to-slate-300 bg-clip-text text-lg font-bold tracking-tight text-transparent sm:text-xl"
            >
              {title}
            </p>
            <p className="text-sm font-medium text-violet-200/90">{subtitle}</p>
          </div>
        </div>

        <div className="relative mt-6 space-y-2">
          <div className="flex items-center justify-between gap-2 text-xs text-slate-400">
            <span className="tabular-nums text-slate-300">{Math.round(pct)}%</span>
            <span className="text-[11px] text-slate-500">{complete ? t('files.archive_finishing') : t('files.archive_wait')}</span>
          </div>
          <div className="relative h-3 overflow-hidden rounded-full bg-slate-800/90 ring-1 ring-white/5">
            <div
              className={clsx(
                'relative h-full overflow-hidden rounded-full bg-gradient-to-r transition-[width] duration-200 ease-out',
                kind === 'zip' ? 'from-violet-400 to-fuchsia-500' : 'from-fuchsia-400 to-violet-500',
                complete && 'shadow-[0_0_20px_rgba(167,139,250,0.45)]',
              )}
              style={{ width: `${Math.min(100, Math.max(2, pct))}%` }}
            >
              {!complete && (
                <span
                  className="absolute inset-0 block bg-gradient-to-r from-transparent via-white/30 to-transparent opacity-70"
                  style={{
                    backgroundSize: '200% 100%',
                    animation: 'hostvim-shimmer 1.35s ease-in-out infinite',
                  }}
                />
              )}
            </div>
          </div>
        </div>
      </div>
      <style>{`
        @keyframes hostvim-shimmer {
          0% { background-position: 120% 0; }
          100% { background-position: -120% 0; }
        }
      `}</style>
    </div>
  )

  return createPortal(ui, document.body)
}
