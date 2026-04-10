import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../services/api'
import {
  AlertTriangle,
  Binary,
  CheckCircle2,
  Clock,
  Cpu,
  Flame,
  Globe,
  Loader2,
  Lock,
  RefreshCw,
  Shield,
  ShieldAlert,
  ShieldCheck,
  ShieldX,
  Terminal,
  Trash2,
  Zap,
} from 'lucide-react'
import toast from 'react-hot-toast'
import { useAuthStore } from '../store/authStore'
import clsx from 'clsx'
import { useDomainsList } from '../hooks/useDomains'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
} from 'recharts'

type SecurityTabId = 'firewall' | 'ssh' | 'server' | 'website' | 'brute' | 'compiler' | 'attack'

function ProPill() {
  return (
    <span className="ml-2 inline-flex items-center rounded-md bg-violet-600/15 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-700 dark:text-violet-300">
      Pro
    </span>
  )
}

function PlanPill({ plan }: { plan: 'free' | 'pro' }) {
  if (plan === 'free') {
    return (
      <span className="inline-flex items-center rounded-md border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-900/20 dark:text-emerald-300">
        FREE
      </span>
    )
  }
  return (
    <span className="inline-flex items-center rounded-md border border-violet-300 bg-violet-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-700 dark:border-violet-700/60 dark:bg-violet-900/20 dark:text-violet-300">
      PRO
    </span>
  )
}

function PlaceholderCard({
  title,
  description,
  pro,
}: {
  title: string
  description: string
  pro?: boolean
}) {
  return (
    <div className="flex gap-3 rounded-xl border border-dashed border-gray-300 bg-gray-50/80 p-4 dark:border-gray-600 dark:bg-gray-800/30">
      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-200/80 dark:bg-gray-700">
        <Lock className="h-5 w-5 text-gray-500 dark:text-gray-400" />
      </div>
      <div>
        <p className="text-sm font-semibold text-gray-900 dark:text-white">
          {title}
          {pro ? <ProPill /> : null}
        </p>
        <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">{description}</p>
      </div>
    </div>
  )
}

export default function SecurityPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const isAdmin = useAuthStore((s) => s.user?.roles?.some((r) => r.name === 'admin'))
  const [tab, setTab] = useState<SecurityTabId>('firewall')
  const [scanTarget, setScanTarget] = useState('/var/www')
  const [scanClamDomain, setScanClamDomain] = useState('')
  const [scanOutput, setScanOutput] = useState('')
  const [scanResolvedPath, setScanResolvedPath] = useState<string | null>(null)
  const [maldetOutput, setMaldetOutput] = useState('')
  const [scanInfectedCount, setScanInfectedCount] = useState<number | null>(null)
  const [scanInfectedFiles, setScanInfectedFiles] = useState<string[]>([])
  const [scanInfectedTruncated, setScanInfectedTruncated] = useState(false)
  const [quarantineSelected, setQuarantineSelected] = useState<string[]>([])
  const [mailConfirm, setMailConfirm] = useState('')
  const [mailReport, setMailReport] = useState<{
    dry_run?: boolean
    active_domains?: number
    scanned?: number
    orphans?: string[]
    removed?: string[]
  } | null>(null)
  const [intelCountryDeny, setIntelCountryDeny] = useState('')
  const [intelAsnDeny, setIntelAsnDeny] = useState('')
  const [intelMinRisk, setIntelMinRisk] = useState(70)
  const [jailBantime, setJailBantime] = useState(600)
  const [jailFindtime, setJailFindtime] = useState(600)
  const [jailMaxretry, setJailMaxretry] = useState(5)
  const [fimDiffs, setFimDiffs] = useState<Array<{ path?: string; type?: string; severity?: string }>>([])
  const [installModal, setInstallModal] = useState<{
    open: boolean
    key: 'fail2ban' | 'modsecurity' | null
    status: 'idle' | 'running' | 'success' | 'error'
    logs: string[]
    startedAt: number | null
    finishedAt: number | null
  }>({
    open: false,
    key: null,
    status: 'idle',
    logs: [],
    startedAt: null,
    finishedAt: null,
  })
  const [toggleProgress, setToggleProgress] = useState<{
    key: 'fail2ban' | 'modsecurity' | null
    running: boolean
    pct: number
    text: string
    status: 'idle' | 'running' | 'success' | 'error'
  }>({
    key: null,
    running: false,
    pct: 0,
    text: '',
    status: 'idle',
  })
  const [rateLimitProfile, setRateLimitProfile] = useState<'wordpress' | 'laravel' | 'api'>('wordpress')
  const [rateLimitProgress, setRateLimitProgress] = useState<{
    running: boolean
    pct: number
    status: 'idle' | 'running' | 'success' | 'error'
    text: string
  }>({
    running: false,
    pct: 0,
    status: 'idle',
    text: '',
  })
  const [siteRuleDomain, setSiteRuleDomain] = useState('')
  const [siteRuleMode, setSiteRuleMode] = useState<'allow' | 'deny' | 'exception'>('deny')
  const [siteRuleTarget, setSiteRuleTarget] = useState('/wp-login\\.php$')
  const domainsQ = useDomainsList()

  const tabs = useMemo(
    () =>
      [
        { id: 'firewall' as const, icon: Flame, label: t('security.tabs.firewall') },
        { id: 'ssh' as const, icon: Terminal, label: t('security.tabs.ssh') },
        { id: 'server' as const, icon: Cpu, label: t('security.tabs.server') },
        { id: 'website' as const, icon: Globe, label: t('security.tabs.website') },
        { id: 'brute' as const, icon: ShieldAlert, label: t('security.tabs.brute') },
        { id: 'compiler' as const, icon: Binary, label: t('security.tabs.compiler') },
        { id: 'attack' as const, icon: Zap, label: t('security.tabs.attack') },
      ] as const,
    [t],
  )

  const q = useQuery({
    queryKey: ['security-overview'],
    queryFn: async () => (await api.get('/security/overview')).data,
    refetchInterval: 45_000,
  })
  const intelPolicyQ = useQuery({
    queryKey: ['security-intel-policy'],
    queryFn: async () => (await api.get('/security/intel/policy')).data,
    refetchInterval: 60_000,
  })
  const intelStatusQ = useQuery({
    queryKey: ['security-intel-status'],
    queryFn: async () => (await api.get('/security/intel/status')).data,
    refetchInterval: 60_000,
  })
  const rateLimitQ = useQuery({
    queryKey: ['security-rate-limit-profile'],
    queryFn: async () => (await api.get('/security/rate-limit/profile')).data,
    refetchInterval: 45_000,
  })
  const siteRulesQ = useQuery({
    queryKey: ['security-modsecurity-site-rules'],
    queryFn: async () => (await api.get('/security/modsecurity/site-rules')).data,
    refetchInterval: 45_000,
  })

  const fwM = useMutation({
    mutationFn: async (payload: {
      action: string
      protocol: string
      port?: string
      source?: string
    }) => api.post('/security/firewall', payload),
    onSuccess: () => {
      toast.success(t('security.toast.rule_sent'))
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string }; status?: number } }
      if (ax.response?.status === 403) toast.error(t('security.toast.admin_only'))
      else toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const toggleM = useMutation({
    mutationFn: async (payload: { key: 'fail2ban' | 'modsecurity' | 'clamav'; enabled: boolean }) => {
      if (payload.key === 'fail2ban') return api.post('/security/fail2ban/toggle', { enabled: payload.enabled })
      if (payload.key === 'modsecurity') return api.post('/security/modsecurity/toggle', { enabled: payload.enabled })
      return api.post('/security/clamav/toggle', { enabled: payload.enabled })
    },
    onMutate: (payload) => {
      if (payload.key === 'clamav') return
      setToggleProgress({
        key: payload.key,
        running: true,
        pct: 12,
        text: 'Islem baslatiliyor...',
        status: 'running',
      })
    },
    onSuccess: (_, vars) => {
      if (vars.key !== 'clamav') {
        setToggleProgress({
          key: vars.key,
          running: false,
          pct: 100,
          text: 'Tamamlandi',
          status: 'success',
        })
        setTimeout(() => {
          setToggleProgress((s) => ({ ...s, key: null, pct: 0, text: '', status: 'idle' }))
        }, 1200)
      }
      toast.success(t('security.toast.setting_updated'))
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown, vars) => {
      if (vars.key !== 'clamav') {
        setToggleProgress({
          key: vars.key,
          running: false,
          pct: 100,
          text: 'Basarisiz',
          status: 'error',
        })
        setTimeout(() => {
          setToggleProgress((s) => ({ ...s, key: null, pct: 0, text: '', status: 'idle' }))
        }, 1800)
      }
      const ax = err as { response?: { data?: { message?: string; hint?: string }; status?: number } }
      if (ax.response?.status === 403) toast.error(t('security.toast.admin_only'))
      else toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const installM = useMutation({
    mutationFn: async (key: 'fail2ban' | 'modsecurity') => {
      if (key === 'fail2ban') return api.post('/security/fail2ban/install')
      return api.post('/security/modsecurity/install')
    },
    onSuccess: () => {
      setInstallModal((s) => ({
        ...s,
        status: 'success',
        finishedAt: Date.now(),
        logs: [...s.logs, t('security.install.done_log')],
      }))
      toast.success(t('security.toast.install_done'))
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string }; status?: number } }
      setInstallModal((s) => ({
        ...s,
        status: 'error',
        finishedAt: Date.now(),
        logs: [
          ...s.logs,
          [ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err),
        ],
      }))
      if (ax.response?.status === 403) toast.error(t('security.toast.admin_only'))
      else toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const runInstall = (key: 'fail2ban' | 'modsecurity') => {
    const title = key === 'fail2ban' ? 'Fail2ban' : 'ModSecurity'
    setInstallModal({
      open: true,
      key,
      status: 'running',
      startedAt: Date.now(),
      finishedAt: null,
      logs: [
        t('security.install.started', { name: title }),
        t('security.install.step_apt'),
        t('security.install.step_service'),
      ],
    })
    installM.mutate(key)
  }

  useEffect(() => {
    if (!toggleProgress.running) return
    const id = window.setInterval(() => {
      setToggleProgress((s) => {
        if (!s.running) return s
        return {
          ...s,
          pct: Math.min(88, s.pct + 6),
          text: 'Uygulaniyor...',
        }
      })
    }, 450)
    return () => window.clearInterval(id)
  }, [toggleProgress.running])

  const applyClamavResult = (payload: {
    output?: unknown
    scan_path?: unknown
    infected_count?: unknown
    infected_files?: unknown
    infected_truncated?: unknown
    scan?: { infected_count?: unknown; infected_files?: unknown; infected_truncated?: unknown }
  }) => {
    const out = String(payload?.output ?? '')
    setScanOutput(out)
    const sp = payload?.scan_path
    setScanResolvedPath(typeof sp === 'string' && sp.trim() !== '' ? sp : null)
    const fromScan = payload?.scan
    const n = Number(payload?.infected_count ?? fromScan?.infected_count ?? NaN)
    setScanInfectedCount(Number.isFinite(n) ? n : null)
    const files = (payload?.infected_files ?? fromScan?.infected_files) as unknown
    setScanInfectedFiles(Array.isArray(files) ? (files as string[]) : [])
    setScanInfectedTruncated(
      Boolean(payload?.infected_truncated ?? fromScan?.infected_truncated),
    )
  }

  const clamavScanPayload = () => {
    const d = scanClamDomain.trim()
    if (d) return { domain: d }
    const t = scanTarget.trim()
    return { target: t !== '' ? t : '/var/www' }
  }

  const clamavScanM = useMutation({
    mutationFn: async () => (await api.post('/security/clamav/scan', clamavScanPayload())).data,
    onSuccess: (res) => {
      const r = res?.result as Record<string, unknown> | undefined
      if (r) applyClamavResult(r)
      setQuarantineSelected([])
      const cnt = Number(r?.infected_count ?? NaN)
      if (Number.isFinite(cnt) && cnt > 0) {
        toast.success(t('security.clamav.toast_found', { count: cnt }))
      } else {
        toast.success(t('security.toast.scan_done'))
      }
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as {
        response?: {
          data?: {
            message?: string
            hint?: string
            result?: Record<string, unknown>
          }
          status?: number
        }
      }
      const r = ax.response?.data?.result
      if (r) applyClamavResult(r)
      else setScanOutput('')
      if (ax.response?.status === 403) toast.error(t('security.toast.admin_only'))
      else toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const clamavQuarantineM = useMutation({
    mutationFn: async (paths: string[]) => (await api.post('/security/clamav/quarantine', { paths })).data,
    onSuccess: (res) => {
      const r = res?.result as { moved?: Array<{ source?: string }> } | undefined
      const moved = (r?.moved ?? []) as Array<{ source?: string }>
      const gone = new Set(moved.map((m) => m.source).filter(Boolean) as string[])
      setScanInfectedFiles((prev) => prev.filter((p) => !gone.has(p)))
      setQuarantineSelected([])
      setScanInfectedCount((c) => {
        if (c === null) return c
        const next = Math.max(0, c - gone.size)
        return next
      })
      toast.success(t('security.clamav.toast_quarantine_done', { count: moved.length }))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string } } }
      toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const maldetScanM = useMutation({
    mutationFn: async () => (await api.post('/security/clamav/maldet-scan', clamavScanPayload())).data,
    onSuccess: (res) => {
      const r = res?.result as Record<string, unknown> | undefined
      const out = String(r?.output ?? '')
      setMaldetOutput(out)
      const sp = r?.scan_path
      setScanResolvedPath(typeof sp === 'string' && sp.trim() !== '' ? sp : null)
      toast.success(t('security.toast.scan_done'))
    },
    onError: (err: unknown) => {
      const ax = err as {
        response?: { data?: { message?: string; hint?: string; result?: Record<string, unknown> } }
      }
      const r = ax.response?.data?.result
      if (r && typeof r.output === 'string') setMaldetOutput(r.output)
      toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const jailM = useMutation({
    mutationFn: async () =>
      api.post('/security/fail2ban/jail', {
        bantime: jailBantime,
        findtime: jailFindtime,
        maxretry: jailMaxretry,
      }),
    onSuccess: () => {
      toast.success(t('security.toast.jail_updated'))
      qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string } } }
      toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  const mailReconcileM = useMutation({
    mutationFn: async (payload: { dry_run: boolean; confirm?: string }) =>
      (await api.post('/security/mail/reconcile', payload)).data,
    onSuccess: (res) => {
      setMailReport(res?.result?.report ?? null)
      if (res?.result?.report?.dry_run) toast.success(t('security.toast.mail_dry_done'))
      else toast.success(t('security.toast.mail_clean_done'))
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; result?: { error?: string } } } }
      toast.error(ax.response?.data?.result?.error ?? ax.response?.data?.message ?? String(err))
    },
  })
  const fimStatusQ = useQuery({
    queryKey: ['security-fim-status'],
    queryFn: async () => (await api.get('/security/fim/status')).data,
    refetchInterval: 45_000,
  })
  const fimBaselineM = useMutation({
    mutationFn: async () => (await api.post('/security/fim/baseline')).data,
    onSuccess: () => {
      toast.success(t('security.toast.fim_baseline_done'))
      void fimStatusQ.refetch()
      void qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string } } }
      toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })
  const fimScanM = useMutation({
    mutationFn: async () => (await api.post('/security/fim/scan')).data,
    onSuccess: (res) => {
      setFimDiffs(Array.isArray(res?.result?.diffs) ? res.result.diffs : [])
      toast.success(t('security.toast.fim_scan_done'))
      void fimStatusQ.refetch()
      void qc.invalidateQueries({ queryKey: ['security-overview'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string } } }
      toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })
  const intelPolicyM = useMutation({
    mutationFn: async (payload: {
      mode: 'observe' | 'enforce'
      countries_deny: string[]
      asn_deny: number[]
      min_risk_score: number
    }) => (await api.post('/security/intel/policy', payload)).data,
    onSuccess: () => {
      toast.success(t('security.toast.setting_updated'))
      void intelPolicyQ.refetch()
      void intelStatusQ.refetch()
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string } } }
      toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })
  const rateLimitM = useMutation({
    mutationFn: async (profile: 'wordpress' | 'laravel' | 'api') => (await api.post('/security/rate-limit/profile', { profile })).data,
    onMutate: () => {
      setRateLimitProgress({ running: true, pct: 14, status: 'running', text: t('security.pro_live.rate.applying') })
    },
    onSuccess: () => {
      setRateLimitProgress({ running: false, pct: 100, status: 'success', text: t('security.pro_live.rate.updated') })
      toast.success(t('security.pro_live.rate.toast_updated'))
      void qc.invalidateQueries({ queryKey: ['security-rate-limit-profile'] })
      setTimeout(() => setRateLimitProgress({ running: false, pct: 0, status: 'idle', text: '' }), 1200)
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string } } }
      setRateLimitProgress({ running: false, pct: 100, status: 'error', text: t('security.pro_live.rate.update_failed') })
      toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
      setTimeout(() => setRateLimitProgress({ running: false, pct: 0, status: 'idle', text: '' }), 1800)
    },
  })
  const addSiteRuleM = useMutation({
    mutationFn: async (payload: { domain: string; mode: 'allow' | 'deny' | 'exception'; target?: string }) =>
      (await api.post('/security/modsecurity/site-rule', payload)).data,
    onSuccess: () => {
      toast.success(t('security.pro_live.waf.toast_added'))
      void qc.invalidateQueries({ queryKey: ['security-modsecurity-site-rules'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string } } }
      toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })
  const removeSiteRuleM = useMutation({
    mutationFn: async (id: string) => (await api.delete('/security/modsecurity/site-rule', { data: { id } })).data,
    onSuccess: () => {
      toast.success(t('security.pro_live.waf.toast_removed'))
      void qc.invalidateQueries({ queryKey: ['security-modsecurity-site-rules'] })
    },
    onError: (err: unknown) => {
      const ax = err as { response?: { data?: { message?: string; hint?: string } } }
      toast.error([ax.response?.data?.message, ax.response?.data?.hint].filter(Boolean).join(' — ') || String(err))
    },
  })

  type Overview = {
    fail2ban?: {
      enabled?: boolean
      installed?: boolean
      jails?: string[]
      error?: string
      settings?: { bantime?: number; findtime?: number; maxretry?: number; error?: string }
    }
    firewall?: {
      backend?: string
      default_policy?: string
      recent_rules?: Array<{
        action?: string
        protocol?: string
        port?: string
        source?: string
        applied_at?: unknown
      }>
    }
    modsecurity?: { enabled?: boolean; installed?: boolean; error?: string }
    clamav?: { enabled?: boolean; installed?: boolean; last_scan?: unknown; error?: string }
  }
  type FimStatus = {
    baseline_exists?: boolean
    last_baseline_at?: string
    last_scan_at?: string
    changed_count?: number
    critical_count?: number
    alerts?: Array<{ id?: string; severity?: string; message?: string; created_at?: string }>
  }

  const overview = q.data?.overview as Overview | undefined
  const fail2banNeedsInstall =
    overview?.fail2ban?.installed === false ||
    (overview?.fail2ban?.installed !== true &&
      String(overview?.fail2ban?.error ?? '').toLowerCase().includes('not installed'))
  const modsecNeedsInstall =
    overview?.modsecurity?.installed === false ||
    (overview?.modsecurity?.installed !== true &&
      String(overview?.modsecurity?.error ?? '').toLowerCase().includes('missing modsecurity'))
  const fwRules = overview?.firewall?.recent_rules ?? []
  const fimStatus = (fimStatusQ.data?.result?.status ?? {}) as FimStatus
  const fimReady = !!fimStatus.baseline_exists
  const productFeatures = useMemo(() => {
    const hasFirewall = typeof overview?.firewall?.default_policy === 'string' && (overview?.firewall?.default_policy ?? '').length > 0
    const hasFail2ban = !!overview?.fail2ban?.enabled
    const hasModsec = !!overview?.modsecurity?.enabled
    const hasClamav = !!overview?.clamav?.enabled

    const intelMode = String((intelPolicyQ.data?.policy?.mode ?? 'observe') || 'observe').toLowerCase()
    const intelLive = String(intelStatusQ.data?.status?.db_version ?? '').trim().length > 0
    return {
      free: [
        { key: 'firewall', active: hasFirewall },
        { key: 'fail2ban', active: hasFail2ban },
        { key: 'modsec', active: hasModsec },
        { key: 'clamav', active: hasClamav },
      ],
      pro: [
        { key: 'rate_limit', active: ['wordpress', 'laravel', 'api'].includes(String(rateLimitQ.data?.result?.profile ?? '')) },
        { key: 'ip_reputation', active: intelLive && (intelMode === 'observe' || intelMode === 'enforce') },
        { key: 'waf_per_site', active: ((siteRulesQ.data?.result?.rules as Array<unknown> | undefined) ?? []).length > 0 },
        { key: 'fim', active: fimReady },
      ],
    }
  }, [overview, intelPolicyQ.data?.policy?.mode, intelStatusQ.data?.status?.db_version, fimReady, rateLimitQ.data?.result?.profile, siteRulesQ.data?.result?.rules])

  const coverage = useMemo(() => {
    const fail2banOk = !!overview?.fail2ban?.enabled
    const firewallOk =
      typeof overview?.firewall?.default_policy === 'string' && (overview?.firewall?.default_policy ?? '') !== ''
    const modsecOk = !!overview?.modsecurity?.enabled
    const clamavOk = !!overview?.clamav?.enabled
    const enabledCount = [fail2banOk, firewallOk, modsecOk, clamavOk].filter(Boolean).length
    const total = 4
    const pct = Math.round((enabledCount / total) * 100)
    return { enabledCount, total, pct, fail2banOk, firewallOk, modsecOk, clamavOk }
  }, [overview])

  const actionDist = useMemo(() => {
    const out: Record<string, number> = {}
    for (const r of fwRules) {
      const a = String(r.action ?? '').toLowerCase().trim()
      if (!a) continue
      out[a] = (out[a] ?? 0) + 1
    }
    const allow = out['allow'] ?? 0
    const deny = out['deny'] ?? 0
    const other = Object.entries(out).reduce((acc, [k, v]) => (k === 'allow' || k === 'deny' ? acc : acc + v), 0)
    return [
      { key: 'allow', label: 'allow', value: allow, color: '#22c55e' },
      { key: 'deny', label: 'deny', value: deny, color: '#ef4444' },
      ...(other > 0 ? [{ key: 'other', label: 'other', value: other, color: '#f59e0b' }] : []),
    ]
  }, [fwRules])

  const protocolDist = useMemo(() => {
    const out: Record<string, number> = {}
    for (const r of fwRules) {
      const p = String(r.protocol ?? '').toLowerCase().trim()
      if (!p) continue
      out[p] = (out[p] ?? 0) + 1
    }
    const items = Object.entries(out)
      .map(([key, value]) => ({ key, label: key, value }))
      .sort((a, b) => b.value - a.value)
      .slice(0, 6)
    return items.length ? items : [{ key: 'unknown', label: 'unknown', value: 0 }]
  }, [fwRules])

  const coveragePieData = useMemo(
    () => [
      { key: 'enabled', name: 'enabled', value: coverage.pct, color: '#22c55e' },
      { key: 'disabled', name: 'disabled', value: Math.max(0, 100 - coverage.pct), color: '#ef4444' },
    ],
    [coverage.pct],
  )

  useEffect(() => {
    const s = overview?.fail2ban?.settings
    if (!s) return
    if (typeof s.bantime === 'number') setJailBantime(s.bantime)
    if (typeof s.findtime === 'number') setJailFindtime(s.findtime)
    if (typeof s.maxretry === 'number') setJailMaxretry(s.maxretry)
  }, [overview?.fail2ban?.settings])
  useEffect(() => {
    const id = window.setInterval(() => {
      setRateLimitProgress((s) => {
        if (!s.running) return s
        return { ...s, pct: Math.min(88, s.pct + 8), text: t('security.pro_live.rate.testing') }
      })
    }, 500)
    return () => window.clearInterval(id)
  }, [rateLimitProgress.running])
  useEffect(() => {
    const p = String(rateLimitQ.data?.result?.profile ?? '').trim()
    if (p === 'wordpress' || p === 'laravel' || p === 'api') setRateLimitProfile(p)
  }, [rateLimitQ.data?.result?.profile])
  useEffect(() => {
    const policy = intelPolicyQ.data?.policy
    if (!policy) return
    const denyCountries = Array.isArray(policy.countries_deny) ? policy.countries_deny : []
    const denyAsn = Array.isArray(policy.asn_deny) ? policy.asn_deny : []
    setIntelCountryDeny(denyCountries.join(','))
    setIntelAsnDeny(denyAsn.map((x: unknown) => String(x)).join(','))
    setIntelMinRisk(typeof policy.min_risk_score === 'number' ? policy.min_risk_score : 70)
  }, [intelPolicyQ.data?.policy])

  const overviewBody =
    q.isLoading ? (
      <p className="text-gray-500">{t('common.loading')}</p>
    ) : q.isError ? (
      <p className="text-sm text-amber-600">{t('security.overview_error')}</p>
    ) : null

  const refreshBtn = (
    <button
      type="button"
      className="btn-secondary inline-flex items-center gap-2 text-sm"
      onClick={() => void q.refetch()}
      disabled={q.isLoading || q.isFetching}
      title={t('security.refresh')}
    >
      <RefreshCw className={q.isFetching ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
      {t('security.refresh')}
    </button>
  )

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-500/10">
            <Shield className="h-7 w-7 text-red-600 dark:text-red-400" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('nav.security')}</h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">{t('security.subtitle')}</p>
          </div>
        </div>
        {refreshBtn}
      </div>

      <div className="rounded-2xl border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-900/60">
        <div className="-mx-1 flex gap-1 overflow-x-auto pb-1">
          {tabs.map(({ id, icon: Icon, label }) => (
            <button
              key={id}
              type="button"
              onClick={() => setTab(id)}
              className={clsx(
                'flex shrink-0 items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
                tab === id
                  ? 'bg-primary-600 text-white shadow-md shadow-primary-600/20'
                  : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
              )}
            >
              <Icon className="h-4 w-4 shrink-0 opacity-90" />
              <span className="whitespace-nowrap">{label}</span>
            </button>
          ))}
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-5 dark:border-emerald-900/50 dark:bg-emerald-950/15">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-sm font-semibold text-gray-900 dark:text-white">{t('security.product.free_title')}</h2>
            <PlanPill plan="free" />
          </div>
          <p className="mb-3 text-xs text-gray-600 dark:text-gray-300">{t('security.product.free_desc')}</p>
          <div className="space-y-2">
            {productFeatures.free.map((f) => (
              <div
                key={f.key}
                className={clsx(
                  'flex items-center justify-between rounded-lg border px-3 py-2 text-xs',
                  f.active
                    ? 'border-emerald-300 bg-emerald-100/70 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-100'
                    : 'border-amber-300 bg-amber-100/70 text-amber-900 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-100',
                )}
              >
                <span>{t(`security.product.features.${f.key}`)}</span>
                <span className="font-semibold">{f.active ? t('security.product.available') : t('security.product.setup_needed')}</span>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-2xl border border-violet-200 bg-violet-50/40 p-5 dark:border-violet-900/50 dark:bg-violet-950/15">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-sm font-semibold text-gray-900 dark:text-white">{t('security.product.pro_title')}</h2>
            <PlanPill plan="pro" />
          </div>
          <p className="mb-3 text-xs text-gray-600 dark:text-gray-300">{t('security.product.pro_desc')}</p>
          <div className="space-y-2">
            {productFeatures.pro.map((f) => (
              <div
                key={f.key}
                className={clsx(
                  'flex items-center justify-between rounded-lg border px-3 py-2 text-xs',
                  f.active
                    ? 'border-emerald-300 bg-emerald-100/70 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-100'
                    : 'border-violet-300 bg-violet-100/70 text-violet-900 dark:border-violet-800 dark:bg-violet-900/30 dark:text-violet-100',
                )}
              >
                <span>{t(`security.product.features.${f.key}`)}</span>
                <span className="font-semibold">{f.active ? t('security.product.available') : t('security.product.coming_soon')}</span>
              </div>
            ))}
          </div>
        </div>
      </div>

      {tab === 'firewall' && (
        <div className="space-y-6">
          {overviewBody}
          {!q.isLoading && !q.isError && (
            <>
              <div className="grid gap-4 lg:grid-cols-2">
                <div className="rounded-xl border border-gray-200 bg-gradient-to-br from-slate-50 to-white p-5 dark:border-gray-700 dark:from-gray-900/80 dark:to-gray-900">
                  <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {t('security.firewall.backend')}
                  </p>
                  <p className="mt-2 font-mono text-lg text-gray-900 dark:text-white">
                    {overview?.firewall?.backend ?? '—'}
                  </p>
                  <p className="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    {t('security.firewall.default_policy')}{' '}
                    <span className="font-mono font-semibold text-gray-900 dark:text-white">
                      {overview?.firewall?.default_policy ?? '—'}
                    </span>
                  </p>
                </div>
                <div className="rounded-xl border border-gray-200 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                  <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('security.firewall.quick_stats')}</p>
                  <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">{t('security.firewall.quick_stats_hint')}</p>
                  <div className="mt-4 grid grid-cols-2 gap-3 text-center">
                    <div className="rounded-lg bg-emerald-50 py-3 dark:bg-emerald-950/30">
                      <p className="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{fwRules.filter((r) => String(r.action).toLowerCase() === 'allow').length}</p>
                      <p className="text-xs text-emerald-800/80 dark:text-emerald-200/80">{t('security.firewall.allow_rules')}</p>
                    </div>
                    <div className="rounded-lg bg-red-50 py-3 dark:bg-red-950/30">
                      <p className="text-2xl font-bold text-red-700 dark:text-red-300">{fwRules.filter((r) => String(r.action).toLowerCase() === 'deny').length}</p>
                      <p className="text-xs text-red-800/80 dark:text-red-200/80">{t('security.firewall.deny_rules')}</p>
                    </div>
                  </div>
                </div>
              </div>

              <div className="grid gap-4 lg:grid-cols-3">
                <div className="rounded-xl border border-gray-100 p-4 dark:border-gray-800 dark:bg-gray-900/30">
                  <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('security.charts.coverage')}</p>
                  <div className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {coverage.enabledCount}/{coverage.total} ({coverage.pct}%)
                  </div>
                  <div className="mt-2" style={{ height: 180 }}>
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Tooltip />
                        <Legend />
                        <Pie data={coveragePieData} dataKey="value" nameKey="name" innerRadius={50} outerRadius={70} stroke="none" isAnimationActive>
                          {coveragePieData.map((p) => (
                            <Cell key={p.key} fill={p.color} />
                          ))}
                        </Pie>
                      </PieChart>
                    </ResponsiveContainer>
                  </div>
                </div>
                <div className="rounded-xl border border-gray-100 p-4 dark:border-gray-800 dark:bg-gray-900/30 lg:col-span-2">
                  <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('security.charts.actions_protocols')}</p>
                  <div className="mt-3 grid gap-4 sm:grid-cols-2">
                    <div style={{ height: 180 }}>
                      <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={actionDist}>
                          <CartesianGrid strokeDasharray="3 3" />
                          <XAxis dataKey="label" />
                          <Tooltip />
                          <Bar dataKey="value" radius={[8, 8, 0, 0]}>
                            {actionDist.map((a) => (
                              <Cell key={a.key} fill={a.color} />
                            ))}
                          </Bar>
                        </BarChart>
                      </ResponsiveContainer>
                    </div>
                    <div style={{ height: 180 }}>
                      <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                          <Tooltip />
                          <Pie data={protocolDist} dataKey="value" nameKey="label" innerRadius={40} outerRadius={65} isAnimationActive>
                            {protocolDist.map((p, i) => {
                              const palette = ['#3b82f6', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444', '#64748b']
                              return <Cell key={p.key} fill={palette[i % palette.length]} />
                            })}
                          </Pie>
                        </PieChart>
                      </ResponsiveContainer>
                    </div>
                  </div>
                </div>
              </div>

              {fwRules.length > 0 && (
                <div>
                  <p className="mb-2 text-sm font-semibold text-gray-900 dark:text-white">{t('security.firewall.recent_rules')}</p>
                  <div className="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                    <table className="w-full text-sm">
                      <thead className="bg-gray-50 dark:bg-gray-800/80">
                        <tr>
                          <th className="px-3 py-2 text-left">{t('security.table.action')}</th>
                          <th className="px-3 py-2 text-left">{t('security.table.protocol')}</th>
                          <th className="px-3 py-2 text-left">{t('security.table.port')}</th>
                          <th className="px-3 py-2 text-left">{t('security.table.source')}</th>
                          <th className="px-3 py-2 text-left">{t('security.table.time')}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {fwRules.map((r, i) => (
                          <tr key={i} className="border-t border-gray-100 dark:border-gray-800">
                            <td className="px-3 py-2 font-mono">
                              {(() => {
                                const a = String(r.action ?? '').toLowerCase()
                                const cls =
                                  a === 'allow'
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-200'
                                    : a === 'deny'
                                      ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-200'
                                      : 'bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200'
                                return (
                                  <span className={`inline-flex rounded-full border px-2 py-0.5 text-xs ${cls}`}>
                                    {String(r.action ?? '—')}
                                  </span>
                                )
                              })()}
                            </td>
                            <td className="px-3 py-2 font-mono">{String(r.protocol ?? '—')}</td>
                            <td className="px-3 py-2 font-mono">{String(r.port ?? '—')}</td>
                            <td className="px-3 py-2 font-mono text-xs">{String(r.source ?? '—')}</td>
                            <td className="px-3 py-2 text-xs text-gray-500">{String(r.applied_at ?? '—')}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {isAdmin && (
                <div className="rounded-xl border border-gray-200 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-white">{t('security.firewall.add_rule')}</h3>
                  <form
                    className="mt-4 grid max-w-xl gap-4 sm:grid-cols-2"
                    onSubmit={(ev) => {
                      ev.preventDefault()
                      const fd = new FormData(ev.currentTarget)
                      fwM.mutate({
                        action: String(fd.get('action')),
                        protocol: String(fd.get('protocol')),
                        port: String(fd.get('port') || '') || undefined,
                        source: String(fd.get('source') || '') || undefined,
                      })
                    }}
                  >
                    <div>
                      <label className="label">{t('security.form.action')}</label>
                      <select name="action" className="input w-full">
                        <option value="allow">allow</option>
                        <option value="deny">deny</option>
                      </select>
                    </div>
                    <div>
                      <label className="label">{t('security.form.protocol')}</label>
                      <select name="protocol" className="input w-full">
                        <option value="tcp">tcp</option>
                        <option value="udp">udp</option>
                        <option value="icmp">icmp</option>
                        <option value="any">any</option>
                      </select>
                    </div>
                    <div>
                      <label className="label">{t('security.form.port')}</label>
                      <input name="port" className="input w-full" placeholder="443" />
                    </div>
                    <div>
                      <label className="label">{t('security.form.source')}</label>
                      <input name="source" className="input w-full" placeholder="0.0.0.0/0" />
                    </div>
                    <div className="sm:col-span-2">
                      <button type="submit" className="btn-primary" disabled={fwM.isPending}>
                        {t('security.form.apply')}
                      </button>
                    </div>
                  </form>
                </div>
              )}
            </>
          )}
        </div>
      )}

      {tab === 'ssh' && (
        <div className="space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-400">{t('security.ssh.intro')}</p>
          <div className="grid gap-3 md:grid-cols-2">
            <PlaceholderCard
              pro
              title={t('security.ssh.port_title')}
              description={t('security.ssh.port_desc')}
            />
            <PlaceholderCard
              pro
              title={t('security.ssh.root_title')}
              description={t('security.ssh.root_desc')}
            />
            <PlaceholderCard
              pro
              title={t('security.ssh.password_title')}
              description={t('security.ssh.password_desc')}
            />
            <PlaceholderCard
              pro
              title={t('security.ssh.keys_title')}
              description={t('security.ssh.keys_desc')}
            />
          </div>
        </div>
      )}

      {tab === 'server' && (
        <div className="space-y-6">
          {overviewBody}
          {!q.isLoading && !q.isError && (
            <>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                  className={clsx(
                    'rounded-xl border p-4',
                    coverage.clamavOk
                      ? 'border-emerald-200 bg-emerald-50/40 dark:border-emerald-900/40 dark:bg-emerald-950/20'
                      : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/15',
                  )}
                >
                  <div className="flex items-center justify-between">
                    <p className="text-xs text-gray-500 dark:text-gray-400">ClamAV</p>
                    <Clock className="h-4 w-4 text-amber-600" />
                  </div>
                  <p className="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                    {coverage.clamavOk ? t('security.status.on') : t('security.status.off')}
                  </p>
                  <p className="mt-1 font-mono text-xs text-gray-600 dark:text-gray-400">
                    {overview?.clamav?.last_scan != null ? String(overview.clamav.last_scan) : t('security.clamav.no_scan')}
                  </p>
                  {isAdmin && (
                    <button
                      type="button"
                      className="mt-3 rounded-lg border border-gray-300 px-2 py-1 text-xs dark:border-gray-600"
                      onClick={() => toggleM.mutate({ key: 'clamav', enabled: !coverage.clamavOk })}
                      disabled={toggleM.isPending}
                    >
                      {coverage.clamavOk ? t('security.action.disable') : t('security.action.enable')}
                    </button>
                  )}
                  {!!overview?.clamav?.error && (
                    <p className="mt-2 text-[11px] text-red-600">{String(overview.clamav.error)}</p>
                  )}
                </div>
                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                  <p className="text-xs text-gray-500">Fail2ban</p>
                  <p className="mt-2 text-sm font-semibold">{coverage.fail2banOk ? t('security.status.on') : t('security.status.off')}</p>
                  <p className="mt-1 text-xs text-gray-500">{t('security.server.brute_hint')}</p>
                </div>
                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                  <p className="text-xs text-gray-500">ModSecurity</p>
                  <p className="mt-2 text-sm font-semibold">{coverage.modsecOk ? t('security.status.on') : t('security.status.off')}</p>
                  <p className="mt-1 text-xs text-gray-500">{t('security.server.waf_hint')}</p>
                </div>
                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                  <p className="text-xs text-gray-500">{t('security.firewall.backend')}</p>
                  <p className="mt-2 font-mono text-sm font-semibold">{overview?.firewall?.backend ?? '—'}</p>
                </div>
              </div>

              {isAdmin && (
                <>
                  <div className="rounded-xl border border-violet-200 bg-violet-50/40 p-5 dark:border-violet-900/40 dark:bg-violet-950/20">
                    <h3 className="text-base font-semibold text-violet-900 dark:text-violet-200">{t('security.fim.title')}</h3>
                    <p className="mt-1 text-xs text-violet-900/90 dark:text-violet-300/90">{t('security.fim.desc')}</p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-4">
                      <div className="rounded-lg border border-gray-200 bg-white/70 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                        <p className="text-xs text-gray-500">{t('security.fim.baseline')}</p>
                        <p className="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{fimReady ? t('security.status.on') : t('security.status.off')}</p>
                      </div>
                      <div className="rounded-lg border border-gray-200 bg-white/70 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                        <p className="text-xs text-gray-500">{t('security.fim.changed')}</p>
                        <p className="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{fimStatus.changed_count ?? 0}</p>
                      </div>
                      <div className="rounded-lg border border-gray-200 bg-white/70 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                        <p className="text-xs text-gray-500">{t('security.fim.critical')}</p>
                        <p className="mt-1 text-sm font-semibold text-red-700 dark:text-red-300">{fimStatus.critical_count ?? 0}</p>
                      </div>
                      <div className="rounded-lg border border-gray-200 bg-white/70 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                        <p className="text-xs text-gray-500">{t('security.fim.last_scan')}</p>
                        <p className="mt-1 text-xs font-mono text-gray-900 dark:text-white">{String(fimStatus.last_scan_at ?? '—')}</p>
                      </div>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                      <button type="button" className="btn-secondary" onClick={() => fimBaselineM.mutate()} disabled={fimBaselineM.isPending}>
                        {fimBaselineM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('security.fim.create_baseline')}
                      </button>
                      <button
                        type="button"
                        className="btn-primary"
                        onClick={() => fimScanM.mutate()}
                        disabled={fimScanM.isPending || !fimReady}
                        title={!fimReady ? t('security.fim.need_baseline') : ''}
                      >
                        {fimScanM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('security.fim.run_scan')}
                      </button>
                    </div>
                    {!fimReady && <p className="mt-2 text-xs text-amber-700 dark:text-amber-300">{t('security.fim.need_baseline')}</p>}
                    <div className="mt-3 grid gap-3 lg:grid-cols-2">
                      <div className="rounded-lg border border-gray-200 bg-white/70 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                        <p className="text-xs font-semibold text-gray-700 dark:text-gray-200">{t('security.fim.last_diff')}</p>
                        <pre className="mt-2 max-h-48 overflow-auto rounded bg-black p-2 text-xs text-green-200">
                          {fimDiffs.length > 0
                            ? fimDiffs
                                .slice(0, 30)
                                .map((d) => `[${String(d.severity ?? 'info')}] ${String(d.type ?? 'changed')} ${String(d.path ?? '')}`)
                                .join('\n')
                            : t('security.fim.no_diff')}
                        </pre>
                      </div>
                      <div className="rounded-lg border border-gray-200 bg-white/70 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                        <p className="text-xs font-semibold text-gray-700 dark:text-gray-200">{t('security.fim.alerts')}</p>
                        <pre className="mt-2 max-h-48 overflow-auto rounded bg-black p-2 text-xs text-green-200">
                          {(fimStatus.alerts ?? []).length > 0
                            ? (fimStatus.alerts ?? [])
                                .slice(0, 30)
                                .map((a) => `[${String(a.severity ?? 'info')}] ${String(a.created_at ?? '')} ${String(a.message ?? '')}`)
                                .join('\n')
                            : t('security.fim.no_alerts')}
                        </pre>
                      </div>
                    </div>
                  </div>

                  <div className="rounded-xl border border-red-200 bg-red-50/40 p-5 dark:border-red-900/40 dark:bg-red-950/20">
                    <h3 className="text-base font-semibold text-red-900 dark:text-red-200">{t('security.mail_cleanup.title')}</h3>
                    <p className="mt-1 text-xs text-red-800/90 dark:text-red-300/90">{t('security.mail_cleanup.warning')}</p>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                      <button
                        type="button"
                        className="btn-secondary"
                        onClick={() => mailReconcileM.mutate({ dry_run: true })}
                        disabled={mailReconcileM.isPending}
                      >
                        {mailReconcileM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('security.mail_cleanup.dry')}
                      </button>
                      <input
                        className="input w-64 font-mono text-sm"
                        value={mailConfirm}
                        onChange={(e) => setMailConfirm(e.target.value)}
                        placeholder="DELETE_ORPHAN_MAIL_STATE"
                      />
                      <button
                        type="button"
                        className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        onClick={() => mailReconcileM.mutate({ dry_run: false, confirm: mailConfirm.trim() || undefined })}
                        disabled={mailReconcileM.isPending}
                      >
                        {t('security.mail_cleanup.confirm')}
                      </button>
                    </div>
                    {mailReport && (
                      <div className="mt-3 rounded-lg border border-gray-200 bg-white/70 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                        <p className="text-xs text-gray-700 dark:text-gray-300">
                          active_domains={mailReport.active_domains ?? 0}, scanned={mailReport.scanned ?? 0}, orphans=
                          {(mailReport.orphans ?? []).length}, removed={(mailReport.removed ?? []).length}
                        </p>
                        {(mailReport.orphans ?? []).length > 0 && (
                          <pre className="mt-2 max-h-40 overflow-auto rounded bg-black p-2 text-xs text-green-200">
                            {(mailReport.orphans ?? []).join('\n')}
                          </pre>
                        )}
                      </div>
                    )}
                  </div>

                  <div className="rounded-xl border border-gray-200 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">{t('security.clamav.scan_title')}</h3>
                    <div className="mt-4 flex flex-wrap items-end gap-3">
                      <div className="min-w-[200px]">
                        <label className="label">{t('security.clamav.domain_optional')}</label>
                        <select
                          className="input w-full"
                          value={scanClamDomain}
                          onChange={(e) => setScanClamDomain(e.target.value)}
                        >
                          <option value="">{t('security.clamav.domain_use_path')}</option>
                          {(domainsQ.data ?? []).map((d) => (
                            <option key={d.id} value={d.name}>
                              {d.name}
                            </option>
                          ))}
                        </select>
                      </div>
                      <div className="min-w-[260px] flex-1">
                        <label className="label">{t('security.clamav.target')}</label>
                        <input
                          className="input w-full font-mono disabled:opacity-50"
                          value={scanTarget}
                          onChange={(e) => setScanTarget(e.target.value)}
                          placeholder="/var/www"
                          disabled={!!scanClamDomain.trim()}
                        />
                      </div>
                      <button
                        type="button"
                        className="btn-primary"
                        onClick={() => {
                          setScanInfectedCount(null)
                          setScanInfectedFiles([])
                          setScanInfectedTruncated(false)
                          setScanResolvedPath(null)
                          setQuarantineSelected([])
                          clamavScanM.mutate()
                        }}
                        disabled={clamavScanM.isPending}
                      >
                        {clamavScanM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('security.clamav.run')}
                      </button>
                      <button
                        type="button"
                        className="rounded-lg border border-violet-300 bg-violet-50 px-4 py-2 text-sm font-medium text-violet-900 hover:bg-violet-100 disabled:opacity-50 dark:border-violet-700 dark:bg-violet-950/40 dark:text-violet-100 dark:hover:bg-violet-950/70"
                        onClick={() => {
                          setMaldetOutput('')
                          setScanResolvedPath(null)
                          maldetScanM.mutate()
                        }}
                        disabled={maldetScanM.isPending || clamavScanM.isPending}
                        title={t('security.clamav.maldet_hint')}
                      >
                        {maldetScanM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('security.clamav.maldet_run')}
                      </button>
                    </div>
                    <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">{t('security.clamav.safe_hint')}</p>
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{t('security.clamav.maldet_hint')}</p>
                    {scanResolvedPath && (
                      <p className="mt-2 font-mono text-xs text-gray-600 dark:text-gray-400">
                        {t('security.clamav.resolved_path')}: {scanResolvedPath}
                      </p>
                    )}
                    {scanInfectedCount !== null && (
                      <div
                        className={`mt-3 rounded-lg border px-3 py-2 text-sm ${
                          scanInfectedCount > 0
                            ? 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/25 dark:text-emerald-200'
                        }`}
                      >
                        <p className="font-semibold">
                          {scanInfectedCount > 0
                            ? t('security.clamav.summary_infected', { count: scanInfectedCount })
                            : t('security.clamav.summary_clean')}
                        </p>
                        {scanInfectedFiles.length > 0 && (
                          <div className="mt-2 space-y-1">
                            <div className="flex flex-wrap items-center gap-2 text-xs">
                              <button
                                type="button"
                                className="rounded border border-amber-400/50 px-2 py-0.5 hover:bg-amber-100/80 dark:hover:bg-amber-900/40"
                                onClick={() => {
                                  if (quarantineSelected.length === scanInfectedFiles.length) {
                                    setQuarantineSelected([])
                                  } else {
                                    setQuarantineSelected([...scanInfectedFiles])
                                  }
                                }}
                              >
                                {quarantineSelected.length === scanInfectedFiles.length
                                  ? t('security.clamav.deselect_all')
                                  : t('security.clamav.select_all')}
                              </button>
                              <button
                                type="button"
                                className="rounded bg-red-600 px-2 py-0.5 font-medium text-white hover:bg-red-700 disabled:opacity-50"
                                disabled={quarantineSelected.length === 0 || clamavQuarantineM.isPending}
                                onClick={() => {
                                  if (
                                    !window.confirm(
                                      t('security.clamav.quarantine_confirm', { count: quarantineSelected.length }),
                                    )
                                  ) {
                                    return
                                  }
                                  clamavQuarantineM.mutate(quarantineSelected)
                                }}
                              >
                                {clamavQuarantineM.isPending
                                  ? t('security.clamav.quarantine_running')
                                  : t('security.clamav.quarantine_selected', { count: quarantineSelected.length })}
                              </button>
                            </div>
                            <ul className="max-h-40 overflow-auto font-mono text-xs">
                              {scanInfectedFiles.map((p) => (
                                <li key={p} className="flex gap-2 break-all py-0.5">
                                  <input
                                    type="checkbox"
                                    className="mt-0.5 shrink-0"
                                    checked={quarantineSelected.includes(p)}
                                    onChange={() => {
                                      setQuarantineSelected((prev) =>
                                        prev.includes(p) ? prev.filter((x) => x !== p) : [...prev, p],
                                      )
                                    }}
                                  />
                                  <span>{p}</span>
                                </li>
                              ))}
                            </ul>
                          </div>
                        )}
                        {scanInfectedTruncated && (
                          <p className="mt-1 text-xs opacity-90">{t('security.clamav.list_truncated')}</p>
                        )}
                      </div>
                    )}
                    <p className="mt-3 text-xs font-medium text-gray-600 dark:text-gray-400">{t('security.clamav.output_clam')}</p>
                    <pre className="mt-1 max-h-64 overflow-auto whitespace-pre-wrap rounded-lg bg-black p-3 text-xs text-green-200">
                      {scanOutput || t('security.clamav.no_output')}
                    </pre>
                    {(maldetOutput || maldetScanM.isPending) && (
                      <>
                        <p className="mt-3 text-xs font-medium text-gray-600 dark:text-gray-400">
                          {t('security.clamav.output_maldet')}
                        </p>
                        <pre className="mt-1 max-h-64 overflow-auto whitespace-pre-wrap rounded-lg bg-black p-3 text-xs text-green-200">
                          {maldetScanM.isPending ? '…' : maldetOutput || t('security.clamav.no_output')}
                        </pre>
                      </>
                    )}
                  </div>
                </>
              )}

              <div className="grid gap-3 md:grid-cols-2">
                <PlaceholderCard
                  pro
                  title={t('security.server.kernel_title')}
                  description={t('security.server.kernel_desc')}
                />
                <PlaceholderCard
                  pro
                  title={t('security.server.audit_title')}
                  description={t('security.server.audit_desc')}
                />
              </div>
            </>
          )}
        </div>
      )}

      {tab === 'website' && (
        <div className="space-y-6">
          {overviewBody}
          {!q.isLoading && !q.isError && (
            <div
              className={clsx(
                'rounded-2xl border p-6',
                coverage.modsecOk
                  ? 'border-emerald-200 bg-emerald-50/30 dark:border-emerald-900/40 dark:bg-emerald-950/15'
                  : 'border-amber-200 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/15',
              )}
            >
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-white">ModSecurity (Apache WAF)</h3>
                  <p className="mt-1 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{t('security.website.modsec_intro')}</p>
                </div>
                {coverage.modsecOk ? (
                  <CheckCircle2 className="h-8 w-8 text-emerald-600" />
                ) : (
                  <AlertTriangle className="h-8 w-8 text-amber-600" />
                )}
              </div>
              <p className="mt-4 text-sm font-medium text-gray-900 dark:text-white">
                {t('security.status.label')}: {coverage.modsecOk ? t('security.status.on') : t('security.status.off')}
              </p>
              {isAdmin && (
                <div className="mt-4 flex flex-wrap gap-2">
                  <button
                    type="button"
                    className="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600"
                    onClick={() => toggleM.mutate({ key: 'modsecurity', enabled: !coverage.modsecOk })}
                    disabled={toggleM.isPending}
                  >
                    {coverage.modsecOk ? t('security.action.disable') : t('security.action.enable')}
                  </button>
                  {modsecNeedsInstall && (
                    <button
                      type="button"
                      className="rounded-lg bg-amber-600 px-3 py-2 text-sm text-white hover:bg-amber-700"
                      onClick={() => runInstall('modsecurity')}
                      disabled={installM.isPending}
                    >
                      {t('security.action.install_modsec')}
                    </button>
                  )}
                </div>
              )}
              {toggleProgress.key === 'modsecurity' && toggleProgress.status !== 'idle' && (
                <div className="mt-3 rounded-lg border border-gray-200 bg-white/70 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                  <div className="mb-1 flex items-center justify-between text-xs">
                    <span className="text-gray-600 dark:text-gray-300">{toggleProgress.text}</span>
                    <span className="font-mono">{toggleProgress.pct}%</span>
                  </div>
                  <div className="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div
                      className={clsx(
                        'h-2 transition-all duration-300',
                        toggleProgress.status === 'error' ? 'bg-red-500' : 'bg-primary-500',
                      )}
                      style={{ width: `${toggleProgress.pct}%` }}
                    />
                  </div>
                </div>
              )}
              {!!overview?.modsecurity?.error && (
                <p className="mt-3 text-sm text-red-600">{String(overview.modsecurity.error)}</p>
              )}
              <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">{t('security.website.toggle_hint')}</p>
            </div>
          )}
          <div className="grid gap-3 md:grid-cols-2">
            <div className="rounded-xl border border-violet-200 bg-violet-50/40 p-4 dark:border-violet-900/40 dark:bg-violet-950/20">
              <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('security.website.per_site_title')}</p>
              <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">{t('security.website.per_site_desc')}</p>
              <p className="mt-2 text-xs text-amber-700 dark:text-amber-300">{t('security.pro_live.waf.rollback_hint')}</p>
              <div className="mt-3 grid gap-2 sm:grid-cols-3">
                <select className="input w-full" value={siteRuleDomain} onChange={(e) => setSiteRuleDomain(e.target.value)}>
                  <option value="">{t('security.pro_live.waf.select_domain')}</option>
                  {domainsQ.data?.map((d) => (
                    <option key={d.id} value={d.name}>
                      {d.name}
                    </option>
                  ))}
                </select>
                <select className="input w-full" value={siteRuleMode} onChange={(e) => setSiteRuleMode(e.target.value as 'allow' | 'deny' | 'exception')}>
                  <option value="allow">allow</option>
                  <option value="deny">deny</option>
                  <option value="exception">exception</option>
                </select>
                <input className="input w-full font-mono text-xs" value={siteRuleTarget} onChange={(e) => setSiteRuleTarget(e.target.value)} placeholder="/wp-login\\.php$" />
              </div>
              <div className="mt-2">
                <button
                  type="button"
                  className="btn-primary"
                  disabled={addSiteRuleM.isPending || !siteRuleDomain.trim() || (siteRuleMode !== 'allow' && !siteRuleTarget.trim())}
                  onClick={() =>
                    addSiteRuleM.mutate({
                      domain: siteRuleDomain.trim(),
                      mode: siteRuleMode,
                      target: siteRuleMode === 'allow' ? '/' : siteRuleTarget.trim(),
                    })
                  }
                >
                  {t('security.pro_live.waf.add_rule')}
                </button>
              </div>
              <div className="mt-3 max-h-36 space-y-2 overflow-auto">
                {((siteRulesQ.data?.result?.rules as Array<{ id?: string; domain?: string; mode?: string; target?: string }> | undefined) ?? []).map((r) => (
                  <div key={String(r.id)} className="flex items-center justify-between rounded-lg border border-gray-200 bg-white/70 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-900/40">
                    <span className="font-mono">{String(r.domain)} • {String(r.mode)} • {String(r.target)}</span>
                    {isAdmin && (
                      <button
                        type="button"
                        className="rounded-md border border-red-300 p-1 text-red-600 dark:border-red-700"
                        disabled={removeSiteRuleM.isPending || !r.id}
                        onClick={() => r.id && removeSiteRuleM.mutate(String(r.id))}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    )}
                  </div>
                ))}
              </div>
            </div>
            <PlaceholderCard pro title={t('security.website.crs_title')} description={t('security.website.crs_desc')} />
          </div>
        </div>
      )}

      {tab === 'brute' && (
        <div className="space-y-6">
          {overviewBody}
          {!q.isLoading && !q.isError && (
            <>
              <div
                className={clsx(
                  'rounded-2xl border p-6',
                  coverage.fail2banOk
                    ? 'border-emerald-200 bg-emerald-50/30 dark:border-emerald-900/40 dark:bg-emerald-950/15'
                    : 'border-amber-200 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/15',
                )}
              >
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Fail2ban</h3>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{t('security.brute.intro')}</p>
                  </div>
                  {coverage.fail2banOk ? (
                    <ShieldCheck className="h-8 w-8 text-emerald-600" />
                  ) : (
                    <ShieldX className="h-8 w-8 text-amber-600" />
                  )}
                </div>
                {overview?.fail2ban?.jails && overview.fail2ban.jails.length > 0 && (
                  <p className="mt-3 font-mono text-xs text-gray-700 dark:text-gray-300">{overview.fail2ban.jails.join(', ')}</p>
                )}
                <div className="mt-4 flex flex-wrap gap-2">
                  {isAdmin && (
                    <>
                      <button
                        type="button"
                        className="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600"
                        onClick={() => toggleM.mutate({ key: 'fail2ban', enabled: !coverage.fail2banOk })}
                        disabled={toggleM.isPending}
                      >
                        {coverage.fail2banOk ? t('security.action.disable') : t('security.action.enable')}
                      </button>
                      {fail2banNeedsInstall && (
                        <button
                          type="button"
                          className="rounded-lg bg-amber-600 px-3 py-2 text-sm text-white hover:bg-amber-700"
                          onClick={() => runInstall('fail2ban')}
                          disabled={installM.isPending}
                        >
                          {t('security.action.install_fail2ban')}
                        </button>
                      )}
                    </>
                  )}
                </div>
                {toggleProgress.key === 'fail2ban' && toggleProgress.status !== 'idle' && (
                  <div className="mt-3 rounded-lg border border-gray-200 bg-white/70 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                    <div className="mb-1 flex items-center justify-between text-xs">
                      <span className="text-gray-600 dark:text-gray-300">{toggleProgress.text}</span>
                      <span className="font-mono">{toggleProgress.pct}%</span>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                      <div
                        className={clsx(
                          'h-2 transition-all duration-300',
                          toggleProgress.status === 'error' ? 'bg-red-500' : 'bg-primary-500',
                        )}
                        style={{ width: `${toggleProgress.pct}%` }}
                      />
                    </div>
                  </div>
                )}
                {!!overview?.fail2ban?.error && (
                  <p className="mt-3 text-sm text-amber-800 dark:text-amber-200">{String(overview.fail2ban.error)}</p>
                )}
                <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">{t('security.brute.toggle_hint')}</p>
              </div>

              {isAdmin && (
                <div className="rounded-xl border border-gray-200 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                  <h3 className="text-lg font-semibold text-gray-900 dark:text-white">{t('security.brute.jail_title')}</h3>
                  <div className="mt-4 grid gap-3 sm:grid-cols-3">
                    <div>
                      <label className="label">{t('security.brute.bantime')}</label>
                      <input
                        type="number"
                        min={60}
                        max={604800}
                        className="input w-full"
                        value={jailBantime}
                        onChange={(e) => setJailBantime(Number(e.target.value || 600))}
                      />
                    </div>
                    <div>
                      <label className="label">{t('security.brute.findtime')}</label>
                      <input
                        type="number"
                        min={60}
                        max={604800}
                        className="input w-full"
                        value={jailFindtime}
                        onChange={(e) => setJailFindtime(Number(e.target.value || 600))}
                      />
                    </div>
                    <div>
                      <label className="label">{t('security.brute.maxretry')}</label>
                      <input
                        type="number"
                        min={1}
                        max={20}
                        className="input w-full"
                        value={jailMaxretry}
                        onChange={(e) => setJailMaxretry(Number(e.target.value || 5))}
                      />
                    </div>
                  </div>
                  <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-xs text-gray-500">{t('security.brute.jail_hint')}</p>
                    <button type="button" className="btn-primary" onClick={() => jailM.mutate()} disabled={jailM.isPending}>
                      {jailM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('security.brute.apply')}
                    </button>
                  </div>
                  {!!overview?.fail2ban?.settings?.error && (
                    <p className="mt-2 text-xs text-red-600">{String(overview.fail2ban.settings.error)}</p>
                  )}
                </div>
              )}

              <PlaceholderCard
                pro
                title={t('security.brute.captcha_title')}
                description={t('security.brute.captcha_desc')}
              />
            </>
          )}
        </div>
      )}

      {tab === 'compiler' && (
        <div className="space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-400">{t('security.compiler.intro')}</p>
          <div className="grid gap-3 md:grid-cols-2">
            <PlaceholderCard pro title={t('security.compiler.gcc_title')} description={t('security.compiler.gcc_desc')} />
            <PlaceholderCard pro title={t('security.compiler.per_user_title')} description={t('security.compiler.per_user_desc')} />
          </div>
        </div>
      )}

      {tab === 'attack' && (
        <div className="space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-400">{t('security.attack.intro')}</p>
          <div className="rounded-xl border border-primary-200 bg-primary-50/40 p-5 dark:border-primary-900/40 dark:bg-primary-950/20">
            <p className="text-sm font-medium text-gray-900 dark:text-white">{t('security.attack.layers_title')}</p>
            <ul className="mt-3 list-inside list-disc space-y-1 text-sm text-gray-700 dark:text-gray-300">
              <li>{t('security.attack.layer_fw')}</li>
              <li>{t('security.attack.layer_f2b')}</li>
              <li>{t('security.attack.layer_modsec')}</li>
            </ul>
          </div>
          <div className="grid gap-3 md:grid-cols-2">
            <div className="rounded-xl border border-violet-200 bg-violet-50/40 p-4 dark:border-violet-900/40 dark:bg-violet-950/20">
              <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('security.attack.rate_title')}</p>
              <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">{t('security.attack.rate_desc')}</p>
              <p className="mt-2 text-xs text-amber-700 dark:text-amber-300">{t('security.pro_live.rate.rollback_hint')}</p>
              <div className="mt-3 flex flex-wrap items-center gap-2">
                <select
                  className="input w-48"
                  value={rateLimitProfile}
                  onChange={(e) => setRateLimitProfile(e.target.value as 'wordpress' | 'laravel' | 'api')}
                  disabled={!isAdmin || rateLimitM.isPending}
                >
                  <option value="wordpress">wordpress</option>
                  <option value="laravel">laravel</option>
                  <option value="api">api</option>
                </select>
                <button type="button" className="btn-primary" disabled={!isAdmin || rateLimitM.isPending} onClick={() => rateLimitM.mutate(rateLimitProfile)}>
                  {t('security.pro_live.rate.apply')}
                </button>
              </div>
              <p className="mt-2 text-xs text-gray-600 dark:text-gray-300">
                {t('security.pro_live.rate.active_profile')}: <span className="font-mono font-semibold">{String(rateLimitQ.data?.result?.profile ?? 'none')}</span>
              </p>
              {!!rateLimitQ.data?.result?.limits && (
                <pre className="mt-2 max-h-28 overflow-auto rounded bg-black p-2 text-[11px] text-green-200">{String(rateLimitQ.data?.result?.limits)}</pre>
              )}
              {rateLimitProgress.status !== 'idle' && (
                <div className="mt-3 rounded-lg border border-gray-200 bg-white/70 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                  <div className="mb-1 flex items-center justify-between text-xs">
                    <span className="text-gray-600 dark:text-gray-300">{rateLimitProgress.text}</span>
                    <span className="font-mono">{rateLimitProgress.pct}%</span>
                  </div>
                  <div className="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div
                      className={clsx('h-2 transition-all duration-300', rateLimitProgress.status === 'error' ? 'bg-red-500' : 'bg-primary-500')}
                      style={{ width: `${rateLimitProgress.pct}%` }}
                    />
                  </div>
                </div>
              )}
            </div>
            <PlaceholderCard pro title={t('security.attack.ddos_title')} description={t('security.attack.ddos_desc')} />
            <PlaceholderCard pro title={t('security.attack.bot_title')} description={t('security.attack.bot_desc')} />
            <div className="rounded-xl border border-violet-200 bg-violet-50/40 p-4 dark:border-violet-800/50 dark:bg-violet-950/20">
              <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('security.attack.geo_title')}</p>
              <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">{t('security.attack.geo_desc')}</p>
              <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                DB: {String(intelStatusQ.data?.status?.db_version ?? 'local-demo-v1')} · last: {String(intelStatusQ.data?.status?.last_update ?? '—')}
              </p>
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                mode: {String(intelPolicyQ.data?.policy?.mode ?? 'observe')} · private IP bypass: {String(intelStatusQ.data?.status?.private_ip_geo_bypass ?? true)}
              </p>
              {isAdmin && (
                <form
                  className="mt-3 space-y-2"
                  onSubmit={(ev) => {
                    ev.preventDefault()
                    const countryList = intelCountryDeny
                      .split(',')
                      .map((x) => x.trim().toUpperCase())
                      .filter(Boolean)
                    const asnList = intelAsnDeny
                      .split(',')
                      .map((x) => Number(x.trim()))
                      .filter((x) => Number.isFinite(x) && x > 0)
                    const mode = String((ev.currentTarget.elements.namedItem('mode') as HTMLSelectElement).value) as 'observe' | 'enforce'
                    intelPolicyM.mutate({
                      mode,
                      countries_deny: countryList,
                      asn_deny: asnList,
                      min_risk_score: Math.max(0, Math.min(100, intelMinRisk)),
                    })
                  }}
                >
                  <div className="grid gap-2 sm:grid-cols-2">
                    <select name="mode" className="input w-full text-xs" defaultValue={String(intelPolicyQ.data?.policy?.mode ?? 'observe')}>
                      <option value="observe">observe</option>
                      <option value="enforce">enforce</option>
                    </select>
                    <input
                      className="input w-full text-xs"
                      value={String(intelMinRisk)}
                      onChange={(e) => setIntelMinRisk(Number(e.target.value || 70))}
                      type="number"
                      min={0}
                      max={100}
                      placeholder="risk score 0-100"
                    />
                  </div>
                  <input
                    className="input w-full text-xs"
                    value={intelCountryDeny}
                    onChange={(e) => setIntelCountryDeny(e.target.value)}
                    placeholder="deny countries: RU,CN,..."
                  />
                  <input
                    className="input w-full text-xs"
                    value={intelAsnDeny}
                    onChange={(e) => setIntelAsnDeny(e.target.value)}
                    placeholder="deny ASN: 12345,13335,..."
                  />
                  <button type="submit" className="btn-primary text-xs" disabled={intelPolicyM.isPending}>
                    {intelPolicyM.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : 'IP Reputation politikasini uygula'}
                  </button>
                </form>
              )}
            </div>
          </div>
        </div>
      )}

      {installModal.open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-2xl space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-900">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-semibold">
                {installModal.key === 'fail2ban' ? t('security.install.modal_fail2ban') : t('security.install.modal_modsec')}
              </h3>
              <button
                type="button"
                className="btn-secondary"
                onClick={() => setInstallModal((s) => ({ ...s, open: false }))}
                disabled={installModal.status === 'running'}
              >
                {t('common.close')}
              </button>
            </div>
            <div className="text-sm">
              {t('security.install.status')}:{' '}
              {installModal.status === 'running'
                ? t('security.install.running')
                : installModal.status === 'success'
                  ? t('security.install.success')
                  : installModal.status === 'error'
                    ? t('security.install.error')
                    : t('security.install.idle')}
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
              <div
                className={clsx(
                  'h-2 transition-all duration-500',
                  installModal.status === 'error' ? 'bg-red-500' : 'bg-primary-500',
                )}
                style={{
                  width:
                    installModal.status === 'running'
                      ? '72%'
                      : installModal.status === 'success' || installModal.status === 'error'
                        ? '100%'
                        : '0%',
                }}
              />
            </div>
            <pre className="max-h-64 overflow-auto whitespace-pre-wrap rounded-lg bg-black p-3 text-xs text-green-200">
              {installModal.logs.join('\n')}
            </pre>
            {installModal.startedAt && (
              <p className="text-xs text-gray-500">
                {new Date(installModal.startedAt).toLocaleString()}{' '}
                {installModal.finishedAt ? `• ${new Date(installModal.finishedAt).toLocaleString()}` : ''}
              </p>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
