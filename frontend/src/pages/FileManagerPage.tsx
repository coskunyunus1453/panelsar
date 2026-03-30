import { Suspense, lazy, useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useDropzone } from 'react-dropzone'
import api from '../services/api'
import { useThemeStore } from '../store/themeStore'
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

type ListEntry = { name: string; is_dir: boolean; size: number; mtime?: number }

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

const EXECUTION_RISK_EXT_RE = /\.(sh|bash|cgi|pl|py|exe|bin)$/i

const IMAGE_EXT_RE = /\.(jpg|jpeg|png|gif|webp|ico|bmp|svg|svgz)$/i

function isImageFile(relPath: string): boolean {
  const name = relPath.split('/').pop() || ''
  return IMAGE_EXT_RE.test(name)
}

function isExecutionRiskFilePath(p: string): boolean {
  const name = p.split('/').pop() || ''
  return EXECUTION_RISK_EXT_RE.test(name)
}

function getMonacoLanguageFromPath(p: string): string {
  const name = p.split('/').pop()?.toLowerCase() || ''
  if (name.endsWith('.php')) return 'php'
  if (name.endsWith('.html') || name.endsWith('.htm')) return 'html'
  if (name.endsWith('.css')) return 'css'
  if (
    name.endsWith('.js') ||
    name.endsWith('.mjs') ||
    name.endsWith('.cjs') ||
    name.endsWith('.jsx')
  ) {
    return 'javascript'
  }
  if (name.endsWith('.json')) return 'json'
  return 'plaintext'
}

// Heavy bundle: editor sadece dosya açılınca lazy yüklensin.
const MonacoEditor = lazy(() => import('@monaco-editor/react'))

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
  if (EDIT_NAME.test(t)) return true
  return /^[\w.\-]+$/.test(t)
}

function isSafeRelativePath(path: string): boolean {
  const t = path.trim()
  if (!t) return true // '' => root
  if (t.includes('\\')) return false
  const segs = t.split('/').filter(Boolean)
  if (segs.length === 0) return true
  if (segs.some((s) => s === '.' || s === '..')) return false
  return segs.every((s) => isSafeNewFileName(s))
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

function formatMtime(sec?: number): string {
  if (!sec) return '—'
  try {
    return new Date(sec * 1000).toLocaleString()
  } catch {
    return '—'
  }
}

export default function FileManagerPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const { isDark } = useThemeStore()
  const [searchParams, setSearchParams] = useSearchParams()
  const domainParam = searchParams.get('domain')
  const [domainId, setDomainId] = useState<number | ''>(() => {
    const n = domainParam ? Number(domainParam) : NaN
    return Number.isFinite(n) && n > 0 ? n : ''
  })
  const [path, setPath] = useState('')
  const [selected, setSelected] = useState<string | null>(null)
  const [editorOpen, setEditorOpen] = useState(false)
  const [tabs, setTabs] = useState<EditorTab[]>([])
  const [activeTab, setActiveTab] = useState(0)
  const [searchQ, setSearchQ] = useState('')
  const [searchHits, setSearchHits] = useState<{ path: string; line: number; preview: string }[]>([])
  const [pathJump, setPathJump] = useState('')
  const [imagePreview, setImagePreview] = useState<{ url: string; filename: string } | null>(null)

  // Listeleme performansı için pagination ve sıralama
  const [pageSize, setPageSize] = useState(50)
  const [offset, setOffset] = useState(0)
  const [sortKey, setSortKey] = useState<'name' | 'size' | 'mtime'>('name')
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc')

  // Virtual/infinite list hissi için sayfaları birleştiriyoruz.
  const [mergedEntries, setMergedEntries] = useState<ListEntry[]>([])
  const [totalCount, setTotalCount] = useState(0)
  const listScrollRef = useRef<HTMLDivElement | null>(null)

  // Rename/Move modal state
  const [renameDialog, setRenameDialog] = useState<{ from: string; newName: string } | null>(null)
  const [moveDialog, setMoveDialog] = useState<{ from: string; targetDir: string; baseName: string } | null>(null)

  const domainsQ = useQuery({
    queryKey: ['domains', 'paginated'],
    queryFn: async () => (await api.get('/domains')).data,
  })

  const domainOptions = useMemo(() => {
    const raw = domainsQ.data
    if (!raw) return [] as { id: number; name: string }[]
    if (Array.isArray(raw)) return raw as { id: number; name: string }[]
    if (raw && typeof raw === 'object' && Array.isArray((raw as { data?: unknown }).data)) {
      return (raw as { data: { id: number; name: string }[] }).data
    }
    return []
  }, [domainsQ.data])

  useEffect(() => {
    if (!domainParam) return
    const n = Number(domainParam)
    if (Number.isFinite(n) && n > 0) {
      setDomainId(n)
    }
  }, [domainParam])

  const onDomainSelectChange = (value: string) => {
    const next = new URLSearchParams(searchParams)
    if (!value) {
      setDomainId('')
      next.delete('domain')
      setSearchParams(next, { replace: true })
      return
    }
    const n = Number(value)
    if (!Number.isFinite(n) || n <= 0) return
    setDomainId(n)
    next.set('domain', String(n))
    setSearchParams(next, { replace: true })
  }

  const filesQ = useQuery({
    queryKey: ['files', domainId, path, pageSize, offset, sortKey, sortOrder],
    enabled: domainId !== '',
    staleTime: 0,
    queryFn: async () =>
      (await api.get(`/domains/${domainId}/files`, { params: { path, limit: pageSize, offset, sort: sortKey, order: sortOrder } })).data as {
        entries: ListEntry[]
        document_root_hint?: string
        total?: number
        limit?: number
        offset?: number
        message?: string
      },
  })

  useEffect(() => {
    setPathJump(path)
    setOffset(0)
    setMergedEntries([])
    setTotalCount(0)
  }, [path, sortKey, sortOrder, domainId, pageSize])

  useEffect(() => {
    if (!filesQ.data) return
    const pageEntries = filesQ.data.entries ?? []
    const tot = filesQ.data.total ?? 0
    setTotalCount(tot)

    if (offset === 0) {
      setMergedEntries(pageEntries)
      return
    }

    setMergedEntries((prev) => {
      const seen = new Set(prev.map((x) => `${x.name}|${x.is_dir}`))
      const next = [...prev]
      for (const e of pageEntries) {
        const key = `${e.name}|${e.is_dir}`
        if (!seen.has(key)) {
          seen.add(key)
          next.push(e)
        }
      }
      return next
    })
  }, [filesQ.data, offset])

  const goIntoFolder = useCallback(
    (folderName: string) => {
      if (!folderName || filesQ.isFetching) return
      const next = path ? joinRel(path, folderName) : folderName
      setPath(next)
      setSelected(null)
    },
    [path, filesQ.isFetching],
  )

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
        const ax = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
        const msg = ax.response?.data?.errors?.path?.[0] ?? ax.response?.data?.message ?? t('files.read_error')
        toast.error(msg)
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

  const downloadAsFile = useCallback(
    async (rel: string) => {
      if (!domainId) return
      const res = await api.get(`/domains/${domainId}/files/download`, {
        params: { path: rel },
        responseType: 'blob',
      })
      const blob = res.data as Blob
      const url = URL.createObjectURL(blob)
      const filename = rel.split('/').pop() || 'download'
      const a = document.createElement('a')
      a.href = url
      a.download = filename
      document.body.appendChild(a)
      a.click()
      a.remove()
      URL.revokeObjectURL(url)
    },
    [domainId],
  )

  const previewImage = useCallback(
    async (rel: string) => {
      if (!domainId) return
      const res = await api.get(`/domains/${domainId}/files/download`, {
        params: { path: rel },
        responseType: 'blob',
      })
      const blob = res.data as Blob
      const url = URL.createObjectURL(blob)
      const filename = rel.split('/').pop() || 'image'
      setImagePreview((prev) => {
        if (prev?.url) URL.revokeObjectURL(prev.url)
        return { url, filename }
      })
    },
    [domainId],
  )

  const mkdirM = useMutation({
    mutationFn: async (name: string) => {
      const target = path ? joinRel(path, name) : name
      await api.post(`/domains/${domainId}/files/mkdir`, { path: target })
    },
    onSuccess: async () => {
      toast.success(t('files.folder_created'))
      setOffset(0)
      setMergedEntries([])
      await qc.invalidateQueries({ queryKey: ['files', domainId, path] })
      await qc.refetchQueries({ queryKey: ['files', domainId, path] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? t('files.mkdir_err'))
    },
  })

  const createFileM = useMutation({
    mutationFn: async (fileName: string) => {
      const target = path ? joinRel(path, fileName) : fileName
      await api.post(`/domains/${domainId}/files/create`, { path: target, content: '' })
      return target
    },
    onSuccess: async (relPath) => {
      toast.success(t('files.file_created'))
      setOffset(0)
      setMergedEntries([])
      await qc.invalidateQueries({ queryKey: ['files', domainId, path] })
      await qc.refetchQueries({ queryKey: ['files', domainId, path] })
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
    onSuccess: async () => {
      toast.success(t('files.upload_ok'))
      setOffset(0)
      setMergedEntries([])
      await qc.invalidateQueries({ queryKey: ['files', domainId, path] })
      await qc.refetchQueries({ queryKey: ['files', domainId, path] })
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
      setOffset(0)
      setMergedEntries([])
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const renameM = useMutation({
    mutationFn: async (vars: { from: string; to: string }) =>
      api.post(`/domains/${domainId}/files/rename`, vars),
    onSuccess: () => {
      toast.success('Ad değiştirildi')
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
      setSelected(null)
      setOffset(0)
      setMergedEntries([])
      setRenameDialog(null)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const moveM = useMutation({
    mutationFn: async (vars: { from: string; to: string }) =>
      api.post(`/domains/${domainId}/files/move`, vars),
    onSuccess: () => {
      toast.success('Taşındı')
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
      setSelected(null)
      setOffset(0)
      setMergedEntries([])
      setMoveDialog(null)
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

  const entries = mergedEntries
  const total = totalCount
  const hasMore = offset + pageSize < totalCount
  const crumbs = splitBreadcrumbs(path)
  const currentTab = tabs[activeTab]

  const listErrDetail = useMemo(() => {
    if (!filesQ.isError || !filesQ.error) return ''
    const ax = filesQ.error as { response?: { data?: { message?: string } } }
    return ax.response?.data?.message ?? ''
  }, [filesQ.isError, filesQ.error])

  const dirtyCount = tabs.filter((x) => !x.loading && x.content !== x.original).length
  const activeReadOnly = !!(currentTab && isExecutionRiskFilePath(currentTab.path))
  const hasDirtyReadOnly = tabs.some(
    (tab) => !tab.loading && tab.content !== tab.original && isExecutionRiskFilePath(tab.path),
  )

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
              onDomainSelectChange(e.target.value)
              setPath('')
              setSelected(null)
              setSearchHits([])
            }}
          >
            <option value="">{t('common.select')}</option>
            {domainOptions.map((d) => (
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
          <form
            className="mt-3 flex flex-wrap items-end gap-2"
            onSubmit={(ev) => {
              ev.preventDefault()
              const next = pathJump.replace(/\\/g, '/').replace(/^\/+|\/+$/g, '')
              setPath(next)
              setSelected(null)
            }}
          >
            <div className="min-w-[200px] flex-1">
              <label className="label text-xs">{t('files.path_editor')}</label>
              <input
                className="input font-mono text-sm"
                value={pathJump}
                onChange={(e) => setPathJump(e.target.value)}
                placeholder="public_html/wp-content"
                spellCheck={false}
              />
            </div>
            <button type="submit" className="btn-secondary" disabled={filesQ.isFetching}>
              {t('files.path_go')}
            </button>
          </form>
          <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">{t('files.folder_dblclick_hint')}</p>
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
        {filesQ.isError && (
          <p className="border-b border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-200">
            {t('files.list_error')}
            {listErrDetail ? ` — ${listErrDetail}` : ''}
          </p>
        )}
        <div
          ref={listScrollRef}
          onScroll={() => {
            const el = listScrollRef.current
            if (!el) return
            if (filesQ.isFetching) return
            if (!hasMore) return
            if (el.scrollHeight - el.scrollTop - el.clientHeight < 140) {
              setOffset((o) => {
                const next = o + pageSize
                return next < totalCount ? next : o
              })
            }
          }}
          className="overflow-x-auto overflow-y-auto max-h-[62vh]"
        >
          <table className="w-full min-w-[640px] text-sm">
            <thead className="bg-gray-50 dark:bg-gray-800/80">
              <tr>
                <th className="text-left px-4 py-2">
                  <button
                    type="button"
                    className="inline-flex items-center gap-1"
                    onClick={() => {
                      if (sortKey === 'name') setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')
                      else {
                        setSortKey('name')
                        setSortOrder('asc')
                      }
                    }}
                  >
                    {t('files.name')}
                    {sortKey === 'name' ? (sortOrder === 'asc' ? '▲' : '▼') : null}
                  </button>
                </th>
                <th className="text-left px-4 py-2">
                  <button
                    type="button"
                    className="inline-flex items-center gap-1"
                    onClick={() => {
                      if (sortKey === 'size') setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')
                      else {
                        setSortKey('size')
                        setSortOrder('asc')
                      }
                    }}
                  >
                    {t('files.size')}
                    {sortKey === 'size' ? (sortOrder === 'asc' ? '▲' : '▼') : null}
                  </button>
                </th>
                <th className="text-left px-4 py-2">
                  <button
                    type="button"
                    className="inline-flex items-center gap-1"
                    onClick={() => {
                      if (sortKey === 'mtime') setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')
                      else {
                        setSortKey('mtime')
                        setSortOrder('asc')
                      }
                    }}
                  >
                    Modified
                    {sortKey === 'mtime' ? (sortOrder === 'asc' ? '▲' : '▼') : null}
                  </button>
                </th>
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
                          className={clsx(
                            'inline-flex w-full max-w-md items-center gap-2 rounded-md px-1 py-0.5 text-left font-mono text-gray-900 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-800',
                            isSel && 'bg-primary-100 ring-1 ring-primary-300 dark:bg-primary-900/30 dark:ring-primary-700',
                          )}
                          onClick={() => setSelected(e.name)}
                          onDoubleClick={(ev) => {
                            ev.preventDefault()
                            goIntoFolder(e.name)
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
                          onDoubleClick={() => {
                            if (isImageFile(rel)) void previewImage(rel)
                            else void openFileWrapped(rel)
                          }}
                          onKeyDown={(ev) => {
                            if (ev.key === 'Enter') {
                              if (isImageFile(rel)) void previewImage(rel)
                              else void openFileWrapped(rel)
                            }
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
                    <td className="px-4 py-2">{e.is_dir ? '—' : formatSize(e.size)}</td>
                    <td className="px-4 py-2">{e.is_dir ? '—' : formatMtime(e.mtime)}</td>
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

                      {!e.is_dir && (
                        <button
                          type="button"
                          className="mr-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                          onClick={() => void downloadAsFile(rel)}
                          title="İndir"
                        >
                          İndir
                        </button>
                      )}

                      <button
                        type="button"
                        className="mr-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                        onClick={() => {
                          setRenameDialog({ from: rel, newName: e.name })
                        }}
                        title="Yeniden adlandır"
                      >
                        Ad
                      </button>

                      <button
                        type="button"
                        className="mr-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                        onClick={() => {
                          setMoveDialog({ from: rel, targetDir: path, baseName: e.name })
                        }}
                        title="Taşı"
                      >
                        Taşı
                      </button>
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

      {domainId !== '' && (
        <div className="card px-4 py-3">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="text-xs text-gray-500 dark:text-gray-400">
              {total > 0 ? (
                <>
                  {offset + 1}-{Math.min(offset + pageSize, total)} / {total}
                </>
              ) : (
                t('common.no_data')
              )}
            </div>
            <div className="flex items-center gap-2">
              <select
                className="input text-sm py-1.5"
                value={pageSize}
                onChange={(e) => {
                  const n = Number(e.target.value) || 50
                  setPageSize(n)
                  setOffset(0)
                }}
              >
                <option value={25}>25</option>
                <option value={50}>50</option>
                <option value={100}>100</option>
              </select>
              <button
                type="button"
                className="btn-secondary text-sm py-1.5"
                disabled={offset === 0 || filesQ.isFetching}
                onClick={() => setOffset((o) => Math.max(0, o - pageSize))}
              >
                Önceki
              </button>
              <button
                type="button"
                className="btn-secondary text-sm py-1.5"
                disabled={!hasMore || filesQ.isFetching}
                onClick={() => setOffset((o) => o + pageSize)}
              >
                Sonraki
              </button>
            </div>
          </div>
        </div>
      )}

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
              const list = entries
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

      {imagePreview && (
        <div className="fixed inset-0 z-[55] flex items-center justify-center bg-black/60 p-2 sm:p-4">
          <div className="mx-auto w-full max-w-3xl overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-3 py-2 dark:border-gray-800">
              <div className="min-w-0 truncate px-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                {imagePreview.filename}
              </div>
              <button
                type="button"
                className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                aria-label="Kapat"
                onClick={() => {
                  if (imagePreview.url) URL.revokeObjectURL(imagePreview.url)
                  setImagePreview(null)
                }}
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="max-h-[80vh] overflow-auto p-3">
              <img
                src={imagePreview.url}
                alt={imagePreview.filename}
                className="mx-auto max-w-full"
              />
            </div>
          </div>
        </div>
      )}

      {renameDialog && (
        <div className="fixed inset-0 z-[58] flex items-center justify-center bg-black/60 p-2 sm:p-4">
          <div className="mx-auto w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
              <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                Yeniden adlandır
              </h2>
              <button
                type="button"
                className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                onClick={() => setRenameDialog(null)}
                aria-label="Kapat"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="p-4 space-y-3">
              <div>
                <div className="text-xs text-gray-500 dark:text-gray-400 mb-1">{renameDialog.from}</div>
                <input
                  className="input w-full"
                  value={renameDialog.newName}
                  onChange={(e) => setRenameDialog((prev) => (prev ? { ...prev, newName: e.target.value } : prev))}
                  placeholder="yeni-isim.php"
                />
              </div>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Güvenlik için yalnızca harf/rakam/dot/underscore/tire desteklenir.
              </p>
            </div>
            <div className="flex items-center justify-end gap-2 border-t border-gray-200 px-4 py-3 dark:border-gray-800">
              <button type="button" className="btn-secondary" onClick={() => setRenameDialog(null)}>
                {t('common.cancel')}
              </button>
              <button
                type="button"
                className="btn-primary"
                disabled={renameM.isPending}
                onClick={() => {
                  if (!renameDialog) return
                  const toName = renameDialog.newName.trim()
                  if (!toName || !isSafeNewFileName(toName)) {
                    toast.error(t('files.invalid_filename'))
                    return
                  }
                  const from = renameDialog.from
                  const toDir = parentPath(from)
                  const to = toDir ? `${toDir}/${toName}` : toName
                  renameM.mutate({ from, to })
                }}
              >
                {t('common.save')}
              </button>
            </div>
          </div>
        </div>
      )}

      {moveDialog && (
        <div className="fixed inset-0 z-[58] flex items-center justify-center bg-black/60 p-2 sm:p-4">
          <div className="mx-auto w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
              <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Taşı</h2>
              <button
                type="button"
                className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                onClick={() => setMoveDialog(null)}
                aria-label="Kapat"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="p-4 space-y-3">
              <div>
                <div className="text-xs text-gray-500 dark:text-gray-400 mb-1">{moveDialog.from}</div>
                <input
                  className="input w-full font-mono"
                  value={moveDialog.targetDir}
                  onChange={(e) => setMoveDialog((prev) => (prev ? { ...prev, targetDir: e.target.value } : prev))}
                  placeholder={path ? 'uploads/2026' : 'uploads/2026'}
                />
              </div>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Hedef klasör yolu göreli olmalı: örn. <code>uploads/2026</code>. Boş bırakılırsa root.
              </p>
            </div>
            <div className="flex items-center justify-end gap-2 border-t border-gray-200 px-4 py-3 dark:border-gray-800">
              <button type="button" className="btn-secondary" onClick={() => setMoveDialog(null)}>
                {t('common.cancel')}
              </button>
              <button
                type="button"
                className="btn-primary"
                disabled={moveM.isPending}
                onClick={() => {
                  if (!moveDialog) return
                  const destDir = moveDialog.targetDir.trim()
                  if (!isSafeRelativePath(destDir)) {
                    toast.error(t('files.invalid_filename'))
                    return
                  }
                  const from = moveDialog.from
                  const baseName = moveDialog.baseName
                  const to = destDir ? joinRel(destDir, baseName) : baseName
                  moveM.mutate({ from, to })
                }}
              >
                {t('common.save')}
              </button>
            </div>
          </div>
        </div>
      )}

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
                  disabled={
                    !currentTab ||
                    currentTab.loading ||
                    dirtyCount === 0 ||
                    saveAllM.isPending ||
                    hasDirtyReadOnly
                  }
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
                    currentTab.content === currentTab.original ||
                    activeReadOnly
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
                <div className="flex h-full min-h-[320px] flex-col">
                  {activeReadOnly && (
                    <div className="px-4 py-2 text-xs text-amber-800 dark:text-amber-200">
                      {t('files.risky_readonly')}
                    </div>
                  )}
                  <Suspense
                    fallback={
                      <p className="flex-1 p-8 text-center text-gray-500 dark:text-gray-400">
                        {t('common.loading')}
                      </p>
                    }
                  >
                    <MonacoEditor
                      key={currentTab.path}
                      height="100%"
                      defaultLanguage={getMonacoLanguageFromPath(currentTab.path)}
                      language={getMonacoLanguageFromPath(currentTab.path)}
                      theme={isDark ? 'vs-dark' : 'light'}
                      value={currentTab.content}
                      onChange={(v) => {
                        if (activeReadOnly) return
                        const next = v ?? ''
                        setTabs((prev) => {
                          const n = [...prev]
                          if (n[activeTab]) n[activeTab] = { ...n[activeTab], content: next }
                          return n
                        })
                      }}
                      options={{
                        readOnly: activeReadOnly,
                        minimap: { enabled: false },
                        fontSize: 13,
                        scrollBeyondLastLine: false,
                        automaticLayout: true,
                      }}
                      beforeMount={() => {
                        // Deep import'lar bazı build ortamlarında TS2307 üretebilir; runtime’da monaco-editor
                        // içeriği mevcut olduğu için TS'i bu importlarda bastırıyoruz.
                        // @ts-ignore
                        void import('monaco-editor/esm/vs/basic-languages/html/html.contribution')
                        // @ts-ignore
                        void import('monaco-editor/esm/vs/basic-languages/css/css.contribution')
                        // @ts-ignore
                        void import('monaco-editor/esm/vs/basic-languages/javascript/javascript.contribution')
                        // @ts-ignore
                        void import('monaco-editor/esm/vs/basic-languages/php/php.contribution')
                      }}
                    />
                  </Suspense>
                </div>
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
