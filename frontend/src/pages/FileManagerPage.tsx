import { Suspense, lazy, useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useDropzone, type DropEvent } from 'react-dropzone'
import api from '../services/api'
import { useThemeStore } from '../store/themeStore'
import {
  ArrowLeft,
  ChevronDown,
  ChevronRight,
  FileCode,
  FileText,
  Folder,
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
import FileUploadProgressOverlay, {
  type FileUploadProgressView,
} from '../components/files/FileUploadProgressOverlay'
import FileArchiveProgressOverlay from '../components/files/FileArchiveProgressOverlay'

type ListEntry = {
  name: string
  is_dir: boolean
  size: number
  mtime?: number
  mode?: string
  owner?: string
  group?: string
}

type TrashItem = {
  id: string
  original_path: string
  deleted_at?: string
  name?: string
  is_dir?: boolean
  size?: number
}

type FileWithRelPath = File & { webkitRelativePath?: string }

type NormalizedUploadItem = {
  file: File
  relFromBase: string
  parentRel: string
  baseName: string
}

type UploadConflictRow = NormalizedUploadItem & { existing: ListEntry }

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
  const full = p.toLowerCase()
  const baseNoExt = name.includes('.') ? name.slice(0, name.lastIndexOf('.')) : name

  // Uzantısız / özel adlandırılmış dosyalar
  if (name === 'dockerfile') return 'dockerfile'
  if (name === 'makefile') return 'makefile'
  if (name === 'jenkinsfile') return 'groovy'
  if (name === '.env' || name.startsWith('.env.')) return 'shell'
  if (name === '.htaccess' || name === '.gitignore' || name === '.gitattributes') return 'plaintext'
  if (name === 'nginx.conf' || name.endsWith('.nginx.conf')) return 'nginx'
  if (name === 'caddyfile') return 'plaintext'
  if (baseNoExt === 'config' && (name.endsWith('.prod') || name.endsWith('.dev'))) return 'plaintext'

  // Yol bazlı örüntüler
  if (full.includes('/.github/workflows/') && (name.endsWith('.yml') || name.endsWith('.yaml'))) return 'yaml'

  // Yaygın uzantılar
  if (name.endsWith('.go')) return 'go'
  if (name.endsWith('.php')) return 'php'
  if (name.endsWith('.html') || name.endsWith('.htm')) return 'html'
  if (name.endsWith('.css')) return 'css'
  if (name.endsWith('.scss') || name.endsWith('.sass') || name.endsWith('.less')) return 'css'
  if (
    name.endsWith('.js') ||
    name.endsWith('.mjs') ||
    name.endsWith('.cjs') ||
    name.endsWith('.jsx')
  ) {
    return 'javascript'
  }
  if (name.endsWith('.ts') || name.endsWith('.tsx')) return 'typescript'
  if (name.endsWith('.json')) return 'json'
  if (name.endsWith('.xml')) return 'xml'
  if (name.endsWith('.yaml') || name.endsWith('.yml')) return 'yaml'
  if (name.endsWith('.md')) return 'markdown'
  if (name.endsWith('.sql')) return 'sql'
  if (name.endsWith('.sh') || name.endsWith('.bash') || name.endsWith('.zsh')) return 'shell'
  if (name.endsWith('.ini') || name.endsWith('.conf') || name.endsWith('.cfg') || name.endsWith('.cnf')) return 'ini'
  return 'plaintext'
}

// Heavy bundle: editor sadece dosya açılınca lazy yüklensin.
const MonacoEditor = lazy(async () => {
  const [mod, loaderMod, monacoMod] = await Promise.all([
    import('@monaco-editor/react'),
    import('@monaco-editor/loader'),
    import('monaco-editor'),
  ])
  // CSP uyumu: CDN loader yerine local bundle.
  loaderMod.default.config({ monaco: monacoMod })
  return mod
})

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
  return /^[\w.-]+$/.test(t)
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

function isZipArchiveFileName(name: string): boolean {
  return /\.zip$/i.test(name)
}

/** Mevcut öğe ile aynı klasörde .zip oluşturma yolu (sıkıştır için). */
function suggestedZipTargetPath(rel: string, isDir: boolean): string {
  const parent = parentPath(rel)
  const base = rel.split('/').filter(Boolean).pop() || 'archive'
  if (isDir) return joinRel(parent, `${base}.zip`)
  const i = base.lastIndexOf('.')
  const stem = i > 0 ? base.slice(0, i) : base
  return joinRel(parent, `${stem || 'archive'}.zip`)
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

async function fetchAllFileEntries(domainId: number, dirRel: string): Promise<ListEntry[]> {
  const all: ListEntry[] = []
  let offset = 0
  const limit = 2000
  for (;;) {
    const u = new URLSearchParams()
    u.set('limit', String(limit))
    u.set('offset', String(offset))
    u.set('sort', 'name')
    u.set('order', 'asc')
    if (dirRel) u.set('path', dirRel)
    const { data } = await api.get<{ entries?: ListEntry[]; total?: number }>(
      `/domains/${domainId}/files?${u.toString()}`,
    )
    const chunk = data?.entries ?? []
    all.push(...chunk)
    const total = data?.total ?? 0
    if (chunk.length === 0 || all.length >= total) break
    offset += limit
  }
  return all
}

function isAlreadyExistsErrorMessage(msg: string): boolean {
  const s = msg.toLowerCase()
  return s.includes('already exists') || s.includes('file exists') || s.includes('exist')
}

type FsEntryLike = {
  isFile?: boolean
  isDirectory?: boolean
  name?: string
  file?: (cb: (file: File) => void, onError?: (err: unknown) => void) => void
  createReader?: () => {
    readEntries: (cb: (entries: FsEntryLike[]) => void, onError?: (err: unknown) => void) => void
  }
}

async function readAllDirEntries(reader: {
  readEntries: (cb: (entries: FsEntryLike[]) => void, onError?: (err: unknown) => void) => void
}): Promise<FsEntryLike[]> {
  const out: FsEntryLike[] = []
  for (;;) {
    const chunk = await new Promise<FsEntryLike[]>((resolve) => {
      reader.readEntries((entries) => resolve(entries ?? []), () => resolve([]))
    })
    if (!chunk.length) break
    out.push(...chunk)
  }
  return out
}

async function walkDroppedEntry(
  entry: FsEntryLike,
  prefix: string,
  out: FileWithRelPath[],
): Promise<void> {
  if (entry.isFile && entry.file) {
    const f = await new Promise<File | null>((resolve) => {
      entry.file!(
        (file) => resolve(file),
        () => resolve(null),
      )
    })
    if (!f) return
    const name = entry.name || f.name
    const rel = prefix ? `${prefix}/${name}` : name
    // browser file objesine göreli yol bilgisi ekle
    Object.defineProperty(f, 'webkitRelativePath', { value: rel, configurable: true })
    out.push(f as FileWithRelPath)
    return
  }
  if (entry.isDirectory && entry.createReader) {
    const name = entry.name || ''
    const nextPrefix = name ? (prefix ? `${prefix}/${name}` : name) : prefix
    const reader = entry.createReader()
    const children = await readAllDirEntries(reader)
    for (const child of children) {
      await walkDroppedEntry(child, nextPrefix, out)
    }
  }
}

async function getFilesFromDropEvent(evt: DropEvent): Promise<(File | DataTransferItem)[]> {
  if ('target' in (evt as any) && (evt as any).target && (evt as any).dataTransfer == null) {
    const input = (evt as any).target as HTMLInputElement
    return Array.from(input?.files || [])
  }
  const dragEvt = evt as DragEvent
  const dt = dragEvt.dataTransfer
  if (!dt) return []

  const items = Array.from(dt.items || [])
  const hasWebkitEntries = items.some((it: any) => typeof it.webkitGetAsEntry === 'function')
  if (!hasWebkitEntries) {
    return Array.from(dt.files || [])
  }

  const out: FileWithRelPath[] = []
  for (const item of items) {
    const anyItem = item as any
    const entry = (typeof anyItem.webkitGetAsEntry === 'function'
      ? anyItem.webkitGetAsEntry()
      : null) as FsEntryLike | null
    if (!entry) continue
    await walkDroppedEntry(entry, '', out)
  }
  return out
}

const FILEMGR_HELP_KEY = 'hostvim_filemgr_help_seen'

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

  useEffect(() => {
    const close = () => setContextMenu(null)
    window.addEventListener('click', close)
    window.addEventListener('scroll', close)
    return () => {
      window.removeEventListener('click', close)
      window.removeEventListener('scroll', close)
    }
  }, [])

  // Rename/Move modal state
  const [renameDialog, setRenameDialog] = useState<{ from: string; newName: string } | null>(null)
  const [moveDialog, setMoveDialog] = useState<{ from: string; targetDir: string; baseName: string } | null>(null)
  const [trashOpen, setTrashOpen] = useState(false)
  const [chmodDialog, setChmodDialog] = useState<{ path: string; mode: string } | null>(null)
  const [clipboardPath, setClipboardPath] = useState<string | null>(null)
  const [clipboardMode, setClipboardMode] = useState<'copy' | 'cut'>('copy')
  const [contextMenu, setContextMenu] = useState<{ x: number; y: number; rel: string; isDir: boolean } | null>(null)
  const [archiveUi, setArchiveUi] = useState<{ kind: 'zip' | 'unzip'; complete: boolean } | null>(null)
  const [pasteConflictDialog, setPasteConflictDialog] = useState<{
    open: boolean
    sourcePath: string
    destDir: string
    baseTarget: string
  } | null>(null)

  const [uploadBusy, setUploadBusy] = useState(false)
  const [uploadProgressView, setUploadProgressView] = useState<FileUploadProgressView | null>(null)
  const [uploadConflictDialog, setUploadConflictDialog] = useState<{
    open: boolean
    basePath: string
    noConflict: NormalizedUploadItem[]
    conflicts: UploadConflictRow[]
  } | null>(null)

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
    if (domainOptions.length === 0) return
    const hasCurrent = domainId !== '' && domainOptions.some((d) => d.id === domainId)
    if (hasCurrent) return

    const firstId = domainOptions[0]?.id
    if (!firstId || !Number.isFinite(firstId)) return

    setDomainId(firstId)
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev)
        next.set('domain', String(firstId))
        return next
      },
      { replace: true },
    )
  }, [domainOptions, domainId, setSearchParams])

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

  const postOneUpload = useCallback(
    async (item: NormalizedUploadItem, onProgress?: (loaded: number, total: number) => void) => {
      const fd = new FormData()
      fd.append('file', item.file, item.baseName)
      fd.append('path', item.parentRel)
      const sizeHint = item.file.size > 0 ? item.file.size : 0
      await api.post(`/domains/${domainId}/files/upload`, fd, {
        onUploadProgress: (ev) => {
          const total = ev.total && ev.total > 0 ? ev.total : sizeHint
          onProgress?.(ev.loaded, total > 0 ? total : Math.max(ev.loaded, 1))
        },
      })
    },
    [domainId],
  )

  const deleteRemotePath = useCallback(
    async (rel: string) => {
      await api.delete(`/domains/${domainId}/files`, {
        params: { path: rel },
        data: { path: rel },
      })
    },
    [domainId],
  )

  const runUploadItems = useCallback(
    async (items: NormalizedUploadItem[]) => {
      if (items.length === 0) {
        await qc.invalidateQueries({ queryKey: ['files', domainId, path] })
        return
      }
      // Hedefte eksik klasörleri önce oluştur (recursive).
      const needDirs = new Set<string>()
      for (const it of items) {
        const segs = it.parentRel.split('/').filter(Boolean)
        let acc = ''
        for (const seg of segs) {
          acc = acc ? `${acc}/${seg}` : seg
          if (isSafeRelativePath(acc)) needDirs.add(acc)
        }
      }
      for (const dir of Array.from(needDirs).sort((a, b) => a.length - b.length)) {
        try {
          await api.post(`/domains/${domainId}/files/mkdir`, { path: dir })
        } catch (err: unknown) {
          const ax = err as { response?: { data?: { message?: string } } }
          const msg = String(ax.response?.data?.message ?? '')
          if (!isAlreadyExistsErrorMessage(msg)) {
            toast.error(msg || t('files.mkdir_err'))
            throw err
          }
        }
      }
      const weights = items.map((it) => Math.max(1, it.file.size || 0))
      const overallTotal = weights.reduce((a, b) => a + b, 0)

      let ok = 0
      let failed = 0
      let completedSum = 0

      try {
        setUploadProgressView({
          totalFiles: items.length,
          currentIndex: 0,
          currentName: items[0]?.baseName ?? '',
          currentLoaded: 0,
          currentTotal: weights[0] ?? 1,
          overallLoaded: 0,
          overallTotal,
          speedBps: 0,
        })

        for (let i = 0; i < items.length; i++) {
          const it = items[i]
          const w = weights[i] ?? 1
          let lastTime = performance.now()
          let lastLoaded = 0
          let emaSpeed = 0

          setUploadProgressView((prev) =>
            prev
              ? {
                  ...prev,
                  currentIndex: i,
                  currentName: it.baseName,
                  currentLoaded: 0,
                  currentTotal: w,
                }
              : null,
          )

          try {
            await postOneUpload(it, (loaded, total) => {
              const tsize = total > 0 ? total : w
              const ld = Math.min(loaded, tsize)
              const now = performance.now()
              const dt = (now - lastTime) / 1000
              if (dt >= 0.08) {
                const dbytes = ld - lastLoaded
                if (dbytes >= 0) {
                  const inst = dbytes / dt
                  emaSpeed = emaSpeed > 0 ? emaSpeed * 0.62 + inst * 0.38 : inst
                }
                lastTime = now
                lastLoaded = ld
              }
              const overallLoaded = Math.min(overallTotal, completedSum + ld)
              setUploadProgressView((prev) =>
                prev
                  ? {
                      ...prev,
                      currentLoaded: ld,
                      currentTotal: tsize,
                      overallLoaded,
                      speedBps: emaSpeed,
                    }
                  : null,
              )
            })
            completedSum += w
            ok++
          } catch (err: unknown) {
            failed++
            const ax = err as {
              response?: { data?: { error?: string; message?: string; errors?: { file?: string[] } } }
            }
            const d = ax.response?.data
            const v = d?.errors?.file?.[0]
            toast.error(v ?? d?.error ?? d?.message ?? t('files.upload_err'))
          }
        }

        setOffset(0)
        await qc.invalidateQueries({ queryKey: ['files', domainId, path] })
        await qc.refetchQueries({ queryKey: ['files', domainId, path] })
        if (failed === 0 && ok > 0) {
          toast.success(t('files.upload_ok'))
        } else if (ok > 0 && failed > 0) {
          toast.success(t('files.upload_partial_ok', { ok, failed: t('files.upload_partial_fail', { n: failed }) }))
        } else if (failed > 0 && ok === 0) {
          toast.error(t('files.upload_err'))
        }
      } finally {
        setUploadProgressView(null)
      }
    },
    [domainId, path, postOneUpload, qc, t],
  )

  const processIncomingFiles = useCallback(
    async (rawFiles: File[]) => {
      if (!domainId || rawFiles.length === 0) return
      const basePath = path
      const items: NormalizedUploadItem[] = []
      for (const file of rawFiles) {
        const wrp = (file as FileWithRelPath).webkitRelativePath
        const relFromBase =
          wrp && String(wrp).trim() !== '' ? String(wrp).replace(/\\/g, '/') : file.name
        if (!isSafeRelativePath(relFromBase)) {
          toast.error(t('files.invalid_path'))
          continue
        }
        const segs = relFromBase.split('/').filter(Boolean)
        if (segs.length === 0) continue
        const baseName = segs[segs.length - 1]
        const parentSub = segs.length > 1 ? segs.slice(0, -1).join('/') : ''
        const parentRel = parentSub ? joinRel(basePath, parentSub) : basePath
        items.push({ file, relFromBase, parentRel, baseName })
      }
      if (items.length === 0) return

      setUploadBusy(true)
      try {
        const rootListing = await fetchAllFileEntries(domainId, basePath)
        for (const it of items) {
          const segs = it.relFromBase.split('/').filter(Boolean)
          const top = segs[0]
          const te = rootListing.find((e) => e.name === top)
          if (segs.length > 1 && te && !te.is_dir) {
            toast.error(t('files.upload_path_blocked', { name: top }))
            setUploadBusy(false)
            return
          }
        }

        const parentCache = new Map<string, ListEntry[]>()
        parentCache.set(basePath, rootListing)
        const loadParent = async (p: string) => {
          if (!parentCache.has(p)) {
            parentCache.set(p, await fetchAllFileEntries(domainId, p))
          }
          return parentCache.get(p)!
        }

        const noConflict: NormalizedUploadItem[] = []
        const conflicts: UploadConflictRow[] = []
        for (const it of items) {
          const list = await loadParent(it.parentRel)
          const ex = list.find((e) => e.name === it.baseName)
          if (ex) {
            conflicts.push({ ...it, existing: ex })
          } else {
            noConflict.push(it)
          }
        }

        if (conflicts.length === 0) {
          await runUploadItems(items)
        } else {
          setUploadConflictDialog({
            open: true,
            basePath,
            noConflict,
            conflicts,
          })
        }
      } catch (err: unknown) {
        const ax = err as { response?: { data?: { message?: string } } }
        toast.error(ax.response?.data?.message ?? String(err))
      } finally {
        setUploadBusy(false)
      }
    },
    [domainId, path, runUploadItems, t],
  )

  const trashMoveM = useMutation({
    mutationFn: async (rel: string) => {
      await api.post(`/domains/${domainId}/files/trash/move`, { path: rel })
    },
    onSuccess: () => {
      toast.success(t('files.moved_to_trash'))
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

  const copyM = useMutation({
    mutationFn: async (vars: { from: string; to: string }) =>
      api.post(`/domains/${domainId}/files/copy`, vars),
    onSuccess: () => {
      toast.success(t('files.copied'))
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
      setOffset(0)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const chmodM = useMutation({
    mutationFn: async (vars: { path: string; mode: string }) =>
      api.post(`/domains/${domainId}/files/chmod`, vars),
    onSuccess: () => {
      toast.success(t('files.chmod_ok'))
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
      setChmodDialog(null)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const bulkChmodM = useMutation({
    mutationFn: async (vars: { paths: string[]; mode: string }) => {
      const settled = await Promise.allSettled(
        vars.paths.map((p) => api.post(`/domains/${domainId}/files/chmod`, { path: p, mode: vars.mode })),
      )
      const failed = settled.filter((x) => x.status === 'rejected').length
      return { total: vars.paths.length, failed }
    },
    onSuccess: ({ total, failed }) => {
      if (failed === 0) toast.success(t('files.bulk_chmod_ok', { total }))
      else toast.error(t('files.bulk_chmod_fail', { failed, total }))
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const bulkTrashMoveM = useMutation({
    mutationFn: async (paths: string[]) => {
      const settled = await Promise.allSettled(
        paths.map((p) => api.post(`/domains/${domainId}/files/trash/move`, { path: p })),
      )
      const failed = settled.filter((x) => x.status === 'rejected').length
      return { total: paths.length, failed }
    },
    onSuccess: ({ total, failed }) => {
      if (failed === 0) toast.success(t('files.bulk_trash_ok', { total }))
      else toast.error(t('files.bulk_trash_fail', { failed, total }))
      setSelectedIds(new Set())
      setSelected(null)
      setOffset(0)
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const trashQ = useQuery({
    queryKey: ['files-trash', domainId],
    enabled: Boolean(domainId) && trashOpen,
    queryFn: async () => {
      const { data } = await api.get<{ items: TrashItem[] }>(`/domains/${domainId}/files/trash`)
      return data?.items ?? []
    },
  })

  const trashRestoreM = useMutation({
    mutationFn: async (id: string) => api.post(`/domains/${domainId}/files/trash/restore`, { id }),
    onSuccess: async () => {
      toast.success(t('files.trash_restored'))
      await qc.invalidateQueries({ queryKey: ['files-trash', domainId] })
      await qc.invalidateQueries({ queryKey: ['files', domainId, path] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const trashDeleteM = useMutation({
    mutationFn: async (id: string) =>
      api.delete(`/domains/${domainId}/files/trash/item`, { params: { id } }),
    onSuccess: async () => {
      toast.success(t('files.trash_deleted'))
      await qc.invalidateQueries({ queryKey: ['files-trash', domainId] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const trashEmptyM = useMutation({
    mutationFn: async () => api.delete(`/domains/${domainId}/files/trash/empty`),
    onSuccess: async () => {
      toast.success(t('files.trash_emptied'))
      await qc.invalidateQueries({ queryKey: ['files-trash', domainId] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const zipM = useMutation({
    mutationFn: async (vars: { source: string; target: string }) =>
      api.post(`/domains/${domainId}/files/zip`, vars),
    onSuccess: () => {
      toast.success(t('files.zip_ok'))
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
      setOffset(0)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const unzipM = useMutation({
    mutationFn: async (vars: { archive: string; target_dir: string }) =>
      api.post(`/domains/${domainId}/files/unzip`, vars),
    onSuccess: () => {
      toast.success(t('files.unzip_ok'))
      qc.invalidateQueries({ queryKey: ['files', domainId, path] })
      setOffset(0)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string } } }
      toast.error(ax.response?.data?.message ?? String(err))
    },
  })

  const runZipArchive = useCallback(
    async (vars: { source: string; target: string }) => {
      setArchiveUi({ kind: 'zip', complete: false })
      try {
        await zipM.mutateAsync(vars)
        setArchiveUi({ kind: 'zip', complete: true })
        await new Promise((r) => setTimeout(r, 680))
      } catch {
        /* toast zipM */
      } finally {
        setArchiveUi(null)
      }
    },
    [zipM],
  )

  const runUnzipArchive = useCallback(
    async (vars: { archive: string; target_dir: string }) => {
      setArchiveUi({ kind: 'unzip', complete: false })
      try {
        await unzipM.mutateAsync(vars)
        setArchiveUi({ kind: 'unzip', complete: true })
        await new Promise((r) => setTimeout(r, 680))
      } catch {
        /* toast unzipM */
      } finally {
        setArchiveUi(null)
      }
    },
    [unzipM],
  )

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
      void processIncomingFiles(accepted)
    },
    [processIncomingFiles],
  )

  const { getRootProps, getInputProps, isDragActive, open } = useDropzone({
    onDrop,
    disabled: domainId === '' || uploadBusy,
    noClick: true,
    multiple: true,
    getFilesFromEvent: getFilesFromDropEvent,
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

  const confirmBulkTrashSelection = () => {
    const paths = entries
      .filter((e) => selectedIds.has(rowKey(e)))
      .map((e) => joinRel(path, e.name))
      .filter((p) => isSafeRelativePath(p))
    if (paths.length === 0) {
      toast.error(t('files.bulk_delete_none'))
      return
    }
    if (!window.confirm(t('files.bulk_delete_confirm', { count: paths.length }))) return
    bulkTrashMoveM.mutate(paths)
  }

  const splitExt = (name: string): { base: string; ext: string } => {
    const i = name.lastIndexOf('.')
    if (i <= 0) return { base: name, ext: '' }
    return { base: name.slice(0, i), ext: name.slice(i) }
  }
  const isConflictError = (err: unknown): boolean => {
    const ax = err as { response?: { data?: { message?: string; error?: string } } }
    const m = String(ax?.response?.data?.message ?? ax?.response?.data?.error ?? '').toLowerCase()
    return m.includes('target already exists')
  }
  const executePasteWithStrategy = async (
    sourcePath: string,
    destDir: string,
    strategy: 'rename' | 'overwrite' | 'skip',
  ) => {
    const name = sourcePath.split('/').pop() || 'copy'
    const baseTarget = joinRel(destDir, name)
    const op = async (from: string, to: string) => {
      if (clipboardMode === 'cut') {
        await api.post(`/domains/${domainId}/files/move`, { from, to })
      } else {
        await api.post(`/domains/${domainId}/files/copy`, { from, to })
      }
    }

    if (strategy === 'skip') {
      toast(t('files.paste_skipped'))
      return
    }

    if (strategy === 'overwrite') {
      await api.delete(`/domains/${domainId}/files`, {
        params: { path: baseTarget },
        data: { path: baseTarget },
      })
      await op(sourcePath, baseTarget)
    } else {
      const { base, ext } = splitExt(name)
      let done = false
      for (let i = 1; i <= 200; i++) {
        const candidateName = `${base}-copy-${i}${ext}`
        const candidate = joinRel(destDir, candidateName)
        try {
          await op(sourcePath, candidate)
          done = true
          break
        } catch (e) {
          if (!isConflictError(e)) throw e
        }
      }
      if (!done) throw new Error('Could not create unique target')
    }
  }

  const runClipboardPaste = async (destDir: string) => {
    if (!clipboardPath) return
    const name = clipboardPath.split('/').pop() || 'copy'
    const baseTarget = joinRel(destDir, name)
    const op = async (from: string, to: string) => {
      if (clipboardMode === 'cut') {
        await api.post(`/domains/${domainId}/files/move`, { from, to })
      } else {
        await api.post(`/domains/${domainId}/files/copy`, { from, to })
      }
    }
    try {
      await op(clipboardPath, baseTarget)
    } catch (err) {
      if (!isConflictError(err)) throw err
      setPasteConflictDialog({
        open: true,
        sourcePath: clipboardPath,
        destDir,
        baseTarget,
      })
      return
    }

    if (clipboardMode === 'cut') {
      setClipboardPath(null)
    }
    await qc.invalidateQueries({ queryKey: ['files', domainId, path] })
    toast.success(t('files.paste_done'))
  }
  const contextMenuPos = useMemo(() => {
    if (!contextMenu) return null
    const menuW = 224
    const menuH = 480
    const pad = 8
    const ww = window.innerWidth || 1280
    const wh = window.innerHeight || 720

    let left = contextMenu.x
    let top = contextMenu.y

    if (left + menuW > ww - pad) left = Math.max(pad, ww - menuW - pad)
    if (top + menuH > wh - pad) top = Math.max(pad, top - menuH)
    if (top < pad) top = pad

    return { left, top }
  }, [contextMenu])

  const dirtyCount = tabs.filter((x) => !x.loading && x.content !== x.original).length
  const activeReadOnly = !!(currentTab && isExecutionRiskFilePath(currentTab.path))
  const hasDirtyReadOnly = tabs.some(
    (tab) => !tab.loading && tab.content !== tab.original && isExecutionRiskFilePath(tab.path),
  )
  const contextBaseDir = contextMenu ? (contextMenu.isDir ? contextMenu.rel : parentPath(contextMenu.rel)) : path

  return (
    <div
      {...getRootProps({
        className: clsx(
          'space-y-2 min-h-[min(85vh,56rem)] rounded-xl p-0.5 -m-0.5 outline-none transition-colors sm:space-y-3',
          domainId !== '' && 'border-2 border-dashed border-transparent',
          isDragActive &&
            'border-primary-400 bg-primary-50/25 ring-2 ring-primary-500/35 dark:border-primary-500/60 dark:bg-primary-950/20',
        ),
      })}
    >
      <input {...getInputProps()} />

      <div className="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_min(16rem,36vw)] lg:grid-cols-[minmax(0,1fr)_18rem] xl:grid-cols-[minmax(0,1fr)_20rem] md:items-start md:gap-4 lg:gap-5">
        <div className="card order-1 min-w-0 overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-gray-700 dark:shadow-none">
          {domainId === '' ? (
            <div className="flex min-h-[min(50vh,22rem)] flex-col items-center justify-center gap-3 px-6 py-12 text-center">
              <Folder className="h-12 w-12 text-gray-300 dark:text-gray-600" aria-hidden />
              <p className="max-w-sm text-sm text-gray-500 dark:text-gray-400">{t('files.select_domain_hint')}</p>
            </div>
          ) : (
            <>
            <div className="border-b border-gray-100 bg-gray-50/40 dark:border-gray-800 dark:bg-gray-950/30">
              <div className="flex flex-wrap items-center gap-2 px-2 py-2 text-sm sm:px-3">
                <button
                  type="button"
                  className="rounded-md p-1.5 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 disabled:opacity-40"
                  disabled={path === ''}
                  title={t('common.back')}
                  aria-label={t('common.back')}
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
                <div className="relative shrink-0" ref={fileOpsRef}>
                  <button
                    type="button"
                    aria-expanded={fileOpsOpen}
                    aria-haspopup="menu"
                    className="inline-flex min-h-[34px] items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs font-medium text-gray-800 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800/80 dark:text-gray-100 dark:hover:bg-gray-800 sm:text-sm"
                    onClick={() => setFileOpsOpen((o) => !o)}
                  >
                    <span className="inline-flex items-center gap-2 truncate">
                      <Folder className="h-4 w-4 shrink-0 text-amber-500" />
                      {t('files.file_operations')}
                    </span>
                    <ChevronDown className="h-4 w-4 shrink-0 opacity-70" />
                  </button>
                  {fileOpsOpen && (
                    <div
                      role="menu"
                      className="absolute right-0 top-full z-40 mt-1 w-72 max-h-[min(70vh,28rem)] overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-xl dark:border-gray-700 dark:bg-gray-900"
                    >
                      <button
                        type="button"
                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 disabled:opacity-40 dark:hover:bg-gray-800"
                        disabled={uploadBusy}
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
                      <div className="my-1 border-t border-gray-100 dark:border-gray-800" />
                      <button
                        type="button"
                        disabled={selectedIds.size === 0 || bulkTrashMoveM.isPending}
                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-red-300 dark:hover:bg-red-950/30"
                        onClick={() => {
                          setFileOpsOpen(false)
                          confirmBulkTrashSelection()
                        }}
                      >
                        <Trash2 className="h-4 w-4" />
                        {t('files.op_bulk_delete', { count: selectedIds.size })}
                      </button>
                      <button
                        type="button"
                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
                        onClick={() => {
                          setFileOpsOpen(false)
                          const base = selected ? joinRel(path, selected) : ''
                          const from = window.prompt(t('files.copy_source_prompt'), base)
                          if (!from?.trim() || !isSafeRelativePath(from.trim())) return
                          const to = window.prompt(t('files.copy_target_prompt'), `${from.trim()}-copy`)
                          if (!to?.trim() || !isSafeRelativePath(to.trim())) return
                          copyM.mutate({ from: from.trim(), to: to.trim() })
                        }}
                      >
                        {t('files.op_copy')}
                      </button>
                      <button
                        type="button"
                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
                        onClick={() => {
                          setFileOpsOpen(false)
                          const rel = selected ? joinRel(path, selected) : path
                          setChmodDialog({ path: rel, mode: '644' })
                        }}
                      >
                        <Unlock className="h-4 w-4" />
                        {t('files.op_chmod')}
                      </button>
                      <button
                        type="button"
                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
                        onClick={() => {
                          setFileOpsOpen(false)
                          const source = selected ? joinRel(path, selected) : path
                          const trimmed = source.trim()
                          if (!isSafeRelativePath(trimmed)) {
                            toast.error(t('files.invalid_path'))
                            return
                          }
                          const isDir = selected
                            ? (entries.find((e) => e.name === selected)?.is_dir ?? false)
                            : true
                          const target = suggestedZipTargetPath(selected ? joinRel(path, selected) : path, isDir)
                          void runZipArchive({ source: trimmed, target })
                        }}
                      >
                        {t('files.op_zip')}
                      </button>
                      <button
                        type="button"
                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
                        onClick={() => {
                          setFileOpsOpen(false)
                          const archiveDefault = selected ? joinRel(path, selected) : ''
                          if (!archiveDefault.trim()) {
                            const archive = window.prompt(t('files.unzip_archive_prompt'), '')
                            if (!archive?.trim() || !isSafeRelativePath(archive.trim())) return
                            const leaf = archive.trim().split('/').pop() || ''
                            if (!isZipArchiveFileName(leaf)) {
                              toast.error(t('files.only_zip_supported'))
                              return
                            }
                            void runUnzipArchive({ archive: archive.trim(), target_dir: path })
                            return
                          }
                          const leaf = archiveDefault.split('/').pop() || ''
                          if (!isZipArchiveFileName(leaf)) {
                            toast.error(t('files.only_zip_supported'))
                            return
                          }
                          void runUnzipArchive({ archive: archiveDefault.trim(), target_dir: path })
                        }}
                      >
                        {t('files.op_unzip')}
                      </button>
                    </div>
                  )}
                </div>
              </div>
              {selectedIds.size > 0 && (
                <div className="flex flex-wrap items-center gap-2 border-t border-amber-200/70 bg-amber-50/95 px-2 py-1.5 dark:border-amber-900/50 dark:bg-amber-950/35 sm:px-3">
                  <span className="text-xs font-semibold tabular-nums text-amber-900 dark:text-amber-200">
                    {t('files.selection_count', { count: selectedIds.size })}
                  </span>
                  <button
                    type="button"
                    className="btn-secondary py-1.5 text-xs"
                    disabled={bulkChmodM.isPending}
                    onClick={() => {
                      const mode = window.prompt(t('files.bulk_chmod_prompt'), '755')
                      if (!mode?.trim() || !/^[0-7]{3,4}$/.test(mode.trim())) {
                        toast.error(t('files.invalid_mode'))
                        return
                      }
                      const paths = entries
                        .filter((e) => selectedIds.has(rowKey(e)))
                        .map((e) => joinRel(path, e.name))
                        .filter((p) => isSafeRelativePath(p))
                      if (paths.length === 0) return
                      bulkChmodM.mutate({ paths, mode: mode.trim() })
                    }}
                  >
                    {t('files.bulk_chmod_btn', { count: selectedIds.size })}
                  </button>
                  <button
                    type="button"
                    className="inline-flex items-center gap-1.5 rounded-md border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-800 hover:bg-red-100 disabled:opacity-50 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/60"
                    disabled={bulkTrashMoveM.isPending}
                    onClick={confirmBulkTrashSelection}
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                    {t('files.bulk_delete_btn', { count: selectedIds.size })}
                  </button>
                </div>
              )}
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
                'border-b border-gray-100 px-3 py-1.5 text-center text-[11px] leading-snug dark:border-gray-800 sm:text-xs',
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
              className="min-h-[min(52vh,28rem)] max-h-[min(78vh,calc(100vh-11rem))] overflow-x-auto overflow-y-auto overscroll-x-contain"
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
                          onContextMenu={(ev) => {
                            ev.preventDefault()
                            setContextMenu({ x: ev.clientX, y: ev.clientY, rel, isDir: e.is_dir })
                            setSelected(e.name)
                          }}
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
                            {[e.mode || '---', e.owner || '-', e.group || '-'].join(' ')}
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
                                  trashMoveM.mutate(rel)
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
                        onContextMenu={(ev) => {
                          ev.preventDefault()
                          setContextMenu({ x: ev.clientX, y: ev.clientY, rel, isDir: e.is_dir })
                          setSelected(e.name)
                        }}
                        className={clsx(
                          'relative flex flex-col items-center gap-1 rounded-lg border p-3 pt-7 text-center text-sm outline-none',
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
                        <label
                          className="absolute left-2 top-2 z-10 flex cursor-pointer items-center rounded p-0.5 hover:bg-black/5 dark:hover:bg-white/10"
                          onClick={(ev) => ev.stopPropagation()}
                          onKeyDown={(ev) => ev.stopPropagation()}
                        >
                          <input
                            type="checkbox"
                            className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                            checked={selectedIds.has(rk)}
                            aria-label={t('files.select_for_bulk', { name: e.name })}
                            onChange={() => {
                              setSelectedIds((prev) => {
                                const next = new Set(prev)
                                if (next.has(rk)) next.delete(rk)
                                else next.add(rk)
                                return next
                              })
                            }}
                          />
                        </label>
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

        <aside
          className={clsx(
            'card order-2 flex w-full min-w-0 flex-col gap-3.5 rounded-xl border border-gray-200 p-3 shadow-sm dark:border-gray-700 dark:shadow-none md:sticky md:top-[max(1rem,env(safe-area-inset-top,0px))] md:z-[5] md:self-start xl:p-4',
            domainId === '' && 'opacity-95',
          )}
        >
          <div>
            <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
              {t('files.sidebar_site')}
            </p>
            <label className="sr-only" htmlFor="file-manager-domain">
              {t('domains.name')}
            </label>
            <select
              id="file-manager-domain"
              className="input w-full min-w-0 text-sm"
              value={domainId === '' ? '' : String(domainId)}
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

          <div className={clsx(domainId === '' && 'pointer-events-none opacity-50')}>
            <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
              {t('files.search_field_label')}
            </p>
            <input
              id="file-manager-search"
              className="input mb-2 min-h-[38px] w-full py-2 text-sm"
              value={searchQ}
              disabled={domainId === ''}
              onChange={(e) => setSearchQ(e.target.value)}
              placeholder={t('files.toolbar_search_placeholder')}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && searchQ.trim().length >= 2) {
                  searchM.mutate(searchQ.trim())
                }
              }}
            />
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                role="switch"
                aria-checked={searchIncludeSubdirs}
                title={t('files.search_include_subdirs')}
                disabled={domainId === ''}
                onClick={() => setSearchIncludeSubdirs((v) => !v)}
                className={clsx(
                  'inline-flex min-h-[44px] flex-1 items-center justify-center gap-1 rounded-lg border px-2 text-xs font-semibold transition-colors sm:min-h-10',
                  searchIncludeSubdirs
                    ? 'border-primary-500/60 bg-primary-50 text-primary-800 dark:border-primary-500/40 dark:bg-primary-950/45 dark:text-primary-200'
                    : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800',
                )}
              >
                <Folder className="h-3.5 w-3.5 shrink-0 opacity-80" aria-hidden />
                <span className="truncate">{t('files.search_subdirs_short')}</span>
              </button>
              <button
                type="button"
                className="btn-primary inline-flex min-h-[44px] flex-1 items-center justify-center gap-1 px-2 text-xs sm:min-h-10 sm:text-sm"
                disabled={domainId === '' || searchQ.trim().length < 2 || searchM.isPending}
                onClick={() => searchM.mutate(searchQ.trim())}
                title={t('files.search_run')}
              >
                <Search className="h-4 w-4 shrink-0" />
                {t('files.search_run')}
              </button>
            </div>
          </div>

          <div className={clsx('flex gap-2', domainId === '' && 'pointer-events-none opacity-50')}>
            <button
              type="button"
              className="btn-secondary inline-flex flex-1 items-center justify-center gap-2 py-2 text-sm"
              onClick={() => {
                void domainsQ.refetch()
                void filesQ.refetch()
              }}
              title={t('common.refresh')}
            >
              <RefreshCw className="h-4 w-4" />
              {t('common.refresh')}
            </button>
          </div>

          <button
            type="button"
            className={clsx(
              'btn-secondary inline-flex w-full items-center justify-center gap-2 py-2.5 text-sm font-medium',
              domainId === '' && 'pointer-events-none opacity-50',
            )}
            disabled={domainId === ''}
            onClick={() => setTrashOpen(true)}
          >
            <Trash2 className="h-4 w-4" />
            {t('files.recycle_bin')}
          </button>

          {typeof domainId === 'number' && domainId > 0 && (
            <div>
              <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {t('files.smart_shortcuts')}
              </p>
              <div className="grid grid-cols-2 gap-1.5">
                <button
                  type="button"
                  className="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-center text-[11px] font-medium leading-tight hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:hover:bg-gray-800 sm:text-xs"
                  onClick={() => {
                    setFilePath('')
                    void openFileWrapped('wp-config.php')
                  }}
                >
                  {t('files.shortcut_wp_config')}
                </button>
                <button
                  type="button"
                  className="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-center text-[11px] font-medium leading-tight hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:hover:bg-gray-800 sm:text-xs"
                  onClick={() => {
                    setFilePath('')
                    void openFileWrapped('.env')
                  }}
                >
                  {t('files.shortcut_env')}
                </button>
                <button
                  type="button"
                  className="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-center text-[11px] font-medium leading-tight hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:hover:bg-gray-800 sm:text-xs"
                  onClick={() => setFilePath('storage')}
                >
                  {t('files.shortcut_storage')}
                </button>
                <button
                  type="button"
                  className="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-center text-[11px] font-medium leading-tight hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:hover:bg-gray-800 sm:text-xs"
                  onClick={() => setFilePath('public')}
                >
                  {t('files.shortcut_public')}
                </button>
              </div>
            </div>
          )}

          <div className={clsx('pt-1', domainId === '' && 'pointer-events-none opacity-50')}>
            <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
              {t('files.view_mode_label')}
            </p>
            <div className="inline-flex w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-600">
              <button
                type="button"
                className={clsx(
                  'flex flex-1 items-center justify-center gap-1.5 py-2 text-xs font-medium sm:text-sm',
                  viewMode === 'grid'
                    ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-200'
                    : 'bg-white text-gray-500 hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800',
                )}
                title={t('files.view_grid')}
                onClick={() => setViewMode('grid')}
              >
                <LayoutGrid className="h-4 w-4" />
                {t('files.view_grid')}
              </button>
              <button
                type="button"
                className={clsx(
                  'flex flex-1 items-center justify-center gap-1.5 border-l border-gray-200 py-2 text-xs font-medium dark:border-gray-600 sm:text-sm',
                  viewMode === 'list'
                    ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-200'
                    : 'bg-white text-gray-500 hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800',
                )}
                title={t('files.view_list')}
                onClick={() => setViewMode('list')}
              >
                <ListIcon className="h-4 w-4" />
                {t('files.view_list')}
              </button>
            </div>
          </div>
        </aside>
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

      {chmodDialog && (
        <div className="fixed inset-0 z-[58] flex items-center justify-center bg-black/60 p-2 sm:p-4">
          <div className="mx-auto w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
              <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{t('files.op_chmod')}</h2>
              <button
                type="button"
                className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                onClick={() => setChmodDialog(null)}
                aria-label="Kapat"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="p-4 space-y-3">
              <div>
                <label className="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">
                  {t('files.path')}
                </label>
                <input
                  className="input w-full font-mono"
                  value={chmodDialog.path}
                  onChange={(e) =>
                    setChmodDialog((prev) => (prev ? { ...prev, path: e.target.value } : prev))
                  }
                  placeholder="public_html/index.php"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">
                  {t('files.mode')}
                </label>
                <div className="flex flex-wrap gap-2">
                  <button
                    type="button"
                    className="btn-secondary py-1 text-xs"
                    onClick={() => setChmodDialog((p) => (p ? { ...p, mode: '644' } : p))}
                  >
                    644
                  </button>
                  <button
                    type="button"
                    className="btn-secondary py-1 text-xs"
                    onClick={() => setChmodDialog((p) => (p ? { ...p, mode: '755' } : p))}
                  >
                    755
                  </button>
                  <input
                    className="input w-28 font-mono"
                    value={chmodDialog.mode}
                    onChange={(e) =>
                      setChmodDialog((prev) => (prev ? { ...prev, mode: e.target.value } : prev))
                    }
                    inputMode="numeric"
                    placeholder="644"
                  />
                </div>
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{t('files.chmod_hint')}</p>
              </div>
            </div>
            <div className="flex items-center justify-end gap-2 border-t border-gray-200 px-4 py-3 dark:border-gray-800">
              <button type="button" className="btn-secondary" onClick={() => setChmodDialog(null)}>
                {t('common.cancel')}
              </button>
              <button
                type="button"
                className="btn-primary"
                disabled={chmodM.isPending}
                onClick={() => {
                  if (!chmodDialog) return
                  const p = chmodDialog.path.trim().replace(/^\/+/g, '')
                  const m = chmodDialog.mode.trim()
                  if (!p || !isSafeRelativePath(p)) {
                    toast.error(t('files.invalid_path'))
                    return
                  }
                  if (!/^[0-7]{3,4}$/.test(m)) {
                    toast.error(t('files.invalid_mode'))
                    return
                  }
                  chmodM.mutate({ path: p, mode: m })
                }}
              >
                {t('common.save')}
              </button>
            </div>
          </div>
        </div>
      )}

      {trashOpen && (
        <div className="fixed inset-0 z-[58] flex items-center justify-center bg-black/60 p-2 sm:p-4">
          <div className="mx-auto w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
              <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                {t('files.recycle_bin')}
              </h2>
              <button
                type="button"
                className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                onClick={() => setTrashOpen(false)}
                aria-label="Kapat"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-4">
              <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-gray-500 dark:text-gray-400">{t('files.trash_hint')}</p>
                <button
                  type="button"
                  className="btn-secondary py-1.5 text-xs"
                  disabled={trashEmptyM.isPending}
                  onClick={() => {
                    if (window.confirm(t('files.trash_confirm_empty'))) trashEmptyM.mutate()
                  }}
                >
                  {t('files.trash_empty')}
                </button>
              </div>

              {trashQ.isLoading ? (
                <p className="text-sm text-gray-500">{t('common.loading')}</p>
              ) : trashQ.isError ? (
                <p className="text-sm text-red-600">{t('common.error')}</p>
              ) : (trashQ.data?.length ?? 0) === 0 ? (
                <p className="text-sm text-gray-500">{t('files.trash_empty_state')}</p>
              ) : (
                <div className="max-h-[55vh] overflow-auto rounded-lg border border-gray-200 dark:border-gray-800">
                  <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-gray-700 dark:bg-gray-800/80 dark:text-gray-300">
                      <tr className="border-b border-gray-200 dark:border-gray-700">
                        <th className="px-3 py-2 text-left">{t('files.name')}</th>
                        <th className="px-3 py-2 text-left">{t('files.path')}</th>
                        <th className="w-44 px-3 py-2 text-right">{t('files.col_action')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(trashQ.data ?? []).map((it) => (
                        <tr key={it.id} className="border-b border-gray-100 dark:border-gray-800">
                          <td className="px-3 py-2 font-mono text-xs text-gray-800 dark:text-gray-100">
                            {it.name || it.id}
                          </td>
                          <td className="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-300">
                            {it.original_path}
                          </td>
                          <td className="px-3 py-2 text-right">
                            <button
                              type="button"
                              className="mr-2 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                              disabled={trashRestoreM.isPending}
                              onClick={() => trashRestoreM.mutate(it.id)}
                            >
                              {t('files.trash_restore')}
                            </button>
                            <button
                              type="button"
                              className="text-xs font-medium text-red-600 hover:underline dark:text-red-400"
                              disabled={trashDeleteM.isPending}
                              onClick={() => {
                                if (window.confirm(t('files.trash_confirm_delete'))) {
                                  trashDeleteM.mutate(it.id)
                                }
                              }}
                            >
                              {t('files.trash_delete')}
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {contextMenu && (
        <div
          className="fixed z-[70] w-56 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
          style={{ left: contextMenuPos?.left ?? contextMenu.x, top: contextMenuPos?.top ?? contextMenu.y }}
          role="menu"
        >
          <button
            type="button"
            className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
            onClick={() => {
              const name = window.prompt(t('files.op_new_folder'), '')
              if (name?.trim()) {
                const rel = joinRel(contextBaseDir, name.trim())
                api
                  .post(`/domains/${domainId}/files/mkdir`, { path: rel })
                  .then(() => {
                    toast.success(t('files.folder_created'))
                    qc.invalidateQueries({ queryKey: ['files', domainId, path] })
                  })
                  .catch((err: unknown) => {
                    const ax = err as { response?: { data?: { message?: string } } }
                    toast.error(ax.response?.data?.message ?? t('files.mkdir_err'))
                  })
              }
              setContextMenu(null)
            }}
          >
            {t('files.ctx_new_folder')}
          </button>
          <button
            type="button"
            className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
            onClick={() => {
              const name = window.prompt(t('files.op_new_file'), '')
              if (!name?.trim()) return
              const base = name.trim()
              if (!isSafeNewFileName(base)) {
                toast.error(t('files.invalid_filename'))
                return
              }
              const rel = joinRel(contextBaseDir, base)
              api
                .post(`/domains/${domainId}/files/create`, { path: rel, content: '' })
                .then(() => {
                  toast.success(t('files.file_created'))
                  qc.invalidateQueries({ queryKey: ['files', domainId, path] })
                })
                .catch((err: unknown) => {
                  const ax = err as { response?: { data?: { message?: string } } }
                  toast.error(ax.response?.data?.message ?? t('files.create_file_err'))
                })
              setContextMenu(null)
            }}
          >
            {t('files.ctx_new_file')}
          </button>
          {!contextMenu.isDir && (
            <button
              type="button"
              className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
              onClick={() => {
                void openFileWrapped(contextMenu.rel)
                setContextMenu(null)
              }}
            >
              {t('files.ctx_edit')}
            </button>
          )}
          {!contextMenu.isDir && (
            <button
              type="button"
              className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
              onClick={() => {
                void downloadAsFile(contextMenu.rel)
                setContextMenu(null)
              }}
            >
              {t('files.ctx_download')}
            </button>
          )}
          <button
            type="button"
            className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
            onClick={() => {
              const base = contextMenu.rel.split('/').pop() || ''
              setRenameDialog({ from: contextMenu.rel, newName: base })
              setContextMenu(null)
            }}
          >
            {t('files.ctx_rename')}
          </button>
          <button
            type="button"
            className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
            onClick={() => {
              setClipboardPath(contextMenu.rel)
              setClipboardMode('copy')
              toast.success(t('files.ctx_copy_ok'))
              setContextMenu(null)
            }}
          >
            {t('files.ctx_copy')}
          </button>
          <button
            type="button"
            className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
            onClick={() => {
              setClipboardPath(contextMenu.rel)
              setClipboardMode('cut')
              toast.success(t('files.ctx_cut_ok'))
              setContextMenu(null)
            }}
          >
            {t('files.ctx_cut')}
          </button>
          <button
            type="button"
            className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 disabled:opacity-40 dark:hover:bg-gray-800"
            disabled={!clipboardPath || !contextMenu.isDir}
            onClick={async () => {
              try {
                await runClipboardPaste(contextMenu.rel)
              } catch (err: unknown) {
                const ax = err as { response?: { data?: { message?: string } } }
                toast.error(ax.response?.data?.message ?? String(err))
              } finally {
                setContextMenu(null)
              }
            }}
          >
            {t('files.ctx_paste')}
          </button>
          <button
            type="button"
            className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
            onClick={() => {
              setChmodDialog({ path: contextMenu.rel, mode: contextMenu.isDir ? '755' : '644' })
              setContextMenu(null)
            }}
          >
            {t('files.ctx_chmod')}
          </button>
          {!contextMenu.isDir && isZipArchiveFileName(contextMenu.rel.split('/').pop() || '') && (
            <button
              type="button"
              className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
              onClick={() => {
                void runUnzipArchive({ archive: contextMenu.rel, target_dir: path })
                setContextMenu(null)
              }}
            >
              {t('files.ctx_extract_here')}
            </button>
          )}
          <button
            type="button"
            className="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
            onClick={() => {
              const target = suggestedZipTargetPath(contextMenu.rel, contextMenu.isDir)
              void runZipArchive({ source: contextMenu.rel, target })
              setContextMenu(null)
            }}
          >
            {t('files.ctx_compress_zip')}
          </button>
          <button
            type="button"
            className="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
            onClick={() => {
              if (window.confirm(t('common.confirm_delete'))) {
                trashMoveM.mutate(contextMenu.rel)
              }
              setContextMenu(null)
            }}
          >
            {t('files.ctx_delete_to_trash')}
          </button>
        </div>
      )}

      {uploadConflictDialog?.open && (
        <div className="fixed inset-0 z-[76] flex items-center justify-center bg-black/60 p-2 sm:p-4">
          <div className="mx-auto flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900">
            <div className="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
              <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                {t('files.upload_conflict_title')}
              </h2>
              <button
                type="button"
                className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                onClick={() => setUploadConflictDialog(null)}
                aria-label="Kapat"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-4 text-sm">
              <p className="text-gray-700 dark:text-gray-300">{t('files.upload_conflict_desc')}</p>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                {t('files.upload_conflict_summary', {
                  n: uploadConflictDialog.conflicts.length,
                  m: uploadConflictDialog.noConflict.length,
                })}
              </p>
              <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table className="w-full min-w-[520px] text-left text-xs">
                  <thead className="bg-gray-50 dark:bg-gray-800/80">
                    <tr>
                      <th className="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">
                        {t('files.upload_col_rel_path')}
                      </th>
                      <th className="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">
                        {t('files.upload_col_existing')}
                      </th>
                      <th className="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">
                        {t('files.upload_col_new')}
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                    {uploadConflictDialog.conflicts.map((row) => {
                      const relDisp = uploadConflictDialog.basePath
                        ? joinRel(uploadConflictDialog.basePath, row.relFromBase)
                        : row.relFromBase
                      const ex = row.existing
                      const exTxt = ex.is_dir
                        ? `${t('files.upload_type_folder')}${
                            ex.size && ex.size > 0 ? ` · ${formatSize(ex.size)}` : ''
                          }`
                        : `${t('files.upload_type_file')} · ${formatSize(ex.size)}`
                      const newTxt = `${t('files.upload_type_file')} · ${formatSize(row.file.size)}`
                      return (
                        <tr key={`${row.parentRel}/${row.baseName}`} className="text-gray-800 dark:text-gray-200">
                          <td className="max-w-[200px] px-3 py-2 font-mono text-[11px] break-all">{relDisp}</td>
                          <td className="px-3 py-2">{exTxt}</td>
                          <td className="px-3 py-2">{newTxt}</td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            </div>
            <div className="flex shrink-0 flex-wrap justify-end gap-2 border-t border-gray-200 px-4 py-3 dark:border-gray-800">
              <button
                type="button"
                className="btn-secondary"
                disabled={uploadBusy}
                onClick={() => setUploadConflictDialog(null)}
              >
                {t('files.upload_cancel_batch')}
              </button>
              <button
                type="button"
                className="btn-secondary"
                disabled={uploadBusy}
                onClick={async () => {
                  const dlg = uploadConflictDialog
                  if (!dlg?.open) return
                  setUploadConflictDialog(null)
                  setUploadBusy(true)
                  try {
                    await runUploadItems(dlg.noConflict)
                  } finally {
                    setUploadBusy(false)
                  }
                }}
              >
                {t('files.upload_skip_conflicts')}
              </button>
              <button
                type="button"
                className="btn-primary"
                disabled={uploadBusy}
                onClick={async () => {
                  const dlg = uploadConflictDialog
                  if (!dlg?.open) return
                  setUploadConflictDialog(null)
                  setUploadBusy(true)
                  try {
                    for (const c of dlg.conflicts) {
                      const targetRel = c.parentRel ? joinRel(c.parentRel, c.baseName) : c.baseName
                      await deleteRemotePath(targetRel)
                    }
                    await runUploadItems([...dlg.noConflict, ...dlg.conflicts])
                  } catch (err: unknown) {
                    const ax = err as { response?: { data?: { message?: string } } }
                    toast.error(ax.response?.data?.message ?? String(err))
                  } finally {
                    setUploadBusy(false)
                  }
                }}
              >
                {t('files.upload_overwrite_conflicts')}
              </button>
            </div>
          </div>
        </div>
      )}

      {pasteConflictDialog?.open && (
        <div className="fixed inset-0 z-[75] flex items-center justify-center bg-black/60 p-2 sm:p-4">
          <div className="mx-auto w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
              <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                {t('files.paste_conflict_title')}
              </h2>
              <button
                type="button"
                className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                onClick={() => setPasteConflictDialog(null)}
                aria-label="Kapat"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="space-y-3 p-4 text-sm">
              <p className="text-gray-700 dark:text-gray-300">{t('files.paste_conflict_desc')}</p>
              <div className="rounded-lg bg-gray-50 p-3 font-mono text-xs text-gray-700 dark:bg-gray-800/70 dark:text-gray-200">
                {pasteConflictDialog.baseTarget}
              </div>
            </div>
            <div className="flex flex-wrap justify-end gap-2 border-t border-gray-200 px-4 py-3 dark:border-gray-800">
              <button
                type="button"
                className="btn-secondary"
                onClick={async () => {
                  if (!pasteConflictDialog) return
                  setPasteConflictDialog(null)
                  await executePasteWithStrategy(
                    pasteConflictDialog.sourcePath,
                    pasteConflictDialog.destDir,
                    'skip',
                  )
                }}
              >
                {t('files.paste_action_skip')}
              </button>
              <button
                type="button"
                className="btn-secondary"
                onClick={async () => {
                  if (!pasteConflictDialog) return
                  const next = pasteConflictDialog
                  setPasteConflictDialog(null)
                  try {
                    await executePasteWithStrategy(next.sourcePath, next.destDir, 'rename')
                    if (clipboardMode === 'cut') setClipboardPath(null)
                    await qc.invalidateQueries({ queryKey: ['files', domainId, path] })
                    toast.success(t('files.paste_done'))
                  } catch (err: unknown) {
                    const ax = err as { response?: { data?: { message?: string } } }
                    toast.error(ax.response?.data?.message ?? String(err))
                  }
                }}
              >
                {t('files.paste_action_rename')}
              </button>
              <button
                type="button"
                className="btn-primary"
                onClick={async () => {
                  if (!pasteConflictDialog) return
                  const next = pasteConflictDialog
                  setPasteConflictDialog(null)
                  try {
                    await executePasteWithStrategy(next.sourcePath, next.destDir, 'overwrite')
                    if (clipboardMode === 'cut') setClipboardPath(null)
                    await qc.invalidateQueries({ queryKey: ['files', domainId, path] })
                    toast.success(t('files.paste_done'))
                  } catch (err: unknown) {
                    const ax = err as { response?: { data?: { message?: string } } }
                    toast.error(ax.response?.data?.message ?? String(err))
                  }
                }}
              >
                {t('files.paste_action_overwrite')}
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
                        void import('monaco-editor/esm/vs/basic-languages/html/html.contribution')
                        void import('monaco-editor/esm/vs/basic-languages/css/css.contribution')
                        void import('monaco-editor/esm/vs/basic-languages/javascript/javascript.contribution')
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

      <FileUploadProgressOverlay open={uploadProgressView !== null} state={uploadProgressView} />
      <FileArchiveProgressOverlay
        open={archiveUi !== null}
        kind={archiveUi?.kind ?? null}
        complete={archiveUi?.complete ?? false}
      />
    </div>
  )
}
