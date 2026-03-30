import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../store/authStore'
import { tokenHasAbility } from '../lib/abilities'
import api from '../services/api'
import { Store, Plus, Trash2 } from 'lucide-react'
import toast from 'react-hot-toast'
import type { Role } from '../types'

type AbilityRow = { name: string; group: string }

export default function ResellerPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const abilities = user?.abilities
  const showReseller =
    tokenHasAbility(abilities, 'reseller:users') ||
    tokenHasAbility(abilities, 'reseller:packages') ||
    tokenHasAbility(abilities, 'reseller:roles')
  const canUsers = tokenHasAbility(abilities, 'reseller:users')
  const canPackages = tokenHasAbility(abilities, 'reseller:packages')
  const canRoles = tokenHasAbility(abilities, 'reseller:roles')

  const [showAdd, setShowAdd] = useState(false)
  const [showRoleForm, setShowRoleForm] = useState(false)

  const usersQ = useQuery({
    queryKey: ['reseller-users'],
    queryFn: async () => (await api.get('/reseller/users')).data,
    enabled: !!showReseller && canUsers,
  })

  const pkgsQ = useQuery({
    queryKey: ['reseller-packages'],
    queryFn: async () =>
      (await api.get('/reseller/packages')).data as { packages: { id: number; name: string; slug: string }[] },
    enabled: !!showReseller && canPackages,
  })

  const rolesListQ = useQuery({
    queryKey: ['reseller-roles'],
    queryFn: async () => (await api.get<Role[]>('/reseller/roles')).data,
    enabled: !!showReseller && (canRoles || canUsers),
  })

  const registryQ = useQuery({
    queryKey: ['reseller-abilities-registry'],
    queryFn: async () =>
      (await api.get<{ abilities: AbilityRow[] }>('/reseller/abilities/registry')).data,
    enabled: !!showReseller && canRoles,
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

  const createUserM = useMutation({
    mutationFn: async (payload: {
      name: string
      email: string
      password: string
      password_confirmation: string
      role: string
    }) => api.post('/reseller/users', payload),
    onSuccess: () => {
      toast.success(t('reseller.user_created'))
      qc.invalidateQueries({ queryKey: ['reseller-users'] })
      setShowAdd(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const first = ax.response?.data?.errors
        ? Object.values(ax.response.data.errors)[0]?.[0]
        : undefined
      toast.error(first ?? ax.response?.data?.message ?? String(err))
    },
  })

  const saveRoleM = useMutation({
    mutationFn: async (payload: { display_name: string; permissions: string[] }) =>
      api.post('/reseller/roles', payload),
    onSuccess: () => {
      toast.success(t('reseller.role_saved'))
      qc.invalidateQueries({ queryKey: ['reseller-roles'] })
      setShowRoleForm(false)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const delRoleM = useMutation({
    mutationFn: async (id: number) => api.delete(`/reseller/roles/${id}`),
    onSuccess: () => {
      toast.success(t('reseller.role_deleted'))
      qc.invalidateQueries({ queryKey: ['reseller-roles'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  if (!showReseller) {
    return <Navigate to="/dashboard" replace />
  }

  const userRows = (usersQ.data?.data ?? []) as {
    id: number
    name: string
    email: string
    status: string
  }[]

  const packages = pkgsQ.data?.packages ?? []
  const roleOptions = rolesListQ.data ?? []
  const uid = user?.id

  const canDeleteRole = (r: Role) =>
    !r.is_system && r.name !== 'user' && r.owner_user_id != null && Number(r.owner_user_id) === Number(uid)

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Store className="h-8 w-8 text-primary-500" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.reseller')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm">{t('reseller.subtitle')}</p>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          {canUsers && (
            <button type="button" className="btn-primary flex items-center gap-2" onClick={() => setShowAdd(true)}>
              <Plus className="h-4 w-4" />
              {t('reseller.new_subuser')}
            </button>
          )}
          {canRoles && (
            <button type="button" className="btn-secondary flex items-center gap-2" onClick={() => setShowRoleForm(true)}>
              <Plus className="h-4 w-4" />
              {t('reseller.new_role')}
            </button>
          )}
        </div>
      </div>

      {showAdd && canUsers && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="card max-w-md w-full p-6 space-y-4 bg-white dark:bg-gray-900">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{t('reseller.new_subuser')}</h2>
            <form
              className="space-y-3"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                createUserM.mutate({
                  name: String(fd.get('name') || ''),
                  email: String(fd.get('email') || ''),
                  password: String(fd.get('password') || ''),
                  password_confirmation: String(fd.get('password_confirmation') || ''),
                  role: String(fd.get('role') || 'user'),
                })
              }}
            >
              <div>
                <label className="label">{t('auth.email')}</label>
                <input name="email" type="email" className="input w-full" required autoComplete="off" />
              </div>
              <div>
                <label className="label">Ad</label>
                <input name="name" type="text" className="input w-full" required />
              </div>
              <div>
                <label className="label">{t('reseller.role_for_new_user')}</label>
                <select name="role" className="input w-full" defaultValue="user" required>
                  {roleOptions.map((r) => (
                    <option key={r.id} value={r.name}>
                      {r.display_name ?? r.name}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">{t('auth.password')}</label>
                <input name="password" type="password" className="input w-full" required minLength={8} />
              </div>
              <div>
                <label className="label">{t('reseller.password_again')}</label>
                <input name="password_confirmation" type="password" className="input w-full" required minLength={8} />
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <button type="button" className="btn-secondary" onClick={() => setShowAdd(false)}>
                  {t('common.cancel')}
                </button>
                <button type="submit" className="btn-primary" disabled={createUserM.isPending}>
                  {t('common.create')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {showRoleForm && canRoles && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
          <div className="card max-w-xl w-full p-6 space-y-4 bg-white dark:bg-gray-900 my-8 max-h-[90vh] overflow-y-auto">
            <h2 className="text-lg font-semibold">{t('reseller.new_role')}</h2>
            <form
              className="space-y-4"
              onSubmit={(ev) => {
                ev.preventDefault()
                const fd = new FormData(ev.currentTarget)
                const perms = fd.getAll('permissions') as string[]
                saveRoleM.mutate({
                  display_name: String(fd.get('display_name') || '').trim(),
                  permissions: perms,
                })
              }}
            >
              <div>
                <label className="label">{t('roles.display_name')}</label>
                <input name="display_name" className="input w-full" required />
              </div>
              <div className="space-y-3 max-h-[40vh] overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-lg p-3">
                {Array.from(grouped.entries()).map(([group, items]) => (
                  <div key={group}>
                    <p className="text-xs uppercase text-gray-500 mb-1">{group}</p>
                    <div className="grid sm:grid-cols-2 gap-1">
                      {items.map((a) => (
                        <label key={a.name} className="flex items-center gap-2 text-xs">
                          <input type="checkbox" name="permissions" value={a.name} />
                          <span className="font-mono">{a.name}</span>
                        </label>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
              <div className="flex justify-end gap-2">
                <button type="button" className="btn-secondary" onClick={() => setShowRoleForm(false)}>
                  {t('common.cancel')}
                </button>
                <button type="submit" className="btn-primary" disabled={saveRoleM.isPending}>
                  {t('common.save')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <div className={`grid gap-6 ${canRoles ? 'lg:grid-cols-3' : 'lg:grid-cols-2'}`}>
        {canUsers && (
          <div className="card overflow-hidden">
            <h2 className="text-lg font-semibold px-4 py-3 border-b border-gray-100 dark:border-gray-800">
              {t('reseller.subusers')}
            </h2>
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-800/80">
                <tr>
                  <th className="text-left px-4 py-2">Ad</th>
                  <th className="text-left px-4 py-2">E-posta</th>
                  <th className="text-left px-4 py-2">{t('common.status')}</th>
                </tr>
              </thead>
              <tbody>
                {userRows.map((u) => (
                  <tr key={u.id} className="border-t border-gray-100 dark:border-gray-800">
                    <td className="px-4 py-2">{u.name}</td>
                    <td className="px-4 py-2 font-mono text-xs">{u.email}</td>
                    <td className="px-4 py-2">{u.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            {usersQ.isLoading && <p className="p-4 text-gray-500">{t('common.loading')}</p>}
            {!usersQ.isLoading && userRows.length === 0 && (
              <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
            )}
          </div>
        )}

        {canPackages && (
          <div className="card overflow-hidden">
            <h2 className="text-lg font-semibold px-4 py-3 border-b border-gray-100 dark:border-gray-800">
              {t('reseller.catalog')}
            </h2>
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-800/80">
                <tr>
                  <th className="text-left px-4 py-2">Ad</th>
                  <th className="text-left px-4 py-2">Slug</th>
                </tr>
              </thead>
              <tbody>
                {packages.map((p) => (
                  <tr key={p.id} className="border-t border-gray-100 dark:border-gray-800">
                    <td className="px-4 py-2">{p.name}</td>
                    <td className="px-4 py-2 font-mono text-xs">{p.slug}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            {pkgsQ.isLoading && <p className="p-4 text-gray-500">{t('common.loading')}</p>}
            {!pkgsQ.isLoading && packages.length === 0 && (
              <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
            )}
          </div>
        )}

        {canRoles && (
          <div className="card overflow-hidden lg:col-span-1">
            <h2 className="text-lg font-semibold px-4 py-3 border-b border-gray-100 dark:border-gray-800">
              {t('reseller.custom_roles')}
            </h2>
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-800/80">
                <tr>
                  <th className="text-left px-4 py-2">{t('roles.col_display')}</th>
                  <th className="text-left px-4 py-2">{t('roles.col_name')}</th>
                  <th className="text-right px-4 py-2">{t('common.actions')}</th>
                </tr>
              </thead>
              <tbody>
                {roleOptions.map((r) => (
                  <tr key={r.id} className="border-t border-gray-100 dark:border-gray-800">
                    <td className="px-4 py-2">{r.display_name ?? r.name}</td>
                    <td className="px-4 py-2 font-mono text-xs">{r.name}</td>
                    <td className="px-4 py-2 text-right">
                      {canDeleteRole(r) && (
                        <button
                          type="button"
                          className="btn-secondary text-xs py-1 text-red-600 inline-flex items-center gap-1"
                          onClick={() => delRoleM.mutate(r.id)}
                          disabled={delRoleM.isPending}
                        >
                          <Trash2 className="h-3 w-3" /> {t('common.delete')}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            {rolesListQ.isLoading && <p className="p-4 text-gray-500">{t('common.loading')}</p>}
          </div>
        )}
      </div>
    </div>
  )
}
