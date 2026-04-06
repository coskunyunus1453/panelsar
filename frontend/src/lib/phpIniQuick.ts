/** php.ini satırları — son aktif (yorum satırları hariç) atama geçerlidir. */

export function getActiveIniValue(ini: string, directive: string): string | null {
  const keyNorm = directive.toLowerCase()
  let last: string | null = null
  for (const rawLine of ini.split(/\r?\n/)) {
    const trimmed = rawLine.trim()
    if (!trimmed || trimmed.startsWith(';') || trimmed.startsWith('#')) continue
    const m = trimmed.match(/^([A-Za-z0-9_.]+)\s*=\s*(.*)$/)
    if (!m) continue
    if (m[1].toLowerCase() !== keyNorm) continue
    last = m[2].trim()
  }
  return last
}

export function setIniDirective(ini: string, directive: string, value: string): string {
  const lines = ini.split(/\r?\n/)
  const keyNorm = directive.toLowerCase()
  let foundIdx = -1
  let foundKey = directive
  for (let i = lines.length - 1; i >= 0; i--) {
    const raw = lines[i]
    const t = raw.trim()
    if (!t || t.startsWith(';') || t.startsWith('#')) continue
    const m = t.match(/^([A-Za-z0-9_.]+)\s*=\s*/)
    if (m && m[1].toLowerCase() === keyNorm) {
      foundIdx = i
      foundKey = m[1]
      break
    }
  }
  const indent = foundIdx >= 0 ? (lines[foundIdx].match(/^\s*/)?.[0] ?? '') : ''
  const newLine = `${indent}${foundKey} = ${value}`
  if (foundIdx >= 0) {
    lines[foundIdx] = newLine
    return lines.join('\n')
  }
  const base = ini.replace(/\s+$/, '')
  return `${base}\n\n; Hostvim — quick settings\n${directive} = ${value}\n`
}

export function iniValueToBool(v: string | null): boolean {
  if (v == null || v === '') return false
  const x = v.trim().toLowerCase()
  return x === 'on' || x === '1' || x === 'true' || x === 'yes'
}

export function parseIniMemoryMb(v: string | null): number {
  if (!v) return 128
  const t = v.trim().toUpperCase().replace(/\s/g, '')
  const m = t.match(/^([0-9]+)([KMGT])?B?$/)
  if (!m) {
    const n = parseInt(t, 10)
    return Number.isFinite(n) && n > 0 ? n : 128
  }
  const n = parseInt(m[1], 10)
  const u = m[2] || ''
  if (u === 'G') return n * 1024
  if (u === 'K') return Math.max(1, Math.round(n / 1024))
  return n
}

export function formatIniMemoryMb(mb: number): string {
  const n = Math.min(Math.max(Math.round(mb), 8), 8192)
  if (n < 1024) return `${n}M`
  const g = n / 1024
  return Number.isInteger(g) ? `${g}G` : `${n}M`
}

export const QUICK_MEMORY_DIRECTIVES = ['memory_limit', 'post_max_size', 'upload_max_filesize'] as const

export type QuickIntDirective = 'max_execution_time' | 'max_input_time' | 'max_file_uploads'

export const QUICK_INT_META: Record<
  QuickIntDirective,
  { min: number; max: number; step: number }
> = {
  max_execution_time: { min: 30, max: 3600, step: 30 },
  max_input_time: { min: 30, max: 3600, step: 30 },
  max_file_uploads: { min: 1, max: 200, step: 1 },
}

export function parseIniInt(v: string | null, fallback: number): number {
  if (!v) return fallback
  const n = parseInt(String(v).trim(), 10)
  return Number.isFinite(n) ? n : fallback
}
