import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import {
  Area,
  AreaChart,
  Cell,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
} from 'recharts'
import type { SystemStats } from '../../types'
import { useThemeStore } from '../../store/themeStore'
import { Cpu, HardDrive, MemoryStick, X } from 'lucide-react'
import clsx from 'clsx'

const DONUT_FREE_LIGHT = '#d1d5db'
const DONUT_FREE_DARK = '#475569'

const COL_CPU = ['#22c55e', '#eab308', '#ef4444']
const COL_MEM = ['#3b82f6', '#6366f1', '#a855f7']
const COL_DISK = ['#f97316', '#ea580c', '#c2410c']

function formatBytes(n?: number): string {
  if (n == null || Number.isNaN(n)) return '—'
  const u = ['B', 'KB', 'MB', 'GB', 'TB']
  let v = n
  let i = 0
  while (v >= 1024 && i < u.length - 1) {
    v /= 1024
    i++
  }
  return `${v < 10 && i > 0 ? v.toFixed(1) : Math.round(v)} ${u[i]}`
}

function donutData(percent: number) {
  const p = Math.min(100, Math.max(0, percent))
  return [
    { name: 'used', value: p },
    { name: 'free', value: 100 - p },
  ]
}

function colorForPercent(p: number, colors: string[]): string {
  if (p >= 85) return colors[2]
  if (p >= 65) return colors[1]
  return colors[0]
}

type DetailKind = 'cpu' | 'ram' | 'disk' | null

export default function ResourceChartsSection({
  stats,
  loading,
}: {
  stats?: SystemStats
  loading: boolean
}) {
  const { t } = useTranslation()
  const isDark = useThemeStore((s) => s.isDark)
  const donutFreeFill = isDark ? DONUT_FREE_DARK : DONUT_FREE_LIGHT
  const [cpuHist, setCpuHist] = useState<{ i: number; v: number }[]>([])
  const [memHist, setMemHist] = useState<{ i: number; v: number }[]>([])
  const [diskHist, setDiskHist] = useState<{ i: number; v: number }[]>([])
  const [detail, setDetail] = useState<DetailKind>(null)

  const cpu = stats?.cpu_usage ?? 0
  const mem = stats?.memory_percent ?? 0
  const disk = stats?.disk_percent ?? 0

  useEffect(() => {
    if (stats == null) return
    const ts = Date.now()
    setCpuHist((h) => [...h.slice(-28), { i: ts, v: Math.round(cpu) }])
    setMemHist((h) => [...h.slice(-28), { i: ts, v: Math.round(mem) }])
    setDiskHist((h) => [...h.slice(-28), { i: ts, v: Math.round(disk) }])
  }, [stats, cpu, mem, disk])

  const cpuFill = colorForPercent(cpu, COL_CPU)
  const memFill = colorForPercent(mem, COL_MEM)
  const diskFill = colorForPercent(disk, COL_DISK)

  if (loading && !stats) {
    return (
      <div className="card p-8 text-center text-gray-500 dark:text-gray-400">
        {t('common.loading')}
      </div>
    )
  }

  if (!stats) {
    return null
  }

  const Card = ({
    title,
    icon: Icon,
    percent,
    fill,
    hist,
    onOpen,
    accent,
    gradientId,
  }: {
    title: string
    icon: typeof Cpu
    percent: number
    fill: string
    hist: { i: number; v: number }[]
    onOpen: () => void
    accent: string
    gradientId: string
  }) => (
    <button
      type="button"
      onClick={onOpen}
      className={clsx(
        'card group w-full p-4 text-left transition-all hover:ring-2 hover:ring-offset-2 hover:ring-offset-white dark:hover:ring-offset-gray-900',
        accent,
      )}
    >
      <div className="flex items-start justify-between gap-2">
        <div className="flex items-center gap-2">
          <div className="rounded-lg bg-gray-100 p-2 dark:bg-gray-800">
            <Icon className="h-5 w-5 text-gray-700 dark:text-gray-200" />
          </div>
          <div>
            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">{title}</p>
            <p className="text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
              {Math.round(percent)}%
            </p>
          </div>
        </div>
        <div className="h-20 w-20 shrink-0">
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie
                data={donutData(percent)}
                dataKey="value"
                cx="50%"
                cy="50%"
                innerRadius={26}
                outerRadius={36}
                startAngle={90}
                endAngle={-270}
                strokeWidth={0}
                isAnimationActive
                animationDuration={900}
                animationEasing="ease-out"
              >
                <Cell fill={fill} />
                <Cell fill={donutFreeFill} />
              </Pie>
            </PieChart>
          </ResponsiveContainer>
        </div>
      </div>
      <div className="mt-3 h-16 w-full">
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={hist} margin={{ top: 4, right: 0, left: 0, bottom: 0 }}>
            <defs>
              <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor={fill} stopOpacity={0.35} />
                <stop offset="100%" stopColor={fill} stopOpacity={0} />
              </linearGradient>
            </defs>
            <XAxis dataKey="i" hide />
            <Tooltip
              formatter={(v: number) => [`${v}%`, t('dashboard.chart_usage')]}
              contentStyle={{ fontSize: 12, borderRadius: 8 }}
            />
            <Area
              type="monotone"
              dataKey="v"
              stroke={fill}
              strokeWidth={2}
              fill={`url(#${gradientId})`}
              isAnimationActive
              animationDuration={600}
            />
          </AreaChart>
        </ResponsiveContainer>
      </div>
      <p className="mt-2 text-xs text-primary-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-primary-400">
        {t('dashboard.click_detail')}
      </p>
    </button>
  )

  return (
    <>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <Card
          title={t('dashboard.cpu_usage')}
          icon={Cpu}
          percent={cpu}
          fill={cpuFill}
          hist={cpuHist}
          onOpen={() => setDetail('cpu')}
          accent="hover:ring-primary-400/60"
          gradientId="dash-spark-cpu"
        />
        <Card
          title={t('dashboard.memory_usage')}
          icon={MemoryStick}
          percent={mem}
          fill={memFill}
          hist={memHist}
          onOpen={() => setDetail('ram')}
          accent="hover:ring-blue-400/60"
          gradientId="dash-spark-mem"
        />
        <Card
          title={t('dashboard.disk_usage')}
          icon={HardDrive}
          percent={disk}
          fill={diskFill}
          hist={diskHist}
          onOpen={() => setDetail('disk')}
          accent="hover:ring-orange-400/60"
          gradientId="dash-spark-disk"
        />
      </div>

      {detail && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
          role="dialog"
          aria-modal="true"
        >
          <div className="card max-h-[85vh] w-full max-w-lg overflow-y-auto bg-white p-6 dark:bg-gray-900">
            <div className="mb-4 flex items-center justify-between">
              <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                {detail === 'cpu' && t('dashboard.detail_cpu_title')}
                {detail === 'ram' && t('dashboard.detail_ram_title')}
                {detail === 'disk' && t('dashboard.detail_disk_title')}
              </h3>
              <button
                type="button"
                className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                aria-label={t('common.cancel')}
                onClick={() => setDetail(null)}
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {detail === 'cpu' && (
              <div className="space-y-4 text-sm">
                <p className="text-gray-600 dark:text-gray-400">{t('dashboard.detail_cpu_intro')}</p>
                <dl className="grid gap-2 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/80">
                  <div className="flex justify-between gap-2">
                    <dt className="text-gray-500">{t('dashboard.cpu_model')}</dt>
                    <dd className="text-right font-medium text-gray-900 dark:text-white">
                      {stats.cpu_model?.trim() || '—'}
                    </dd>
                  </div>
                  <div className="flex justify-between gap-2">
                    <dt className="text-gray-500">{t('dashboard.cpu_cores')}</dt>
                    <dd className="text-right font-mono">{stats.cpu_cores_logical ?? '—'}</dd>
                  </div>
                  <div className="flex justify-between gap-2">
                    <dt className="text-gray-500">{t('dashboard.load_now')}</dt>
                    <dd className="text-right font-mono">{Math.round(cpu)}%</dd>
                  </div>
                </dl>
                <h4 className="font-medium text-gray-900 dark:text-white">
                  {t('dashboard.top3_cpu')}
                </h4>
                <ol className="space-y-2">
                  {(stats.top_cpu_processes ?? []).slice(0, 3).map((p, i) => (
                    <li
                      key={`${p.pid}-${i}`}
                      className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-gray-100 px-3 py-2 dark:border-gray-800"
                    >
                      <span className="font-mono text-xs text-gray-500">#{p.pid}</span>
                      <span className="min-w-0 flex-1 truncate text-gray-800 dark:text-gray-200">
                        {p.name}
                      </span>
                      <span className="font-mono text-sm font-semibold text-primary-600 dark:text-primary-400">
                        {p.cpu_percent.toFixed(1)}% CPU
                      </span>
                    </li>
                  ))}
                  {(stats.top_cpu_processes ?? []).length === 0 && (
                    <li className="text-gray-500">{t('dashboard.no_process_data')}</li>
                  )}
                </ol>
              </div>
            )}

            {detail === 'ram' && (
              <div className="space-y-4 text-sm">
                <p className="text-gray-600 dark:text-gray-400">{t('dashboard.detail_ram_intro')}</p>
                <dl className="grid gap-2 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/80">
                  <div className="flex justify-between gap-2">
                    <dt className="text-gray-500">{t('dashboard.ram_total')}</dt>
                    <dd className="font-mono">{formatBytes(stats.memory_total)}</dd>
                  </div>
                  <div className="flex justify-between gap-2">
                    <dt className="text-gray-500">{t('dashboard.ram_used')}</dt>
                    <dd className="font-mono">{formatBytes(stats.memory_used)}</dd>
                  </div>
                  <div className="flex justify-between gap-2">
                    <dt className="text-gray-500">{t('dashboard.ram_available')}</dt>
                    <dd className="font-mono">{formatBytes(stats.memory_available)}</dd>
                  </div>
                  <div className="flex justify-between gap-2">
                    <dt className="text-gray-500">{t('dashboard.ram_percent')}</dt>
                    <dd className="font-mono">{Math.round(mem)}%</dd>
                  </div>
                  <div className="flex justify-between gap-2">
                    <dt className="text-gray-500">{t('dashboard.swap')}</dt>
                    <dd className="font-mono text-right">
                      {formatBytes(stats.swap_used)} / {formatBytes(stats.swap_total)}
                      {stats.swap_percent != null && stats.swap_total ? (
                        <span className="ml-1 text-gray-500">
                          ({Math.round(stats.swap_percent)}%)
                        </span>
                      ) : null}
                    </dd>
                  </div>
                </dl>
                <h4 className="font-medium text-gray-900 dark:text-white">
                  {t('dashboard.top3_ram')}
                </h4>
                <ol className="space-y-2">
                  {(stats.top_memory_processes ?? []).slice(0, 3).map((p, i) => (
                    <li
                      key={`${p.pid}-${i}`}
                      className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-gray-100 px-3 py-2 dark:border-gray-800"
                    >
                      <span className="font-mono text-xs text-gray-500">#{p.pid}</span>
                      <span className="min-w-0 flex-1 truncate text-gray-800 dark:text-gray-200">
                        {p.name}
                      </span>
                      <span className="font-mono text-sm font-semibold text-blue-600 dark:text-blue-400">
                        {formatBytes(p.rss_bytes)}
                      </span>
                    </li>
                  ))}
                  {(stats.top_memory_processes ?? []).length === 0 && (
                    <li className="text-gray-500">{t('dashboard.no_process_data')}</li>
                  )}
                </ol>
              </div>
            )}

            {detail === 'disk' && (
              <div className="space-y-4 text-sm">
                <p className="text-gray-600 dark:text-gray-400">{t('dashboard.detail_disk_intro')}</p>
                <dl className="grid gap-2 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/80">
                  <div className="flex justify-between gap-2">
                    <dt className="text-gray-500">{t('dashboard.disk_panel_mount')}</dt>
                    <dd className="text-right font-mono text-xs">
                      {formatBytes(stats.disk_used)} / {formatBytes(stats.disk_total)} ({Math.round(disk)}%)
                    </dd>
                  </div>
                </dl>
                <h4 className="font-medium text-gray-900 dark:text-white">
                  {t('dashboard.top3_disk')}
                </h4>
                <p className="text-xs text-gray-500">{t('dashboard.top3_disk_hint')}</p>
                <ol className="space-y-2">
                  {(stats.top_disk_mounts ?? []).slice(0, 3).map((m, i) => (
                    <li
                      key={`${m.path}-${i}`}
                      className="rounded-md border border-gray-100 px-3 py-2 dark:border-gray-800"
                    >
                      <div className="font-mono text-xs text-gray-500">{m.path}</div>
                      {m.fstype ? (
                        <div className="text-xs text-gray-400">{m.fstype}</div>
                      ) : null}
                      <div className="mt-1 flex justify-between font-mono text-sm">
                        <span>
                          {formatBytes(m.used_bytes)} / {formatBytes(m.total_bytes)}
                        </span>
                        <span className="font-semibold text-orange-600 dark:text-orange-400">
                          {Math.round(m.used_percent)}%
                        </span>
                      </div>
                    </li>
                  ))}
                  {(stats.top_disk_mounts ?? []).length === 0 && (
                    <li className="text-gray-500">{t('dashboard.no_mount_data')}</li>
                  )}
                </ol>
              </div>
            )}
          </div>
        </div>
      )}
    </>
  )
}
