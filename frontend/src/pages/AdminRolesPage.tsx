import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import api from '../services/api'
import { Tags, Plus, Trash2, Pencil } from 'lucide-react'
import toast from 'react-hot-toast'
import type { Role } from '../types'

type AbilityRow = { name: string; group: string }

export default function AdminRolesPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const isAdmin = useAuthStore((s) => s.user?.roles?.some((r) => r.name === 'admin'))
  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing] = useState<Role | null>(null)

  const registryQ = useQuery({
    queryKey: ['admin-abilities-registry'],
    queryFn: async () =>
      (await api.get<{ abilities: AbilityRow[] }>('/admin/abilities/registry')).data,
    enabled: !!isAdmin,
  })

  const rolesQ = useQuery({
    queryKey: ['admin-roles'],
    queryFn: async () => (await api.get<Role[]>('/admin/roles')).data,
    enabled: !!isAdmin,
  })

  const grouped = useMemo(() => {
    const rows = registryQ.data?.abilities ?? []
    const m = new Map<string, AbilityRow[]>()
    for (const row of rows) {
      const g = row.group || 'other'
      if (!m.has(g)) m.set(g, [])
      m.get(g)!.push(row)
    }
    return m
  }, [registryQ.data])

  const saveM = useMutation({
    mutationFn: async (payload: {
      id?: number
      name?: string
      display_name: string
      assignable_by_reseller: boolean
      permissions: string[]
    }) => {
      if (payload.id != null) {
        return api.put(`/admin/roles/${payload.id}`, {
          display_name: payload.display_name,
          assignable_by_reseller: payload.assignable_by_reseller,
          permissions: payload.permissions,
        })
      }
      return api.post('/admin/roles', {
        name: payload.name,
        display_name: payload.display_name,
        assignable_by_reseller: payload.assignable_by_reseller,
        permissions: payload.permissions,
      })
    },
    onSuccess: () => {
      toast.success(t('roles.saved'))
      qc.invalidateQueries({ queryKey: ['admin-roles'] })
      setShowForm(false)
      setEditing(null)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const delM = useMutation({
    mutationFn: async (id: number) => api.delete(`/admin/roles/${id}`),
    onSuccess: () => {
      toast.success(t('roles.deleted'))
      qc.invalidateQueries({ queryKey: ['admin-roles'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  if (!isAdmin) {
    return <Navigate to="/dashboard" replace />
  }

  const rows = rolesQ.data ?? []

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Tags className="h-8 w-8 text-primary-500" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('roles.title')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm">{t('roles.subtitle')}</p>
          </div>
        </div>
        <button type="button" className="btn-primary flex items-center gap-2" onClick={() => { setEditing(null); setShowForm(true) }}>
          <Plus className="h-4 w-4" />
          {t('roles.new_role')}
        </button>
      </div>

      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-800/80">
            <tr>
              <th className="text-left px-4 py-2">{t('roles.col_name')}</th>
              <th className="text-left px-4 py-2">{t('roles.col_display')}</th>
              <th className="text-left px-4 py-2">{t('roles.col_reseller')}</th>
              <th className="text-left px-4 py-2">{t('roles.col_perms')}</th>
              <th className="text-right px-4 py-2">{t('common.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id} className="border-t border-gray-100 dark:border-gray-800">
                <td className="px-4 py-2 font-mono text-xs">{r.name}</td>
                <td className="px-4 py-2">{r.display_name ?? r.name}</td>
                <td className="px-4 py-2">{r.assignable_by_reseller ? t('common.yes') : t('common.no')}</td>
                <td className="px-4 py-2">{r.permissions?.length ?? 0}</td>
                <td className="px-4 py-2 text-right space-x-1">
                  {!r.is_system && (
                    <>
                      <button
                        type="button"
                        className="btn-secondary text-xs py-1 inline-flex items-center gap-1"
                        onClick={() => { setEditing(r); setShowForm(true) }}
                      >
                        <Pencil className="h-3 w-3" /> {t('common.edit')}
                      </button>
                      <button
                        type="button"
                        className="btn-secondary text-xs py-1 inline-flex items-center gap-1 text-red-600"
                        onClick={() => delM.mutate(r.id)}
                        disabled={delM.isPending}
                      >
                        <Trash2 className="h-3 w-3" /> {t('common.delete')}
                      </button>
                    </>
                  )}
                  {r.is_system && <span className="text-gray-400 text-xs">{t('roles.system')}</span>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {rolesQ.isLoading && <p className="p-6 text-center text-gray-500">{t('common.loading')}</p>}
      </div>

      {showForm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
          <div className="card max-w-2xl w-full p-6 space-y-4 bg-white dark:bg-gray-900 my-8 max-h-[90vh] overflow-y-auto">
            <h2 className="text-lg font-semibold">{editing ? t('roles.edit_role') : t('roles.new_role')}</h2>
            <form
              className="space-y-4"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                const selected = fd.getAll('permissions') as string[]
                const nameSlug = String(fd.get('name') || '').trim().toLowerCase()
                saveM.mutate({
                  id: editing?.id,
                  name: editing ? undefined : nameSlug,
                  display_name: String(fd.get('display_name') || '').trim(),
                  assignable_by_reseller: fd.get('assignable_by_reseller') === 'on',
                  permissions: selected,
                })
              }}
            >
              {!editing && (
                <div>
                  <label className="label">Tekil anahtar (örn. support_readonly)</label>
                  <input name="name" className="input w-full" required pattern="[a-z0-9][a-z0-9_\-]*" placeholder="örnek_rol" />
                </div>
              )}
              <div>
                <label className="label">{t('roles.display_name')}</label>
                <input
                  name="display_name"
                  className="input w-full"
                  required
                  defaultValue={editing?.display_name ?? ''}
                  placeholder={t('roles.display_name')}
                />
              </div>
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" name="assignable_by_reseller" defaultChecked={editing?.assignable_by_reseller} />
                {t('roles.assignable_reseller')}
              </label>
              <div className="space-y-3 max-h-[40vh] overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-lg p-3">
                <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{t('roles.permissions')}</p>
                {Array.from(grouped.entries()).map(([group, items]) => (
                  <div key={group}>
                    <p className="text-xs uppercase text-gray-500 mb-1">{group}</p>
                    <div className="grid sm:grid-cols-2 gap-1">
                      {items.map((a) => (
                        <label key={a.name} className="flex items-center gap-2 text-xs">
                          <input
                            type="checkbox"
                            name="permissions"
                            value={a.name}
                            defaultChecked={editing?.permissions?.some((p) => p.name === a.name)}
                          />
                          <span className="font-mono">{a.name}</span>
                        </label>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
              <div className="flex justify-end gap-2">
                <button type="button" className="btn-secondary" onClick={() => { setShowForm(false); setEditing(null) }}>
                  {t('common.cancel')}
                </button>
                <button type="submit" className="btn-primary" disabled={saveM.isPending}>
                  {t('common.save')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
