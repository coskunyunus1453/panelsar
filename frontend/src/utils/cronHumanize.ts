/** Standart 5 alanlı cron (dakika saat gün ay haftanın günü). */

export const CRON_PRESETS: { id: string; schedule: string }[] = [
  { id: 'every_minute', schedule: '* * * * *' },
  { id: 'every_5', schedule: '*/5 * * * *' },
  { id: 'every_15', schedule: '*/15 * * * *' },
  { id: 'every_30', schedule: '*/30 * * * *' },
  { id: 'hourly', schedule: '0 * * * *' },
  { id: 'daily_midnight', schedule: '0 0 * * *' },
  { id: 'daily_6', schedule: '0 6 * * *' },
  { id: 'daily_noon', schedule: '0 12 * * *' },
  { id: 'weekly_sunday', schedule: '0 0 * * 0' },
  { id: 'weekly_monday', schedule: '0 0 * * 1' },
  { id: 'monthly_first', schedule: '0 0 1 * *' },
]

const PRESET_MAP = Object.fromEntries(CRON_PRESETS.map((p) => [p.schedule, p.id]))

export function presetIdForSchedule(schedule: string): string | null {
  return PRESET_MAP[schedule.trim()] ?? null
}

export function parseCronFields(schedule: string): string[] | null {
  const p = schedule.trim().split(/\s+/).filter(Boolean)
  return p.length === 5 ? p : null
}

export function joinCronFields(parts: string[]): string {
  const p = [...parts]
  while (p.length < 5) p.push('*')
  return p.slice(0, 5).join(' ')
}
