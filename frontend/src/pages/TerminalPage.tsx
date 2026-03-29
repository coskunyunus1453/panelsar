import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { TerminalSquare, AlertCircle } from 'lucide-react'
import { Terminal } from '@xterm/xterm'
import { FitAddon } from '@xterm/addon-fit'
import '@xterm/xterm/css/xterm.css'
import toast from 'react-hot-toast'

type SessionRes = { url: string; expires_in: number }

export default function TerminalPage() {
  const { t } = useTranslation()
  const user = useAuthStore((s) => s.user)
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')
  const containerRef = useRef<HTMLDivElement>(null)
  const [status, setStatus] = useState<'idle' | 'connecting' | 'open' | 'closed'>('idle')
  const cleanupRef = useRef<() => void>(() => {})

  const connect = () => {
    cleanupRef.current()
    cleanupRef.current = () => {}

    const el = containerRef.current
    if (!el || !isAdmin) return

    setStatus('connecting')
    el.innerHTML = ''

    void (async () => {
      try {
        const { data } = await api.post<SessionRes>('/terminal/session')
        const term = new Terminal({
          cursorBlink: true,
          fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
          fontSize: 13,
          theme: {
            background: '#0f172a',
            foreground: '#e2e8f0',
          },
        })
        const fit = new FitAddon()
        term.loadAddon(fit)
        term.open(el)
        fit.fit()

        const ws = new WebSocket(data.url)
        ws.binaryType = 'arraybuffer'
        let ro: ResizeObserver | undefined

        const onResize = () => {
          fit.fit()
          try {
            if (ws.readyState === WebSocket.OPEN) {
              ws.send(
                JSON.stringify({
                  type: 'resize',
                  cols: term.cols,
                  rows: term.rows,
                }),
              )
            }
          } catch {
            /* ignore */
          }
        }

        ws.onopen = () => {
          setStatus('open')
          onResize()
          ro = new ResizeObserver(onResize)
          ro.observe(el)
          term.onData((payload) => {
            if (ws.readyState !== WebSocket.OPEN) return
            ws.send(new TextEncoder().encode(payload))
          })
        }

        ws.onmessage = (ev) => {
          if (typeof ev.data === 'string') return
          const buf = ev.data as ArrayBuffer
          term.write(new Uint8Array(buf))
        }

        ws.onerror = () => {
          toast.error(t('terminal.ws_error'))
        }

        ws.onclose = () => {
          ro?.disconnect()
          setStatus('closed')
          term.writeln('')
          term.writeln(`\r\n\x1b[33m${t('terminal.disconnected')}\x1b[0m`)
        }

        cleanupRef.current = () => {
          ro?.disconnect()
          setStatus('closed')
          try {
            ws.close()
          } catch {
            /* ignore */
          }
          term.dispose()
        }
      } catch (err: unknown) {
        setStatus('closed')
        const ax = err as { response?: { data?: { message?: string } } }
        toast.error(ax.response?.data?.message ?? String(err))
      }
    })()
  }

  useEffect(() => {
    if (!isAdmin) return
    connect()
    return () => cleanupRef.current()
    // eslint-disable-next-line react-hooks/exhaustive-deps -- ilk mount’ta tek bağlantı
  }, [isAdmin])

  if (!isAdmin) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <TerminalSquare className="h-8 w-8 text-indigo-500" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
              {t('terminal.title')}
            </h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm max-w-2xl">
              {t('terminal.subtitle')}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <span
            className={`rounded-full px-2 py-0.5 text-xs font-medium ${
              status === 'open'
                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                : status === 'connecting'
                  ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                  : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
            }`}
          >
            {status === 'open'
              ? t('terminal.status_online')
              : status === 'connecting'
                ? t('terminal.status_connecting')
                : t('terminal.status_offline')}
          </span>
          <button type="button" className="btn-secondary text-sm" onClick={connect}>
            {t('terminal.reconnect')}
          </button>
        </div>
      </div>

      <div className="flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
        <AlertCircle className="h-5 w-5 shrink-0" />
        <p>{t('terminal.warning')}</p>
      </div>

      <div
        ref={containerRef}
        className="h-[min(70vh,560px)] w-full overflow-hidden rounded-lg border border-gray-200 bg-slate-950 p-2 dark:border-gray-700"
      />
    </div>
  )
}
