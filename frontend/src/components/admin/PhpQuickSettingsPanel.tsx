import type { ReactNode } from 'react'
import clsx from 'clsx'
import { useTranslation } from 'react-i18next'
import { Minus, Plus } from 'lucide-react'
import {
  formatIniMemoryMb,
  getActiveIniValue,
  iniValueToBool,
  parseIniInt,
  parseIniMemoryMb,
  QUICK_INT_META,
  QUICK_MEMORY_DIRECTIVES,
  setIniDirective,
  type QuickIntDirective,
} from '../../lib/phpIniQuick'

type Props = {
  iniText: string
  setIniText: (next: string) => void
  disabled: boolean
  loading: boolean
}

export default function PhpQuickSettingsPanel({ iniText, setIniText, disabled, loading }: Props) {
  const { t } = useTranslation()

  const patch = (key: string, value: string) => {
    setIniText(setIniDirective(iniText, key, value))
  }

  const setBool = (key: string, on: boolean, numeric?: boolean) => {
    patch(key, numeric ? (on ? '1' : '0') : on ? 'On' : 'Off')
  }

  const setMem = (key: string, mb: number) => {
    patch(key, formatIniMemoryMb(mb))
  }

  const bumpMem = (key: string, deltaMb: number) => {
    const cur = parseIniMemoryMb(getActiveIniValue(iniText, key))
    setMem(key, cur + deltaMb)
  }

  const setInt = (key: QuickIntDirective, n: number) => {
    const meta = QUICK_INT_META[key]
    const v = Math.min(meta.max, Math.max(meta.min, Math.round(n)))
    patch(key, String(v))
  }

  const bumpInt = (key: QuickIntDirective, dir: 1 | -1) => {
    const meta = QUICK_INT_META[key]
    const cur = parseIniInt(getActiveIniValue(iniText, key), meta.min)
    setInt(key, cur + dir * meta.step)
  }

  const section = (titleKey: string, children: ReactNode) => (
    <div className="rounded-xl border border-gray-100 dark:border-gray-800 overflow-hidden">
      <div className="px-4 py-2.5 bg-gray-50/80 dark:bg-gray-900/50 border-b border-gray-100 dark:border-gray-800">
        <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
          {t(titleKey)}
        </h3>
      </div>
      <div className="divide-y divide-gray-100 dark:divide-gray-800">{children}</div>
    </div>
  )

  const rowToggle = (key: string, numeric?: boolean) => {
    const raw = getActiveIniValue(iniText, key)
    const on = numeric ? iniValueToBool(raw) || raw === '1' : iniValueToBool(raw)
    const labelKey = `php_settings.quick.directive.${key.replace(/\./g, '_')}`
    const hintKey = `${labelKey}_hint`
    return (
      <div key={key} className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3.5">
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{t(labelKey)}</p>
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{t(hintKey)}</p>
        </div>
        <button
          type="button"
          role="switch"
          aria-checked={on}
          disabled={disabled || loading}
          onClick={() => setBool(key, !on, numeric)}
          className={clsx(
            'relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors',
            'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2',
            on ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-700',
            (disabled || loading) && 'opacity-50 cursor-not-allowed',
          )}
        >
          <span
            className={clsx(
              'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition',
              on ? 'translate-x-5' : 'translate-x-0.5',
            )}
          />
        </button>
      </div>
    )
  }

  const rowMemory = (key: string) => {
    const mb = parseIniMemoryMb(getActiveIniValue(iniText, key))
    const labelKey = `php_settings.quick.directive.${key}`
    const hintKey = `${labelKey}_hint`
    return (
      <div key={key} className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3.5">
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{t(labelKey)}</p>
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{t(hintKey)}</p>
        </div>
        <div className="flex items-center justify-end gap-2 flex-shrink-0">
          <button
            type="button"
            className="p-2 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40"
            disabled={disabled || loading || mb <= 8}
            onClick={() => bumpMem(key, -32)}
            aria-label={t('php_settings.quick.decrease')}
          >
            <Minus className="h-4 w-4" />
          </button>
          <span className="font-mono text-sm tabular-nums min-w-[4.5rem] text-center text-gray-900 dark:text-gray-100">
            {formatIniMemoryMb(mb)}
          </span>
          <button
            type="button"
            className="p-2 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40"
            disabled={disabled || loading || mb >= 8192}
            onClick={() => bumpMem(key, 32)}
            aria-label={t('php_settings.quick.increase')}
          >
            <Plus className="h-4 w-4" />
          </button>
        </div>
      </div>
    )
  }

  const rowInt = (key: QuickIntDirective) => {
    const meta = QUICK_INT_META[key]
    const cur = parseIniInt(getActiveIniValue(iniText, key), meta.min)
    const labelKey = `php_settings.quick.directive.${key}`
    const hintKey = `${labelKey}_hint`
    return (
      <div key={key} className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3.5">
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{t(labelKey)}</p>
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{t(hintKey)}</p>
        </div>
        <div className="flex items-center justify-end gap-2 flex-shrink-0">
          <button
            type="button"
            className="p-2 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40"
            disabled={disabled || loading || cur <= meta.min}
            onClick={() => bumpInt(key, -1)}
            aria-label={t('php_settings.quick.decrease')}
          >
            <Minus className="h-4 w-4" />
          </button>
          <span className="font-mono text-sm tabular-nums min-w-[3rem] text-center text-gray-900 dark:text-gray-100">
            {cur}
          </span>
          <button
            type="button"
            className="p-2 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40"
            disabled={disabled || loading || cur >= meta.max}
            onClick={() => bumpInt(key, 1)}
            aria-label={t('php_settings.quick.increase')}
          >
            <Plus className="h-4 w-4" />
          </button>
        </div>
      </div>
    )
  }

  if (loading) {
    return <p className="text-sm text-gray-500 dark:text-gray-400 py-6">{t('common.loading')}</p>
  }

  return (
    <div className="space-y-5">
      <p className="text-sm text-gray-500 dark:text-gray-400">{t('php_settings.quick.hint')}</p>

      {section(
        'php_settings.quick.section.errors',
        <>
          {rowToggle('display_errors')}
          {rowToggle('log_errors')}
        </>,
      )}

      {section(
        'php_settings.quick.section.security',
        <>
          {rowToggle('expose_php')}
          {rowToggle('allow_url_fopen')}
          {rowToggle('file_uploads')}
        </>,
      )}

      {section(
        'php_settings.quick.section.limits',
        <>
          {QUICK_MEMORY_DIRECTIVES.map((k) => rowMemory(k))}
          {rowInt('max_execution_time')}
          {rowInt('max_input_time')}
          {rowInt('max_file_uploads')}
        </>,
      )}

      {section('php_settings.quick.section.opcache', rowToggle('opcache.enable', true))}
    </div>
  )
}
