import { useCallback, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useDropzone } from 'react-dropzone'
import api from '../services/api'
import {
  ChevronRight,
  FileCode,
  FileText,
  Folder,
  FolderOpen,
  Home,
  RefreshCw,
  Save,
  Search,
  Trash2,
  Upload,
  FilePlus,
  X,
} from 'lucide-react'
import toast from 'react-hot-toast'
import clsx from 'clsx'

type ListEntry = { name: string; is_dir: boolean; size: number }

type EditorTab = {
  path: string
  content: string
  original: string
  loading?: boolean
}

const EDIT_NAME = /^(\.htaccess|\.env(\..+)?)$/i
const EDIT_EXT =
  /\.(html?|php|phtml|js|mjs|cjs|ts|tsx|jsx|css|scss|sass|less|json|xml|txt|md|yml|yaml|ini|sql|sh|vue|svelte|twig|neon|toml|csv|log|gitignore|gitattributes)$/i
/** Düzenleyicide anlamsız / riskli: ikili ve medya */
const BINARY_EXT =
  /\.(jpg|jpeg|png|gif|webp|ico|bmp|svgz|pdf|zip|tar|gz|tgz|7z|rar|bz2|xz|exe|dll|so|dylib|woff2?|ttf|eot|mp4|mp3|wav|avi|mov|webm|bin|iso|sqlite|db|dmg)$/i

function isEditableFile(name: string): boolean {
  if (EDIT_NAME.test(name)) return true
  if (EDIT_EXT.test(name)) return true
  if (BINARY_EXT.test(name)) return false
  // örn. custom.ext — uzantı yazıp oluşturulan dosyalar
  return /^[^/\\]+\.[a-zA-Z0-9]{1,20}$/.test(name)
}

function isSafeNewFileName(name: string): boolean {
  const t = name.trim()
  if (!t || t.length > 200) return false
  if (t.includes('/') || t.includes('\\')) return false
  if (t === '.' || t === '..') return false
  return /^[\w.\-]+$/.test(t)
}

function joinRel(dir: string, name: string): string {
  const d = dir.replace(/^\/+|\/+$/g, '')
  if (!d) return name
  return `${d}/${name}`
}

function parentPath(p: string): string {
  const t = p.replace(/^\/+|\/+$/g, '')
  if (!t) return ''
  const i = t.lastIndexOf('/')
  return i === -1 ? '' : t.slice(0, i)
}

function splitBreadcrumbs(p: string): { label: string; path: string }[] {
  const t = p.replace(/^\/+|\/+$/g, '')
  if (!t) return []
  const parts = t.split('/')
  const out: { label: string; path: string }[] = []
  let acc = ''
  for (const part of parts) {
    acc = acc ? `${acc}/${part}` : part
    out.push({ label: part, path: acc })
  }
  return out
}

function formatSize(n: number): string {
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / (1024 * 1024)).toFixed(1)} MB`
}

export default function FileManagerPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [domainId, setDomainId] = useState<number | ''>('')
  const [path, setPath] = useState('')
  const [selected, setSelected] = useState<string | null>(null)
  const [editorOpen, setEditorOpen] = useState(false)
  const [tabs, setTabs] = useState<EditorTab[]>([])
  const [activeTab, setActiveTab] = useState(0)
  const [searchQ, setSearchQ] = useState('')
  const [searchHits, setSearchHits] = useState<{ path: string; line: number; preview: string }[]>([])

  const domainsQ = useQuery({
    queryKey: ['domains', 'paginated'],
    queryFn: async () => (await api.get('/domains')).data,
  })

  const filesQ = useQuery({
    queryKey: ['files', domainId, path],
    enabled: domainId !== '',
    queryFn: async () =>
      (await api.get(`/domains/${domainId}/files`, { params: { path } })).data as {
        entries: ListEntry[]
        document_root_hint?: string
      },
  })

  const docHint = filesQ.data?.document_root_hint

  const fullPathDisplay = useMemo(() => {
    const rel = path || t('files.root_segment')
    if (docHint) {
      const sep = docHint.includes('\\') ? '\\' : '/'
      const base = docHint.replace(/[/\\]+$/, '')
      return path ? `${base}${sep}${path.replace(/\//g, sep)}` : base
    }
    return `~/${rel}`
  }, [path, docHint, t])

  const openFileWrapped = useCallback(
    async (relPath: string) => {
      const name = relPath.split('/').pop() || ''
      if (!isEditableFile(name)) {
        toast.error(t('files.cannot_edit'))
        return
      }
      const existing = tabs.findIndex((x) => x.path === relPath)
      if (existing >= 0) {
        setActiveTab(existing)
        setEditorOpen(true)
        return
      }
      setEditorOpen(true)
      setTabs((prev) => [...prev, { path: relPath, content: '', original: '', loading: true }])
      const newIndex = tabs.length
      setActiveTab(newIndex)
      try {
        const { data } = await api.get<{ content: string }>(`/domains/${domainId}/files/read`, {
          params: { path: relPath },
        })
        const c = data?.content ?? ''
        setTabs((prev) => {
          const i = prev.findIndex((x) => x.path === relPath)
          if (i < 0) return prev
          const next = [...prev]
          next[i] = { path: relPath, content: c, original: c, loading: false }
          return next
        })
        setActiveTab(newIndex)
      } catch (e: unknown) {
        const ax = e as { response?: { data?: { message?: string } } }
        toast.error(ax.response?.data?.message ?? t('files.read_error'))
        setTabs((prev) => {
          const next = prev.filter((x) => !(x.path === relPath && x.loading))
          if (next.length === 0) {
            setTimeout(() => setEditorOpen(false), 0)
          }
          return next
        })
      }
    },
    [domainId, tabs, t],
  )

  const mkdirM = useMutation({
    mutationFn: async (name: string) => {
      const target = path ? joinRel(path, name) : name
      await api.post(`/domains/${domainId}/files/mkdir`, { path: target })
    },
    onSuccess: () => {
      toast.success(t('files.folder_created'))
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
    },
  })

  const createFileM = useMutation({
    mutationFn: async (fileName: string) => {
      const target = path ? joinRel(path, fileName) : fileName
      await api.post(`/domains/${domainId}/files/write`, { path: target, content: '' })
      return target
    },
    onSuccess: async (relPath) => {
      toast.success(t('files.file_created'))
      await qc.invalidateQueries({ queryKey: ['files', domainId, path] })
      const base = relPath.split('/').pop() || relPath
      if (isEditableFile(base)) {
        void openFileWrapped(relPath)
      }
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? t('files.create_file_err'))
    },
  })

  const uploadM = useMutation({
    mutationFn: async (file: File) => {
      const fd = new FormData()
      fd.append('file', file)
      fd.append('path', path)
      await api.post(`/domains/${domainId}/files/upload`, fd)
    },
    onSuccess: () => {
      toast.success(t('files.upload_ok'))
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
    },
    onError: (err: unknown) => {
      const ax = err as {
        response?: { data?: { error?: string; message?: string; errors?: { file?: string[] } } }
      }
      const d = ax.response?.data
      const v = d?.errors?.file?.[0]
      toast.error(v ?? d?.error ?? d?.message ?? t('files.upload_err'))
    },
  })

  const deleteM = useMutation({
    mutationFn: async (rel: string) => {
      await api.delete(`/domains/${domainId}/files`, { params: { path: rel } })
    },
    onSuccess: () => {
      toast.success(t('files.deleted'))
      setSelected(null)
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const searchM = useMutation({
    mutationFn: async (q: string) => {
      const { data } = await api.get<{ hits: { path: string; line: number; preview: string }[] }>(
        `/domains/${domainId}/files/search`,
        { params: { path, q } },
      )
      return data?.hits ?? []
    },
    onSuccess: (hits) => {
      setSearchHits(hits)
      toast.success(t('files.search_done', { count: hits.length }))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const saveOne = useCallback(
    async (idx: number) => {
      const tab = tabs[idx]
      if (!tab || tab.loading || !domainId) return
      await api.post(`/domains/${domainId}/files/write`, {
        path: tab.path,
        content: tab.content,
      })
      setTabs((prev) => {
        const n = [...prev]
        if (n[idx]) n[idx] = { ...n[idx], original: n[idx].content }
        return n
      })
      toast.success(t('files.saved_file', { path: tab.path }))
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
    },
    [tabs, domainId, path, qc, t],
  )

  const saveAllM = useMutation({
    mutationFn: async () => {
      const dirty = tabs
        .map((tab, i) => ({ tab, i }))
        .filter(({ tab }) => !tab.loading && tab.content !== tab.original)
      for (const { tab } of dirty) {
        await api.post(`/domains/${domainId}/files/write`, {
          path: tab.path,
          content: tab.content,
        })
      }
      return dirty.length
    },
    onSuccess: (n) => {
      setTabs((prev) => prev.map((tab) => ({ ...tab, original: tab.content })))
      toast.success(t('files.saved_all', { count: n }))
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
    },
    onError: (err: unknown) => {
      toast.error(String(err))
    },
  })

  const onDrop = useCallback(
    (accepted: File[]) => {
      accepted.forEach((f) => uploadM.mutate(f))
    },
    [uploadM],
  )

  const { getRootProps, getInputProps, isDragActive, open } = useDropzone({
    onDrop,
    disabled: domainId === '' || uploadM.isPending,
    noClick: true,
    multiple: true,
  })

  const entries = filesQ.data?.entries ?? []
  const crumbs = splitBreadcrumbs(path)
  const currentTab = tabs[activeTab]

  const dirtyCount = tabs.filter((x) => !x.loading && x.content !== x.original).length

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <FolderOpen className="h-8 w-8 text-primary-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.files')}</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">{t('files.subtitle')}</p>
        </div>
      </div>

      <div className="card p-4 flex flex-wrap gap-4 items-end">
        <div>
          <label className="label">{t('domains.name')}</label>
          <select
            className="input min-w-[200px]"
            value={domainId}
            onChange={(e) => {
              setDomainId(e.target.value ? Number(e.target.value) : '')
              setPath('')
              setSelected(null)
              setSearchHits([])
            }}
          >
            <option value="">{t('common.select')}</option>
            {(domainsQ.data?.data ?? []).map((d: { id: number; name: string }) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>
        </div>
        <button
          type="button"
          className="btn-secondary flex items-center gap-2"
          onClick={() => {
            void domainsQ.refetch()
            void filesQ.refetch()
          }}
        >
          <RefreshCw className="h-4 w-4" />
          {t('common.refresh')}
        </button>
        <button
          type="button"
          className="btn-primary flex items-center gap-2"
          disabled={domainId === '' || uploadM.isPending}
          onClick={() => open()}
        >
          <Upload className="h-4 w-4" />
          {t('files.upload')}
        </button>
      </div>

      {domainId !== '' && (
        <div className="card p-4 space-y-3">
          <div className="flex flex-wrap items-center gap-1 text-sm">
            <span className="text-gray-500 dark:text-gray-400 shrink-0">{t('files.full_path')}:</span>
            <code className="break-all rounded bg-gray-100 px-2 py-1 text-xs text-gray-800 dark:bg-gray-800 dark:text-gray-200">
              {fullPathDisplay}
            </code>
          </div>
          <nav className="flex flex-wrap items-center gap-1 text-sm" aria-label="breadcrumb">
            <button
              type="button"
              className="inline-flex items-center gap-1 rounded-md px-2 py-1 font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
              onClick={() => {
                setPath('')
                setSelected(null)
              }}
            >
              <Home className="h-4 w-4" />
              {t('files.root_segment')}
            </button>
            {crumbs.map((c) => (
              <span key={c.path} className="inline-flex items-center gap-1">
                <ChevronRight className="h-4 w-4 text-gray-400" />
                <button
                  type="button"
                  className="rounded-md px-2 py-1 font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                  onClick={() => {
                    setPath(c.path)
                    setSelected(null)
                  }}
                >
                  {c.label}
                </button>
              </span>
            ))}
          </nav>
        </div>
      )}

      <div className="card p-4 space-y-3">
        <div className="flex flex-wrap gap-2 items-end">
          <div className="flex-1 min-w-[200px]">
            <label className="label">{t('files.search_in_files')}</label>
            <input
              className="input"
              value={searchQ}
              onChange={(e) => setSearchQ(e.target.value)}
              placeholder={t('files.search_placeholder')}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && domainId && searchQ.trim().length >= 2) {
                  searchM.mutate(searchQ.trim())
                }
              }}
            />
          </div>
          <button
            type="button"
            className="btn-secondary flex items-center gap-2"
            disabled={!domainId || searchQ.trim().length < 2 || searchM.isPending}
            onClick={() => searchM.mutate(searchQ.trim())}
          >
            <Search className="h-4 w-4" />
            {t('files.search_run')}
          </button>
        </div>
        {searchHits.length > 0 && (
          <ul className="max-h-48 overflow-auto rounded-lg border border-gray-200 dark:border-gray-700 text-sm">
            {searchHits.map((h, i) => (
              <li
                key={`${h.path}-${h.line}-${i}`}
                className="border-b border-gray-100 px-3 py-2 last:border-0 dark:border-gray-800"
              >
                <button
                  type="button"
                  className="w-full text-left hover:bg-gray-50 dark:hover:bg-gray-800/80"
                  onClick={() => {
                    const dir = parentPath(h.path)
                    setPath(dir)
                    setSelected(null)
                    void openFileWrapped(h.path)
                  }}
                >
                  <span className="font-mono text-primary-600 dark:text-primary-400">{h.path}</span>
                  <span className="text-gray-500"> :{h.line}</span>
                  <p className="truncate text-gray-600 dark:text-gray-400">{h.preview}</p>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div
        {...getRootProps()}
        className={clsx(
          'card overflow-hidden transition-colors',
          domainId !== '' &&
            'border-2 border-dashed border-gray-200 dark:border-gray-600',
          isDragActive && 'ring-2 ring-primary-500 border-primary-400 bg-primary-50/30 dark:bg-primary-900/10',
        )}
      >
        <input {...getInputProps()} />
        {domainId !== '' && (
          <p
            className={clsx(
              'border-b border-gray-100 px-4 py-2 text-center text-sm dark:border-gray-800',
              isDragActive
                ? 'bg-primary-100 text-primary-900 dark:bg-primary-900/40 dark:text-primary-100'
                : 'bg-gray-50/80 text-gray-600 dark:bg-gray-800/50 dark:text-gray-300',
            )}
          >
            {isDragActive ? t('files.drop_here') : t('files.drop_zone_hint')}
          </p>
        )}
        <div className="overflow-x-auto">
          <table className="w-full min-w-[640px] text-sm">
            <thead className="bg-gray-50 dark:bg-gray-800/80">
              <tr>
                <th className="text-left px-4 py-2">{t('files.name')}</th>
                <th className="text-left px-4 py-2">{t('files.type')}</th>
                <th className="text-left px-4 py-2">{t('files.size')}</th>
                <th className="text-right px-4 py-2 w-40">{t('files.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {path !== '' && (
                <tr className="border-t border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/40">
                  <td className="px-4 py-2" colSpan={4}>
                    <button
                      type="button"
                      className="inline-flex items-center gap-2 font-medium text-primary-600 hover:underline dark:text-primary-400"
                      onClick={() => {
                        setPath(parentPath(path))
                        setSelected(null)
                      }}
                    >
                      <Folder className="h-4 w-4" />
                      ..
                    </button>
                  </td>
                </tr>
              )}
              {entries.map((e) => {
                const rel = joinRel(path, e.name)
                const isSel = selected === e.name
                return (
                  <tr
                    key={e.name}
                    className={clsx(
                      'border-t border-gray-100 dark:border-gray-800',
                      isSel && 'bg-primary-50/50 dark:bg-primary-900/15',
                    )}
                  >
                    <td className="px-4 py-2">
                      {e.is_dir ? (
                        <button
                          type="button"
                          className="inline-flex items-center gap-2 font-mono text-left text-gray-900 hover:text-primary-600 dark:text-gray-100 dark:hover:text-primary-400"
                          onClick={() => {
                            setPath(rel)
                            setSelected(null)
                          }}
                        >
                          <Folder className="h-4 w-4 shrink-0 text-amber-500" />
                          {e.name}
                        </button>
                      ) : (
                        <div
                          role="button"
                          tabIndex={0}
                          className="inline-flex items-center gap-2 font-mono text-gray-900 dark:text-gray-100 cursor-pointer"
                          onClick={() => setSelected(e.name)}
                          onDoubleClick={() => void openFileWrapped(rel)}
                          onKeyDown={(ev) => {
                            if (ev.key === 'Enter') void openFileWrapped(rel)
                          }}
                        >
                          <FileText className="h-4 w-4 shrink-0 text-gray-400" />
                          {e.name}
                          {isEditableFile(e.name) && (
                            <span className="text-xs text-gray-400">({t('files.dblclick_edit')})</span>
                          )}
                        </div>
                      )}
                    </td>
                    <td className="px-4 py-2">{e.is_dir ? t('files.directory') : t('files.file')}</td>
                    <td className="px-4 py-2">{e.is_dir ? '—' : formatSize(e.size)}</td>
                    <td className="px-4 py-2 text-right">
                      {!e.is_dir && isEditableFile(e.name) && (
                        <button
                          type="button"
                          className="mr-2 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                          onClick={() => void openFileWrapped(rel)}
                        >
                          {t('files.open_editor')}
                        </button>
                      )}
                      <button
                        type="button"
                        className="inline-flex items-center gap-1 rounded-md p-1 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                        title={t('common.delete')}
                        onClick={() => {
                          if (window.confirm(t('common.confirm_delete'))) {
                            deleteM.mutate(rel)
                          }
                        }}
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
        {domainId && !filesQ.isLoading && entries.length === 0 && path === '' && (
          <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
        )}
      </div>

      <div className="card p-4 space-y-4">
        <div>
          <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">{t('files.quick_mkdir')}</p>
          <form
            className="flex flex-wrap gap-2"
            onSubmit={(ev) => {
              ev.preventDefault()
              const fd = new FormData(ev.currentTarget)
              const name = String(fd.get('folder') || '').trim()
              if (name && domainId !== '') mkdirM.mutate(name)
              ev.currentTarget.reset()
            }}
          >
            <input name="folder" className="input flex-1 min-w-[160px]" placeholder="new-folder" />
            <button
              type="submit"
              className="btn-primary"
              disabled={domainId === '' || mkdirM.isPending}
            >
              {t('files.mkdir')}
            </button>
          </form>
        </div>
        <div>
          <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">{t('files.quick_new_file')}</p>
          <form
            className="flex flex-wrap gap-2"
            onSubmit={(ev) => {
              ev.preventDefault()
              const fd = new FormData(ev.currentTarget)
              const name = String(fd.get('newfile') || '').trim()
              if (domainId === '') return
              if (!isSafeNewFileName(name)) {
                toast.error(t('files.invalid_filename'))
                return
              }
              const list = filesQ.data?.entries ?? []
              if (list.some((e) => e.name === name && !e.is_dir)) {
                toast.error(t('files.file_exists'))
                return
              }
              createFileM.mutate(name)
              ev.currentTarget.reset()
            }}
          >
            <input
              name="newfile"
              className="input flex-1 min-w-[180px] font-mono text-sm"
              placeholder="ornek.php, style.css, not.txt"
            />
            <button
              type="submit"
              className="btn-secondary inline-flex items-center gap-2"
              disabled={domainId === '' || createFileM.isPending}
            >
              <FilePlus className="h-4 w-4" />
              {t('files.create_file')}
            </button>
          </form>
        </div>
      </div>

      {editorOpen && tabs.length > 0 && (
        <div
          className="fixed inset-0 z-[60] flex flex-col bg-black/50 p-2 sm:p-4"
          role="dialog"
          aria-modal="true"
        >
          <div className="mx-auto flex h-full w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-3 py-2 dark:border-gray-800">
              <div className="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto">
                <FileCode className="h-5 w-5 shrink-0 text-primary-500" />
                {tabs.map((tab, i) => (
                  <button
                    key={tab.path}
                    type="button"
                    className={clsx(
                      'shrink-0 rounded-t-lg border border-b-0 px-3 py-1.5 text-xs font-mono max-w-[200px] truncate',
                      i === activeTab
                        ? 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900'
                        : 'border-transparent bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                      !tab.loading && tab.content !== tab.original && 'border-amber-300 dark:border-amber-700',
                    )}
                    onClick={() => setActiveTab(i)}
                  >
                    {tab.path.split('/').pop()}
                    {!tab.loading && tab.content !== tab.original ? ' •' : ''}
                  </button>
                ))}
              </div>
              <div className="flex shrink-0 items-center gap-2">
                <button
                  type="button"
                  className="btn-secondary flex items-center gap-1 text-sm py-1.5"
                  disabled={!currentTab || currentTab.loading || dirtyCount === 0 || saveAllM.isPending}
                  onClick={() => saveAllM.mutate()}
                >
                  <Save className="h-4 w-4" />
                  {t('files.save_all')}
                </button>
                <button
                  type="button"
                  className="btn-primary flex items-center gap-1 text-sm py-1.5"
                  disabled={
                    !currentTab ||
                    currentTab.loading ||
                    currentTab.content === currentTab.original
                  }
                  onClick={() => void saveOne(activeTab)}
                >
                  <Save className="h-4 w-4" />
                  {t('files.save')}
                </button>
                <button
                  type="button"
                  className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                  aria-label={t('files.close_editor')}
                  onClick={() => {
                    const unsaved = tabs.some((x) => !x.loading && x.content !== x.original)
                    if (unsaved && !window.confirm(t('files.unsaved_close'))) return
                    setEditorOpen(false)
                    setTabs([])
                  }}
                >
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>
            <div className="min-h-0 flex-1 border-t border-gray-200 dark:border-gray-800">
              {currentTab?.loading ? (
                <p className="p-8 text-center text-gray-500">{t('common.loading')}</p>
              ) : currentTab ? (
                <textarea
                  className="h-full min-h-[320px] w-full resize-none bg-gray-50 p-4 font-mono text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500 dark:bg-gray-950 dark:text-gray-100"
                  spellCheck={false}
                  value={currentTab.content}
                  onChange={(e) => {
                    const v = e.target.value
                    setTabs((prev) => {
                      const n = [...prev]
                      if (n[activeTab]) n[activeTab] = { ...n[activeTab], content: v }
                      return n
                    })
                  }}
                />
              ) : null}
            </div>
            <div className="border-t border-gray-200 px-3 py-2 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
              {currentTab && (
                <span className="font-mono">
                  {t('files.editing')}: {currentTab.path}
                </span>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
