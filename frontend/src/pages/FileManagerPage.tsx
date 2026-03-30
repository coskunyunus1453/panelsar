import { Suspense, lazy, useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useDropzone } from 'react-dropzone'
import api from '../services/api'
import { useThemeStore } from '../store/themeStore'
import {
  ArrowLeft,
  ChevronDown,
  ChevronRight,
  FileCode,
  FileText,
  Folder,
  FolderOpen,
  HelpCircle,
  LayoutGrid,
  List as ListIcon,
  RefreshCw,
  Save,
  Search,
  Trash2,
  Unlock,
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

/** Klasör/dosya göreli yolu — segment başına sadece . .. \ ve kontrolsüz uzunluk engellenir (UTF-8 dosya adları OK). */
function isSafeRelativePath(path: string): boolean {
  const t = path.trim().replace(/^\/+/g, '')
  if (!t) return true
  if (t.includes('\\')) return false
  const segs = t.split('/').filter(Boolean)
  if (segs.length === 0) return true
  if (segs.some((s) => s === '.' || s === '..')) return false
  if (segs.some((s) => s.length > 255)) return false
  return true
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

function formatMtimeIso(sec?: number): string {
  if (!sec) return '—'
  try {
    const d = new Date(sec * 1000)
    const p = (n: number) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`
  } catch {
    return '—'
  }
}

const FILEMGR_HELP_KEY = 'panelsar_filemgr_help_seen'

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

  const pathParamRaw = searchParams.get('path') ?? ''
  const path = useMemo(() => {
    const t = pathParamRaw.trim().replace(/^\/+/g, '')
    if (!isSafeRelativePath(t)) return ''
    return t
  }, [pathParamRaw])

  const setFilePath = useCallback(
    (next: string) => {
      const t = next.trim().replace(/^\/+/g, '')
      const normalized = isSafeRelativePath(t) ? t : ''
      setSearchParams(
        (prev) => {
          const nextSp = new URLSearchParams(prev)
          if (normalized) nextSp.set('path', normalized)
          else nextSp.delete('path')
          return nextSp
        },
        { replace: true },
      )
      // Path değişince önbellekte kalmış eski dizin listesini zorla temizle
      if (domainId !== '') {
        queueMicrotask(() => {
          void qc.invalidateQueries({ queryKey: ['files', domainId] })
        })
      }
    },
    [setSearchParams, domainId, qc],
  )

  useEffect(() => {
    const t = pathParamRaw.trim().replace(/^\/+/g, '')
    if (t && !isSafeRelativePath(t)) {
      setSearchParams(
        (prev) => {
          const nextSp = new URLSearchParams(prev)
          nextSp.delete('path')
          return nextSp
        },
        { replace: true },
      )
    }
  }, [pathParamRaw, setSearchParams])
  const [selected, setSelected] = useState<string | null>(null)
  const [editorOpen, setEditorOpen] = useState(false)
  const [tabs, setTabs] = useState<EditorTab[]>([])
  const [activeTab, setActiveTab] = useState(0)
  const [searchQ, setSearchQ] = useState('')
  const [searchHits, setSearchHits] = useState<{ path: string; line: number; preview: string }[]>([])
  const [imagePreview, setImagePreview] = useState<{ url: string; filename: string } | null>(null)
  const [helpModalOpen, setHelpModalOpen] = useState(false)
  const [searchIncludeSubdirs, setSearchIncludeSubdirs] = useState(false)
  const [viewMode, setViewMode] = useState<'list' | 'grid'>('list')
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => new Set())
  const [fileOpsOpen, setFileOpsOpen] = useState(false)
  const [goPageInput, setGoPageInput] = useState('1')

  // Listeleme performansı için pagination ve sıralama
  const [pageSize, setPageSize] = useState(50)
  const [offset, setOffset] = useState(0)
  const [sortKey, setSortKey] = useState<'name' | 'size' | 'mtime'>('name')
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc')

  const listScrollRef = useRef<HTMLDivElement | null>(null)
  const fileOpsRef = useRef<HTMLDivElement | null>(null)

  useEffect(() => {
    if (!fileOpsOpen) return
    const fn = (e: MouseEvent) => {
      if (fileOpsRef.current && !fileOpsRef.current.contains(e.target as Node)) {
        setFileOpsOpen(false)
      }
    }
    document.addEventListener('mousedown', fn)
    return () => document.removeEventListener('mousedown', fn)
  }, [fileOpsOpen])

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

  useEffect(() => {
    if (domainId === '') return
    try {
      if (!localStorage.getItem(FILEMGR_HELP_KEY)) {
        setHelpModalOpen(true)
      }
    } catch {
      setHelpModalOpen(true)
    }
  }, [domainId])

  const onDomainSelectChange = (value: string) => {
    if (!value) {
      setDomainId('')
      setSearchParams(
        (prev) => {
          const next = new URLSearchParams(prev)
          next.delete('domain')
          next.delete('path')
          return next
        },
        { replace: true },
      )
      return
    }
    const n = Number(value)
    if (!Number.isFinite(n) || n <= 0) return
    setDomainId(n)
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev)
        next.set('domain', String(n))
        next.delete('path')
        return next
      },
      { replace: true },
    )
  }

  const filesQ = useQuery({
    queryKey: ['files', domainId, path, pageSize, offset, sortKey, sortOrder],
    enabled: domainId !== '',
    staleTime: 0,
    refetchOnMount: 'always',
    queryFn: async ({ queryKey }) => {
      const [, domId, pathSeg, lim, off, sk, so] = queryKey as [
        string,
        number,
        string,
        number,
        number,
        'name' | 'size' | 'mtime',
        'asc' | 'desc',
      ]
      const u = new URLSearchParams()
      u.set('limit', String(lim))
      u.set('offset', String(off))
      u.set('sort', sk)
      u.set('order', so)
      if (pathSeg !== '') {
        u.set('path', pathSeg)
      }
      const { data } = await api.get(`/domains/${domId}/files?${u.toString()}`)
      return data as {
        entries: ListEntry[]
        document_root_hint?: string
        total?: number
        limit?: number
        offset?: number
        message?: string
      }
    },
  })

  useEffect(() => {
    setOffset(0)
    setSelectedIds(new Set())
  }, [path, sortKey, sortOrder, domainId, pageSize])

  const goIntoFolder = useCallback(
    (folderName: string) => {
      if (!folderName) return
      const next = path ? joinRel(path, folderName) : folderName
      setFilePath(next)
      setSelected(null)
    },
    [path, setFilePath],
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
        const { data } = await api.post<{ content: string }>(`/domains/${domainId}/files/read`, {
          path: relPath,
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
      await api.delete(`/domains/${domainId}/files`, {
        params: { path: rel },
        data: { path: rel },
      })
    },
    onSuccess: () => {
      toast.success(t('files.deleted'))
      setSelected(null)
      setOffset(0)
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
      setMoveDialog(null)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const searchM = useMutation({
    mutationFn: async (q: string) => {
      const basePath = searchIncludeSubdirs ? '' : path
      const { data } = await api.get<{ hits: { path: string; line: number; preview: string }[] }>(
        `/domains/${domainId}/files/search`,
        { params: { path: basePath, q } },
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
  const total = filesQ.data?.total ?? 0
  const crumbs = splitBreadcrumbs(path)
  const currentTab = tabs[activeTab]

  const listErrDetail = useMemo(() => {
    if (!filesQ.isError || !filesQ.error) return ''
    const ax = filesQ.error as { response?: { data?: { message?: string } } }
    return ax.response?.data?.message ?? ''
  }, [filesQ.isError, filesQ.error])

  const entryStats = useMemo(() => {
    let dirs = 0
    let files = 0
    let bytes = 0
    for (const e of entries) {
      if (e.is_dir) dirs++
      else {
        files++
        bytes += e.size || 0
      }
    }
    return { dirs, files, bytes }
  }, [entries])

  const totalPages = Math.max(1, total > 0 ? Math.ceil(total / pageSize) : 1)
  const currentPageNum = Math.min(totalPages, Math.floor(offset / pageSize) + 1)

  useEffect(() => {
    setGoPageInput(String(currentPageNum))
  }, [currentPageNum])

  const rowKey = (e: ListEntry) => `${e.name}|${e.is_dir}`

  const dirtyCount = tabs.filter((x) => !x.loading && x.content !== x.original).length
  const activeReadOnly = !!(currentTab && isExecutionRiskFilePath(currentTab.path))
  const hasDirtyReadOnly = tabs.some(
    (tab) => !tab.loading && tab.content !== tab.original && isExecutionRiskFilePath(tab.path),
  )

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <FolderOpen className="h-8 w-8 text-primary-500" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.files')}</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm">{t('files.subtitle')}</p>
          </div>
        </div>
        <button
          type="button"
          className="btn-secondary inline-flex items-center gap-2 text-sm"
          onClick={() => setHelpModalOpen(true)}
        >
          <HelpCircle className="h-4 w-4" />
          {t('files.help_open')}
        </button>
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
        <div className="flex flex-wrap items-end gap-3 border-b border-gray-100 dark:border-gray-800 px-4 py-3">
          <div>
            <label className="label text-xs">{t('domains.name')}</label>
            <select
              className="input min-w-[200px] text-sm"
              value={domainId}
              onChange={(e) => {
                onDomainSelectChange(e.target.value)
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
            className="btn-secondary inline-flex items-center gap-2 text-sm"
            onClick={() => {
              void domainsQ.refetch()
              void filesQ.refetch()
            }}
          >
            <RefreshCw className="h-4 w-4" />
            {t('common.refresh')}
          </button>
        </div>

        {domainId === '' && (
          <p className="p-8 text-center text-sm text-gray-500 dark:text-gray-400">
            {t('files.select_domain_hint')}
          </p>
        )}

        {domainId !== '' && (
          <>
            <div className="flex flex-wrap items-center gap-2 border-b border-gray-100 dark:border-gray-800 px-3 py-2 text-sm">
              <button
                type="button"
                className="rounded-md p-1.5 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 disabled:opacity-40"
                disabled={path === ''}
                title="Geri"
                onClick={() => {
                  setFilePath(parentPath(path))
                  setSelected(null)
                }}
              >
                <ArrowLeft className="h-5 w-5" />
              </button>
              <nav className="flex min-w-0 flex-1 flex-wrap items-center gap-0.5" aria-label="breadcrumb">
                <button
                  type="button"
                  className="max-w-[10rem] truncate rounded px-1.5 py-0.5 font-medium text-primary-600 hover:bg-gray-100 dark:text-primary-400 dark:hover:bg-gray-800 sm:max-w-none"
                  onClick={() => {
                    setFilePath('')
                    setSelected(null)
                  }}
                >
                  {t('files.root_segment')}
                </button>
                {crumbs.map((c) => (
                  <span key={c.path} className="inline-flex min-w-0 items-center gap-0.5">
                    <ChevronRight className="h-4 w-4 shrink-0 text-gray-400" />
                    <button
                      type="button"
                      className="max-w-[8rem] truncate rounded px-1.5 py-0.5 text-left font-medium text-primary-600 hover:bg-gray-100 dark:text-primary-400 dark:hover:bg-gray-800 sm:max-w-[14rem]"
                      onClick={() => {
                        setFilePath(c.path)
                        setSelected(null)
                      }}
                    >
                      {c.label}
                    </button>
                  </span>
                ))}
              </nav>
              <button
                type="button"
                className="shrink-0 rounded-md p-1.5 text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800"
                onClick={() => void filesQ.refetch()}
                title={t('files.op_refresh')}
              >
                <RefreshCw className={clsx('h-4 w-4', filesQ.isFetching && 'animate-spin')} />
              </button>
              <div className="flex w-full flex-wrap items-center gap-2 sm:ml-auto sm:w-auto">
                <input
                  className="input min-w-[140px] flex-1 py-1.5 text-sm sm:flex-none sm:min-w-[180px]"
                  value={searchQ}
                  onChange={(e) => setSearchQ(e.target.value)}
                  placeholder={t('files.toolbar_search_placeholder')}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' && searchQ.trim().length >= 2) {
                      searchM.mutate(searchQ.trim())
                    }
                  }}
                />
                <label className="inline-flex items-center gap-1.5 whitespace-nowrap text-xs text-gray-600 dark:text-gray-400">
                  <input
                    type="checkbox"
                    checked={searchIncludeSubdirs}
                    onChange={(e) => setSearchIncludeSubdirs(e.target.checked)}
                  />
                  {t('files.search_include_subdirs')}
                </label>
                <button
                  type="button"
                  className="btn-primary inline-flex items-center justify-center p-2 sm:px-3"
                  disabled={searchQ.trim().length < 2 || searchM.isPending}
                  onClick={() => searchM.mutate(searchQ.trim())}
                  title={t('files.search_run')}
                >
                  <Search className="h-4 w-4" />
                </button>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2 border-b border-gray-100 dark:border-gray-800 px-3 py-2">
              <div className="relative" ref={fileOpsRef}>
                <button
                  type="button"
                  className="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800/80 dark:text-gray-100 dark:hover:bg-gray-800"
                  onClick={() => setFileOpsOpen((o) => !o)}
                >
                  <Folder className="h-4 w-4 text-amber-500" />
                  {t('files.file_operations')}
                  <ChevronDown className="h-4 w-4 opacity-70" />
                </button>
                {fileOpsOpen && (
                  <div className="absolute left-0 top-full z-30 mt-1 min-w-[220px] rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    <button
                      type="button"
                      className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
                      onClick={() => {
                        setFileOpsOpen(false)
                        open()
                      }}
                    >
                      <Upload className="h-4 w-4" />
                      {t('files.op_upload')}
                    </button>
                    <button
                      type="button"
                      className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
                      onClick={() => {
                        setFileOpsOpen(false)
                        const name = window.prompt(t('files.op_new_folder'), '')
                        if (name?.trim()) mkdirM.mutate(name.trim())
                      }}
                    >
                      <Folder className="h-4 w-4 text-amber-500" />
                      {t('files.op_new_folder')}
                    </button>
                    <button
                      type="button"
                      className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
                      onClick={() => {
                        setFileOpsOpen(false)
                        const name = window.prompt(t('files.op_new_file'), '')
                        if (!name?.trim()) return
                        const base = name.trim()
                        if (!isSafeNewFileName(base)) {
                          toast.error(t('files.invalid_filename'))
                          return
                        }
                        if (entries.some((e) => e.name === base && !e.is_dir)) {
                          toast.error(t('files.file_exists'))
                          return
                        }
                        createFileM.mutate(base)
                      }}
                    >
                      <FilePlus className="h-4 w-4" />
                      {t('files.op_new_file')}
                    </button>
                    <button
                      type="button"
                      className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
                      onClick={() => {
                        setFileOpsOpen(false)
                        void filesQ.refetch()
                      }}
                    >
                      <RefreshCw className="h-4 w-4" />
                      {t('files.op_refresh')}
                    </button>
                  </div>
                )}
              </div>
              <div className="ml-auto flex flex-wrap items-center gap-2">
                <button
                  type="button"
                  className="btn-secondary py-1.5 text-sm"
                  onClick={() => toast(t('files.perm_backup_na'))}
                >
                  {t('files.perm_backup')}
                </button>
                <button
                  type="button"
                  className="btn-secondary inline-flex items-center gap-1.5 py-1.5 text-sm"
                  onClick={() => toast(t('files.recycle_na'))}
                >
                  <Trash2 className="h-4 w-4" />
                  {t('files.recycle_bin')}
                </button>
                <div className="inline-flex overflow-hidden rounded-md border border-gray-200 dark:border-gray-600">
                  <button
                    type="button"
                    className={clsx(
                      'p-2',
                      viewMode === 'grid'
                        ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-200'
                        : 'bg-white text-gray-500 hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800',
                    )}
                    title={t('files.view_grid')}
                    onClick={() => setViewMode('grid')}
                  >
                    <LayoutGrid className="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    className={clsx(
                      'border-l border-gray-200 p-2 dark:border-gray-600',
                      viewMode === 'list'
                        ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-200'
                        : 'bg-white text-gray-500 hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800',
                    )}
                    title={t('files.view_list')}
                    onClick={() => setViewMode('list')}
                  >
                    <ListIcon className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>

            {searchHits.length > 0 && (
              <div className="max-h-36 overflow-y-auto border-b border-gray-100 px-3 py-2 dark:border-gray-800">
                <p className="mb-1 text-xs text-gray-500">
                  {t('files.search_done', { count: searchHits.length })}
                </p>
                <ul className="space-y-1 text-xs">
                  {searchHits.slice(0, 20).map((h, i) => (
                    <li key={`${h.path}-${h.line}-${i}`}>
                      <button
                        type="button"
                        className="text-left font-mono text-primary-600 hover:underline dark:text-primary-400"
                        onClick={() => {
                          const dir = parentPath(h.path)
                          setFilePath(dir)
                          setSelected(null)
                          void openFileWrapped(h.path)
                        }}
                      >
                        {h.path}:{h.line}
                      </button>
                      <span className="ml-1 text-gray-500">{h.preview}</span>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <p
              className={clsx(
                'border-b border-gray-100 px-3 py-2 text-center text-xs dark:border-gray-800',
                isDragActive
                  ? 'bg-primary-100 text-primary-900 dark:bg-primary-900/40 dark:text-primary-100'
                  : 'bg-gray-50/80 text-gray-600 dark:bg-gray-800/50 dark:text-gray-300',
              )}
            >
              {isDragActive ? t('files.drop_here') : t('files.drop_zone_hint')}
            </p>
            {filesQ.isError && (
              <p className="border-b border-red-100 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-200">
                {t('files.list_error')}
                {listErrDetail ? ` — ${listErrDetail}` : ''}
              </p>
            )}

            <div
              key={`files-${domainId}-${path || 'root'}`}
              ref={listScrollRef}
              className="max-h-[min(62vh,560px)] overflow-x-auto overflow-y-auto"
            >
              {viewMode === 'list' ? (
                <table className="w-full min-w-[960px] text-sm">
                  <thead className="bg-gray-50 text-gray-700 dark:bg-gray-800/80 dark:text-gray-300">
                    <tr className="border-b border-gray-200 dark:border-gray-700">
                      <th className="w-10 px-2 py-2 text-left">
                        <input
                          type="checkbox"
                          checked={
                            entries.length > 0 &&
                            entries.every((e) => selectedIds.has(rowKey(e)))
                          }
                          onChange={() => {
                            const all = entries.every((e) => selectedIds.has(rowKey(e)))
                            if (all) {
                              setSelectedIds(new Set())
                            } else {
                              setSelectedIds(new Set(entries.map((e) => rowKey(e))))
                            }
                          }}
                          aria-label="select all"
                        />
                      </th>
                      <th className="px-3 py-2 text-left">
                        <button
                          type="button"
                          className="inline-flex items-center gap-1 font-medium"
                          onClick={() => {
                            if (sortKey === 'name') setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')
                            else {
                              setSortKey('name')
                              setSortOrder('asc')
                            }
                          }}
                        >
                          {t('files.name')}
                          {sortKey === 'name' ? (sortOrder === 'asc' ? '▲' : '▼') : <span className="text-gray-400">⇅</span>}
                        </button>
                      </th>
                      <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">
                        {t('files.col_protected')}
                      </th>
                      <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">
                        {t('files.col_perm_owner')}
                      </th>
                      <th className="px-3 py-2 text-left">
                        <button
                          type="button"
                          className="inline-flex items-center gap-1 font-medium"
                          onClick={() => {
                            if (sortKey === 'size') setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')
                            else {
                              setSortKey('size')
                              setSortOrder('asc')
                            }
                          }}
                        >
                          {t('files.size')}
                          {sortKey === 'size' ? (sortOrder === 'asc' ? '▲' : '▼') : <span className="text-gray-400">⇅</span>}
                        </button>
                      </th>
                      <th className="px-3 py-2 text-left">
                        <button
                          type="button"
                          className="inline-flex items-center gap-1 font-medium"
                          onClick={() => {
                            if (sortKey === 'mtime') setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')
                            else {
                              setSortKey('mtime')
                              setSortOrder('asc')
                            }
                          }}
                        >
                          {t('files.col_modified')}
                          {sortKey === 'mtime' ? (sortOrder === 'asc' ? '▲' : '▼') : <span className="text-gray-400">⇅</span>}
                        </button>
                      </th>
                      <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">
                        {t('files.col_note')}
                      </th>
                      <th className="w-44 px-3 py-2 text-right">{t('files.col_action')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {path !== '' && (
                      <tr className="border-t border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/40">
                        <td className="px-2 py-2" />
                        <td className="px-3 py-2" colSpan={7}>
                          <button
                            type="button"
                            className="inline-flex items-center gap-2 font-medium text-primary-600 hover:underline dark:text-primary-400"
                            onClick={() => {
                              setFilePath(parentPath(path))
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
                      const rk = rowKey(e)
                      return (
                        <tr
                          key={rk}
                          className={clsx(
                            'border-t border-gray-100 dark:border-gray-800',
                            isSel && 'bg-primary-50/50 dark:bg-primary-900/15',
                          )}
                        >
                          <td className="px-2 py-2 align-top">
                            <input
                              type="checkbox"
                              checked={selectedIds.has(rk)}
                              onChange={() => {
                                setSelectedIds((prev) => {
                                  const next = new Set(prev)
                                  if (next.has(rk)) next.delete(rk)
                                  else next.add(rk)
                                  return next
                                })
                              }}
                            />
                          </td>
                          <td className="px-3 py-2 align-top">
                            {e.is_dir ? (
                              <button
                                type="button"
                                className={clsx(
                                  'inline-flex max-w-md items-center gap-2 rounded-md px-1 py-0.5 text-left font-mono text-gray-900 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-800',
                                  isSel && 'bg-primary-100 ring-1 ring-primary-300 dark:bg-primary-900/30 dark:ring-primary-700',
                                )}
                                onClick={() => {
                                  setSelected(e.name)
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
                                className="inline-flex cursor-pointer items-center gap-2 font-mono text-gray-900 dark:text-gray-100"
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
                              </div>
                            )}
                          </td>
                          <td className="px-3 py-2 align-top text-gray-600 dark:text-gray-400">
                            <span className="inline-flex items-center gap-1">
                              <Unlock className="h-3.5 w-3.5" />
                              {t('files.unprotected')}
                            </span>
                          </td>
                          <td className="px-3 py-2 align-top font-mono text-xs text-gray-600 dark:text-gray-400">
                            {t('files.perm_na')}
                          </td>
                          <td className="px-3 py-2 align-top">
                            {e.is_dir ? (
                              <button
                                type="button"
                                className="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                onClick={() => toast(t('files.calc_folder_na'))}
                              >
                                {t('files.calc')}
                              </button>
                            ) : (
                              <span className="font-medium text-primary-600 dark:text-primary-400">
                                {formatSize(e.size)}
                              </span>
                            )}
                          </td>
                          <td className="px-3 py-2 align-top font-mono text-xs text-gray-700 dark:text-gray-300">
                            {e.is_dir ? '—' : formatMtimeIso(e.mtime)}
                          </td>
                          <td className="px-3 py-2 align-top text-gray-400">—</td>
                          <td className="px-3 py-2 text-right align-top">
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
                              >
                                İndir
                              </button>
                            )}
                            <button
                              type="button"
                              className="mr-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                              onClick={() => setRenameDialog({ from: rel, newName: e.name })}
                            >
                              Ad
                            </button>
                            <button
                              type="button"
                              className="mr-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                              onClick={() =>
                                setMoveDialog({ from: rel, targetDir: path, baseName: e.name })
                              }
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
              ) : (
                <div className="grid grid-cols-2 gap-2 p-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                  {path !== '' && (
                    <button
                      type="button"
                      className="flex flex-col items-center gap-1 rounded-lg border border-gray-200 bg-gray-50/80 p-3 text-sm hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900/50 dark:hover:bg-gray-800"
                      onClick={() => {
                        setFilePath(parentPath(path))
                        setSelected(null)
                      }}
                    >
                      <Folder className="h-8 w-8 text-amber-500" />
                      <span>..</span>
                    </button>
                  )}
                  {entries.map((e) => {
                    const rel = joinRel(path, e.name)
                    const rk = rowKey(e)
                    return (
                      <div
                        key={rk}
                        role="button"
                        tabIndex={0}
                        className={clsx(
                          'flex flex-col items-center gap-1 rounded-lg border p-3 text-center text-sm outline-none',
                          selected === e.name
                            ? 'border-primary-400 bg-primary-50/80 dark:bg-primary-900/20'
                            : 'border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40 dark:hover:bg-gray-800',
                        )}
                        onClick={() => {
                          if (e.is_dir) {
                            setSelected(e.name)
                            goIntoFolder(e.name)
                          } else {
                            setSelected(e.name)
                          }
                        }}
                        onDoubleClick={() => {
                          if (e.is_dir) return
                          if (isImageFile(rel)) void previewImage(rel)
                          else void openFileWrapped(rel)
                        }}
                        onKeyDown={(ev) => {
                          if (ev.key === 'Enter') {
                            if (e.is_dir) goIntoFolder(e.name)
                            else if (isImageFile(rel)) void previewImage(rel)
                            else void openFileWrapped(rel)
                          }
                        }}
                      >
                        {e.is_dir ? (
                          <Folder className="h-8 w-8 text-amber-500" />
                        ) : (
                          <FileText className="h-8 w-8 text-gray-400" />
                        )}
                        <span className="w-full truncate font-mono text-xs">{e.name}</span>
                      </div>
                    )
                  })}
                </div>
              )}
            </div>

            {domainId && !filesQ.isLoading && entries.length === 0 && path === '' && (
              <p className="p-6 text-center text-gray-500">{t('common.no_data')}</p>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-3 py-2 text-xs dark:border-gray-800">
              <div className="text-gray-600 dark:text-gray-400">
                <span>
                  {t('files.footer_dirs_files', {
                    dirs: entryStats.dirs,
                    files: entryStats.files,
                  })}
                </span>
                <span className="mx-1">·</span>
                <span>{t('files.footer_size_label')}:</span>{' '}
                <button
                  type="button"
                  className="font-medium text-primary-600 hover:underline dark:text-primary-400"
                  onClick={() => toast(t('files.calc_folder_na'))}
                >
                  {t('files.calc')}
                </button>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                <button
                  type="button"
                  className="rounded border border-gray-200 p-1.5 dark:border-gray-600"
                  disabled={currentPageNum <= 1 || filesQ.isFetching}
                  onClick={() => setOffset((o) => Math.max(0, o - pageSize))}
                  aria-label="prev"
                >
                  <ChevronRight className="h-4 w-4 rotate-180" />
                </button>
                <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-800 dark:bg-primary-900/50 dark:text-primary-200">
                  {currentPageNum}
                </span>
                <button
                  type="button"
                  className="rounded border border-gray-200 p-1.5 dark:border-gray-600"
                  disabled={currentPageNum >= totalPages || filesQ.isFetching}
                  onClick={() => setOffset((o) => o + pageSize)}
                  aria-label="next"
                >
                  <ChevronRight className="h-4 w-4" />
                </button>
                <select
                  className="input py-1 text-xs"
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
                <form
                  className="flex items-center gap-1"
                  onSubmit={(ev) => {
                    ev.preventDefault()
                    const p = Number.parseInt(goPageInput, 10)
                    const page = Number.isFinite(p) ? Math.min(totalPages, Math.max(1, p)) : 1
                    setOffset((page - 1) * pageSize)
                  }}
                >
                  <span className="text-gray-500">{t('files.go_page')}</span>
                  <input
                    className="input w-10 px-1 py-1 text-center text-xs"
                    value={goPageInput}
                    onChange={(e) => setGoPageInput(e.target.value)}
                  />
                  <button type="submit" className="btn-secondary py-1 text-xs">
                    OK
                  </button>
                </form>
                <span className="text-gray-500">{t('files.total_count', { n: total })}</span>
              </div>
            </div>
          </>
        )}
      </div>

      {helpModalOpen && (
        <div
          className="fixed inset-0 z-[59] flex items-center justify-center bg-black/50 p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="filemgr-help-title"
        >
          <div className="w-full max-w-lg rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
              <h2 id="filemgr-help-title" className="text-lg font-semibold text-gray-900 dark:text-white">
                {t('files.help_title')}
              </h2>
              <button
                type="button"
                className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                aria-label="Kapat"
                onClick={() => setHelpModalOpen(false)}
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="space-y-3 px-4 py-4 text-sm text-gray-600 dark:text-gray-300">
              <p>{t('files.help_intro')}</p>
              <p>{t('files.help_upload_hint')}</p>
              <div className="rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-800/80">
                <div className="mb-1 font-medium text-gray-700 dark:text-gray-200">
                  {t('files.full_path')}
                </div>
                <code className="break-all text-gray-800 dark:text-gray-100">{fullPathDisplay}</code>
              </div>
            </div>
            <div className="flex justify-end gap-2 border-t border-gray-200 px-4 py-3 dark:border-gray-800">
              <button
                type="button"
                className="btn-primary"
                onClick={() => {
                  setHelpModalOpen(false)
                  try {
                    localStorage.setItem(FILEMGR_HELP_KEY, '1')
                  } catch {
                    /* ignore */
                  }
                }}
              >
                {t('files.help_understood')}
              </button>
            </div>
          </div>
        </div>
      )}



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
