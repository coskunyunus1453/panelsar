import { useCallback, useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { TerminalSquare, AlertCircle } from 'lucide-react'
import { Terminal } from '@xterm/xterm'
import { FitAddon } from '@xterm/addon-fit'
import '@xterm/xterm/css/xterm.css'
import toast from 'react-hot-toast'
import { safeWebSocketUrl } from '../lib/urlSafety'

type SessionRes = { url: string; token?: string; expires_in: number }
type TerminalSettings = { use_root: boolean }

export default function TerminalPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const isAdmin = user?.roles?.some((r) => r.name === 'admin')
  const containerRef = useRef<HTMLDivElement>(null)
  const [status, setStatus] = useState<'idle' | 'connecting' | 'open' | 'closed'>('idle')
  const cleanupRef = useRef<() => void>(() => {})
  const tRef = useRef(t)
  tRef.current = t

  const settingsQ = useQuery({
    queryKey: ['admin', 'settings', 'terminal'],
    enabled: isAdmin === true,
    queryFn: async () => (await api.get<TerminalSettings>('/admin/settings/terminal')).data,
  })

  const saveRootM = useMutation({
    mutationFn: async (use_root: boolean) => {
      await api.put('/admin/settings/terminal', { use_root })
    },
    onSuccess: () => {
      toast.success(t('terminal.settings_saved_reconnect'))
      void qc.invalidateQueries({ queryKey: ['admin', 'settings', 'terminal'] })
      cleanupRef.current()
      setStatus('closed')
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
      void qc.invalidateQueries({ queryKey: ['admin', 'settings', 'terminal'] })
    },
  })

  const connect = useCallback(() => {
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

        const wsUrl = safeWebSocketUrl(data.url)
        if (!wsUrl) {
          throw new Error('Güvensiz terminal websocket URL')
        }
        const wsToken = String(data.token || '').trim()
        const protocols = wsToken ? [`panelsar.jwt.${wsToken}`] : undefined
        const ws = protocols ? new WebSocket(wsUrl, protocols) : new WebSocket(wsUrl)
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
          toast.error(tRef.current('terminal.ws_error'))
        }

        ws.onclose = () => {
          ro?.disconnect()
          setStatus('closed')
          term.writeln('')
          term.writeln(`\r\n\x1b[33m${tRef.current('terminal.disconnected')}\x1b[0m`)
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
  }, [isAdmin])

  useEffect(() => {
    if (!isAdmin) return
    connect()
    return () => cleanupRef.current()
  }, [isAdmin, connect])

  if (!isAdmin) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="space-y-5">
      <div className="card p-4 sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-indigo-500/10 p-2.5">
              <TerminalSquare className="h-7 w-7 text-indigo-500" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                {t('terminal.title')}
              </h1>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <span
              className={`rounded-full px-2.5 py-1 text-xs font-medium ${
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
      </div>

      <div className="card p-2">
        <div
          ref={containerRef}
          className="h-[min(72vh,620px)] w-full overflow-hidden rounded-lg border border-gray-200 bg-slate-950 p-2 dark:border-gray-700"
        />
      </div>

      <div className="space-y-3">
        <div className="card p-4">
          <label className="flex cursor-pointer items-start gap-3">
            <input
              type="checkbox"
              className="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
              checked={settingsQ.data?.use_root ?? true}
              disabled={settingsQ.isLoading || saveRootM.isPending}
              onChange={(e) => saveRootM.mutate(e.target.checked)}
            />
            <span>
              <span className="font-medium text-gray-900 dark:text-white">{t('terminal.use_root_label')}</span>
              <span className="mt-1 block text-sm text-gray-500 dark:text-gray-400">
                {t('terminal.use_root_hint')}
              </span>
            </span>
          </label>
        </div>

        <div className="flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <div>
            <p>{settingsQ.data?.use_root ?? true ? t('terminal.warning') : t('terminal.warning_limited')}</p>
          </div>
        </div>
      </div>
    </div>
  )
}
