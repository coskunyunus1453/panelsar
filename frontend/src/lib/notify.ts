import toast from 'react-hot-toast'
import { useNotificationsStore } from '../store/notificationsStore'

export function notify(level: 'info' | 'success' | 'error', title: string, message?: string) {
  useNotificationsStore.getState().add({ level, title, message })
  const text = message ? `${title} — ${message}` : title
  if (level === 'success') toast.success(text)
  else if (level === 'error') toast.error(text, { duration: 7000 })
  else toast(text)
}
