import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Navigate } from 'react-router-dom'
import toast from 'react-hot-toast'
import { ShieldCheck } from 'lucide-react'
import api from '../services/api'
import { useAuthStore } from '../store/authStore'

type Row = Record<string, unknown>
type Paginated<T> = { data: T[] }

export default function AdminVendorControlPage() {
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const isAdmin = user?.roles?.some((r) => ['admin', 'vendor_admin', 'vendor_support', 'vendor_finance', 'vendor_devops'].includes(r.name))

  const [tab, setTab] = useState<'overview' | 'billing' | 'support' | 'security'>('overview')
  const [search, setSearch] = useState('')

  const vendorsQ = useQuery({
    queryKey: ['vendor-tenants'],
    enabled: !!isAdmin,
    queryFn: async () => (await api.get('/vendor/tenants')).data as { items: Paginated<Row> },
  })
  const plansQ = useQuery({
    queryKey: ['vendor-plans'],
    enabled: !!isAdmin,
    queryFn: async () => (await api.get('/vendor/plans')).data as { items: Paginated<Row> },
  })
  const featuresQ = useQuery({
    queryKey: ['vendor-features'],
    enabled: !!isAdmin,
    queryFn: async () => (await api.get('/vendor/features')).data as { items: Paginated<Row> },
  })
  const licensesQ = useQuery({
    queryKey: ['vendor-licenses'],
    enabled: !!isAdmin,
    queryFn: async () => (await api.get('/vendor/licenses')).data as { items: Paginated<Row> },
  })
  const nodesQ = useQuery({
    queryKey: ['vendor-nodes'],
    enabled: !!isAdmin,
    queryFn: async () => (await api.get('/vendor/nodes')).data as { items: Paginated<Row> },
  })
  const billingSubsQ = useQuery({
    queryKey: ['vendor-billing-subs'],
    enabled: !!isAdmin && tab === 'billing',
    queryFn: async () => (await api.get('/vendor/billing/subscriptions')).data as { items: Paginated<Row> },
  })
  const invoicesQ = useQuery({
    queryKey: ['vendor-billing-invoices'],
    enabled: !!isAdmin && tab === 'billing',
    queryFn: async () => (await api.get('/vendor/billing/invoices')).data as { items: Paginated<Row> },
  })
  const paymentsQ = useQuery({
    queryKey: ['vendor-billing-payments'],
    enabled: !!isAdmin && tab === 'billing',
    queryFn: async () => (await api.get('/vendor/billing/payments')).data as { items: Paginated<Row> },
  })
  const ticketsQ = useQuery({
    queryKey: ['vendor-support-tickets'],
    enabled: !!isAdmin && tab === 'support',
    queryFn: async () => (await api.get('/vendor/support/tickets')).data as { items: Paginated<Row> },
  })
  const auditQ = useQuery({
    queryKey: ['vendor-audit'],
    enabled: !!isAdmin && tab === 'security',
    queryFn: async () => (await api.get('/vendor/security/audit')).data as { items: Paginated<Row> },
  })
  const siemQ = useQuery({
    queryKey: ['vendor-siem-config'],
    enabled: !!isAdmin && tab === 'security',
    queryFn: async () =>
      (await api.get('/vendor/security/siem')).data as {
        item: { enabled: boolean; endpoint: string; auth_type: 'none' | 'bearer'; has_secret: boolean; timeout_seconds: number }
      },
  })

  const tenants = vendorsQ.data?.items?.data ?? []
  const plans = plansQ.data?.items?.data ?? []
  const features = featuresQ.data?.items?.data ?? []
  const licenses = licensesQ.data?.items?.data ?? []
  const nodes = nodesQ.data?.items?.data ?? []
  const subs = billingSubsQ.data?.items?.data ?? []
  const invoices = invoicesQ.data?.items?.data ?? []
  const payments = paymentsQ.data?.items?.data ?? []
  const tickets = ticketsQ.data?.items?.data ?? []
  const audits = auditQ.data?.items?.data ?? []

  const [tenantName, setTenantName] = useState('')
  const [tenantSlug, setTenantSlug] = useState('')
  const [tenantPanelUserId, setTenantPanelUserId] = useState('')
  const createTenantM = useMutation({
    mutationFn: async () =>
      api.post('/vendor/tenants', {
        name: tenantName.trim(),
        slug: tenantSlug.trim(),
        panel_user_id: tenantPanelUserId.trim() ? Number(tenantPanelUserId) : null,
      }),
    onSuccess: () => {
      setTenantName('')
      setTenantSlug('')
      setTenantPanelUserId('')
      qc.invalidateQueries({ queryKey: ['vendor-tenants'] })
      toast.success('Tenant oluşturuldu')
    },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Tenant oluşturulamadı'),
  })

  const [planCode, setPlanCode] = useState('')
  const [planName, setPlanName] = useState('')
  const createPlanM = useMutation({
    mutationFn: async () => api.post('/vendor/plans', { code: planCode.trim(), name: planName.trim(), billing_cycle: 'monthly' }),
    onSuccess: () => {
      setPlanCode('')
      setPlanName('')
      qc.invalidateQueries({ queryKey: ['vendor-plans'] })
      toast.success('Plan oluşturuldu')
    },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Plan oluşturulamadı'),
  })

  const [featureKey, setFeatureKey] = useState('')
  const [featureName, setFeatureName] = useState('')
  const createFeatureM = useMutation({
    mutationFn: async () => api.post('/vendor/features', { key: featureKey.trim(), name: featureName.trim(), kind: 'boolean' }),
    onSuccess: () => {
      setFeatureKey('')
      setFeatureName('')
      qc.invalidateQueries({ queryKey: ['vendor-features'] })
      toast.success('Feature oluşturuldu')
    },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Feature oluşturulamadı'),
  })

  const [licenseTenantId, setLicenseTenantId] = useState('')
  const [licensePlanId, setLicensePlanId] = useState('')
  const createLicenseM = useMutation({
    mutationFn: async () =>
      api.post('/vendor/licenses', { tenant_id: Number(licenseTenantId), plan_id: Number(licensePlanId), status: 'active' }),
    onSuccess: () => {
      setLicenseTenantId('')
      setLicensePlanId('')
      qc.invalidateQueries({ queryKey: ['vendor-licenses'] })
      toast.success('Lisans oluşturuldu')
    },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Lisans oluşturulamadı'),
  })

  const [subTenantId, setSubTenantId] = useState('')
  const [subLicenseId, setSubLicenseId] = useState('')
  const [subStatus, setSubStatus] = useState<'active' | 'trialing' | 'past_due' | 'canceled' | 'unpaid'>('active')
  const upsertSubM = useMutation({
    mutationFn: async () =>
      api.post('/vendor/billing/subscriptions', {
        tenant_id: Number(subTenantId),
        license_id: subLicenseId ? Number(subLicenseId) : null,
        provider: 'manual',
        external_id: `manual-${Date.now()}`,
        status: subStatus,
        amount_minor: 1999,
        currency: 'USD',
        billing_cycle: 'monthly',
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendor-billing-subs'] })
      qc.invalidateQueries({ queryKey: ['vendor-licenses'] })
      toast.success('Subscription güncellendi')
    },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Subscription kaydedilemedi'),
  })

  const [ticketTenantId, setTicketTenantId] = useState('')
  const [ticketSubject, setTicketSubject] = useState('')
  const [ticketMessage, setTicketMessage] = useState('')
  const [selectedTicketId, setSelectedTicketId] = useState<number | null>(null)
  const [ticketReply, setTicketReply] = useState('')
  const createTicketM = useMutation({
    mutationFn: async () =>
      api.post('/vendor/support/tickets', {
        tenant_id: Number(ticketTenantId),
        subject: ticketSubject.trim(),
        message: ticketMessage.trim(),
        priority: 'normal',
      }),
    onSuccess: () => {
      setTicketSubject('')
      setTicketMessage('')
      qc.invalidateQueries({ queryKey: ['vendor-support-tickets'] })
      toast.success('Ticket oluşturuldu')
    },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Ticket oluşturulamadı'),
  })
  const ticketDetailQ = useQuery({
    queryKey: ['vendor-support-ticket', selectedTicketId],
    enabled: !!isAdmin && !!selectedTicketId,
    queryFn: async () =>
      (await api.get(`/vendor/support/tickets/${selectedTicketId}`)).data as { item: Row & { messages?: Row[] } },
  })
  const ticketReplyM = useMutation({
    mutationFn: async () =>
      api.post(`/vendor/support/tickets/${selectedTicketId}/messages`, {
        message: ticketReply.trim(),
        author_type: 'vendor',
      }),
    onSuccess: () => {
      setTicketReply('')
      qc.invalidateQueries({ queryKey: ['vendor-support-ticket', selectedTicketId] })
      qc.invalidateQueries({ queryKey: ['vendor-support-tickets'] })
      toast.success('Yanıt gönderildi')
    },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Yanıt gönderilemedi'),
  })

  const [mapPlanId, setMapPlanId] = useState('')
  const [mapFeatureId, setMapFeatureId] = useState('')
  const [mapEnabled, setMapEnabled] = useState(true)
  const [mapQuota, setMapQuota] = useState('')
  const mapFeatureM = useMutation({
    mutationFn: async () =>
      api.post(`/vendor/plans/${mapPlanId}/features`, {
        feature_id: Number(mapFeatureId),
        enabled: mapEnabled,
        quota: mapQuota.trim() ? Number(mapQuota) : null,
      }),
    onSuccess: () => {
      setMapQuota('')
      toast.success('Plan feature güncellendi')
    },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Plan feature güncellenemedi'),
  })

  const [selectedTenantId, setSelectedTenantId] = useState('')
  const tenant360Q = useQuery({
    queryKey: ['vendor-ops-customer360', selectedTenantId],
    enabled: !!isAdmin && !!selectedTenantId,
    queryFn: async () =>
      (await api.get(`/vendor/ops/customers/${selectedTenantId}`)).data as {
        tenant: Row
        overview: Row
        licenses: Row[]
        nodes: Row[]
        subscriptions: Row[]
        tickets: Row[]
        domains: Row[]
        modules: Row[]
      },
  })

  const [selectedLicenseTimelineId, setSelectedLicenseTimelineId] = useState('')
  const timelineQ = useQuery({
    queryKey: ['vendor-ops-license-timeline', selectedLicenseTimelineId],
    enabled: !!isAdmin && !!selectedLicenseTimelineId,
    queryFn: async () =>
      (await api.get(`/vendor/ops/licenses/${selectedLicenseTimelineId}/timeline`)).data as {
        license: Row
        events: Row[]
        nodes: Row[]
      },
  })

  const [licenseStatusId, setLicenseStatusId] = useState('')
  const [licenseStatus, setLicenseStatus] = useState<'active' | 'expired' | 'suspended' | 'revoked'>('active')
  const setLicenseStatusM = useMutation({
    mutationFn: async () =>
      api.post(`/vendor/licenses/${licenseStatusId}/status`, {
        status: licenseStatus,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendor-licenses'] })
      qc.invalidateQueries({ queryKey: ['vendor-ops-license-timeline', licenseStatusId] })
      toast.success('Lisans durumu güncellendi')
    },
    onError: (err: unknown) =>
      toast.error((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Lisans durumu güncellenemedi'),
  })

  const exportAuditM = useMutation({
    mutationFn: async () => (await api.get('/vendor/security/audit/export?limit=500')).data as { items: Row[] },
    onSuccess: (data) => {
      const blob = new Blob([JSON.stringify(data.items ?? [], null, 2)], { type: 'application/json' })
      const u = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = u
      a.download = `vendor-audit-${Date.now()}.json`
      a.click()
      URL.revokeObjectURL(u)
      toast.success('Audit export indirildi')
    },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Audit export başarısız'),
  })
  const [siemEnabled, setSiemEnabled] = useState(false)
  const [siemEndpoint, setSiemEndpoint] = useState('')
  const [siemAuthType, setSiemAuthType] = useState<'none' | 'bearer'>('none')
  const [siemSecret, setSiemSecret] = useState('')
  const [siemTimeout, setSiemTimeout] = useState('5')
  const saveSiemM = useMutation({
    mutationFn: async () =>
      api.post('/vendor/security/siem', {
        enabled: siemEnabled,
        endpoint: siemEndpoint.trim(),
        auth_type: siemAuthType,
        secret: siemSecret.trim() || undefined,
        timeout_seconds: Number(siemTimeout || 5),
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendor-siem-config'] })
      setSiemSecret('')
      toast.success('SIEM ayari kaydedildi')
    },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'SIEM ayari kaydedilemedi'),
  })
  const testSiemM = useMutation({
    mutationFn: async () => (await api.post('/vendor/security/siem/test')).data as { ok: boolean; status: number; body: string },
    onSuccess: (data) => toast.success(data.ok ? `SIEM test basarili (${data.status})` : `SIEM test hata (${data.status})`),
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'SIEM test basarisiz'),
  })

  const quickStats = useMemo(
    () => [
      { label: 'Tenant', value: tenants.length },
      { label: 'Plan', value: plans.length },
      { label: 'Feature', value: features.length },
      { label: 'Lisans', value: licenses.length },
      { label: 'Node', value: nodes.length },
    ],
    [tenants.length, plans.length, features.length, licenses.length, nodes.length],
  )
  const filteredTenants = useMemo(
    () => tenants.filter((t) => String(t.name ?? '').toLowerCase().includes(search.toLowerCase()) || String(t.slug ?? '').toLowerCase().includes(search.toLowerCase())),
    [tenants, search],
  )

  useEffect(() => {
    const cfg = siemQ.data?.item
    if (!cfg) return
    setSiemEnabled(!!cfg.enabled)
    setSiemEndpoint(cfg.endpoint ?? '')
    setSiemAuthType(cfg.auth_type ?? 'none')
    setSiemTimeout(String(cfg.timeout_seconds ?? 5))
  }, [siemQ.data])
  const filteredLicenses = useMemo(
    () => licenses.filter((l) => String(l.license_key ?? '').toLowerCase().includes(search.toLowerCase()) || String((l.tenant as Row | undefined)?.name ?? '').toLowerCase().includes(search.toLowerCase())),
    [licenses, search],
  )
  const filteredNodes = useMemo(
    () => nodes.filter((n) => String(n.instance_id ?? '').toLowerCase().includes(search.toLowerCase()) || String(n.hostname ?? '').toLowerCase().includes(search.toLowerCase())),
    [nodes, search],
  )

  if (!isAdmin) return <Navigate to="/dashboard" replace />

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <ShieldCheck className="h-8 w-8 text-indigo-500" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Vendor Control Plane</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Tenant, lisans, node, billing ve support operasyon merkezi.
          </p>
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        {quickStats.map((s) => (
          <div key={s.label} className="card p-4">
            <p className="text-xs text-gray-500">{s.label}</p>
            <p className="text-2xl font-semibold text-gray-900 dark:text-white">{s.value}</p>
          </div>
        ))}
      </div>

      <div className="flex flex-wrap gap-2">
        {[
          ['overview', 'Core'],
          ['billing', 'Billing'],
          ['support', 'Support'],
          ['security', 'Security'],
        ].map(([id, label]) => (
          <button
            key={id}
            type="button"
            className={`rounded-lg px-3 py-1.5 text-sm ${tab === id ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800'}`}
            onClick={() => setTab(id as typeof tab)}
          >
            {label}
          </button>
        ))}
      </div>
      <input
        className="input w-full max-w-lg"
        placeholder="Tenant, lisans key, node instance ara..."
        value={search}
        onChange={(e) => setSearch(e.target.value)}
      />

      {tab === 'overview' && (
        <div className="grid gap-6 lg:grid-cols-2">
          <div className="card p-5 space-y-3">
            <h2 className="font-semibold text-gray-900 dark:text-white">Yeni Tenant</h2>
            <input className="input w-full" placeholder="Tenant adı" value={tenantName} onChange={(e) => setTenantName(e.target.value)} />
            <input className="input w-full" placeholder="tenant-slug" value={tenantSlug} onChange={(e) => setTenantSlug(e.target.value)} />
            <input className="input w-full" placeholder="Panel user id (opsiyonel)" value={tenantPanelUserId} onChange={(e) => setTenantPanelUserId(e.target.value)} />
            <button className="btn-primary" disabled={createTenantM.isPending || !tenantName || !tenantSlug} onClick={() => createTenantM.mutate()}>
              Tenant oluştur
            </button>
          </div>
          <div className="card p-5 space-y-3">
            <h2 className="font-semibold text-gray-900 dark:text-white">Yeni Plan</h2>
            <input className="input w-full" placeholder="pro-monthly" value={planCode} onChange={(e) => setPlanCode(e.target.value)} />
            <input className="input w-full" placeholder="Pro Monthly" value={planName} onChange={(e) => setPlanName(e.target.value)} />
            <button className="btn-primary" disabled={createPlanM.isPending || !planCode || !planName} onClick={() => createPlanM.mutate()}>
              Plan oluştur
            </button>
          </div>
          <div className="card p-5 space-y-3">
            <h2 className="font-semibold text-gray-900 dark:text-white">Yeni Feature</h2>
            <input className="input w-full" placeholder="deploy.rollback" value={featureKey} onChange={(e) => setFeatureKey(e.target.value)} />
            <input className="input w-full" placeholder="Deploy Rollback" value={featureName} onChange={(e) => setFeatureName(e.target.value)} />
            <button className="btn-primary" disabled={createFeatureM.isPending || !featureKey || !featureName} onClick={() => createFeatureM.mutate()}>
              Feature oluştur
            </button>
          </div>
          <div className="card p-5 space-y-3">
            <h2 className="font-semibold text-gray-900 dark:text-white">Yeni Lisans</h2>
            <select className="input w-full" value={licenseTenantId} onChange={(e) => setLicenseTenantId(e.target.value)}>
              <option value="">Tenant seç</option>
              {tenants.map((t) => (
                <option key={String(t.id)} value={String(t.id)}>{String(t.name)}</option>
              ))}
            </select>
            <select className="input w-full" value={licensePlanId} onChange={(e) => setLicensePlanId(e.target.value)}>
              <option value="">Plan seç</option>
              {plans.map((p) => (
                <option key={String(p.id)} value={String(p.id)}>{String(p.name)}</option>
              ))}
            </select>
            <button className="btn-primary" disabled={createLicenseM.isPending || !licenseTenantId || !licensePlanId} onClick={() => createLicenseM.mutate()}>
              Lisans oluştur
            </button>
          </div>
          <div className="card p-5 space-y-3 lg:col-span-2">
            <h2 className="font-semibold text-gray-900 dark:text-white">Plan Feature Atama</h2>
            <div className="grid gap-3 md:grid-cols-5">
              <select className="input" value={mapPlanId} onChange={(e) => setMapPlanId(e.target.value)}>
                <option value="">Plan</option>
                {plans.map((p) => <option key={String(p.id)} value={String(p.id)}>{String(p.name)}</option>)}
              </select>
              <select className="input" value={mapFeatureId} onChange={(e) => setMapFeatureId(e.target.value)}>
                <option value="">Feature</option>
                {features.map((f) => <option key={String(f.id)} value={String(f.id)}>{String(f.key)}</option>)}
              </select>
              <select className="input" value={mapEnabled ? '1' : '0'} onChange={(e) => setMapEnabled(e.target.value === '1')}>
                <option value="1">Enabled</option>
                <option value="0">Disabled</option>
              </select>
              <input className="input" placeholder="Quota (opsiyonel)" value={mapQuota} onChange={(e) => setMapQuota(e.target.value)} />
              <button className="btn-primary" disabled={mapFeatureM.isPending || !mapPlanId || !mapFeatureId} onClick={() => mapFeatureM.mutate()}>
                Ata
              </button>
            </div>
          </div>
          <div className="card p-5 lg:col-span-2">
            <h3 className="font-semibold mb-3 text-gray-900 dark:text-white">Tenant / Lisans / Node Özet Listeleri</h3>
            <div className="grid gap-4 lg:grid-cols-3 text-sm">
              <div>
                <p className="mb-2 font-medium">Tenant ({filteredTenants.length})</p>
                <div className="space-y-1 max-h-48 overflow-auto">
                  {filteredTenants.map((t) => <div key={String(t.id)} className="rounded border border-gray-200 dark:border-gray-800 px-2 py-1">{String(t.name)} <span className="text-gray-500">({String(t.slug)})</span></div>)}
                </div>
              </div>
              <div>
                <p className="mb-2 font-medium">Lisans ({filteredLicenses.length})</p>
                <div className="space-y-1 max-h-48 overflow-auto">
                  {filteredLicenses.map((l) => <div key={String(l.id)} className="rounded border border-gray-200 dark:border-gray-800 px-2 py-1">{String(l.license_key)} <span className="text-gray-500">[{String(l.status)}]</span></div>)}
                </div>
              </div>
              <div>
                <p className="mb-2 font-medium">Node ({filteredNodes.length})</p>
                <div className="space-y-1 max-h-48 overflow-auto">
                  {filteredNodes.map((n) => <div key={String(n.id)} className="rounded border border-gray-200 dark:border-gray-800 px-2 py-1">{String(n.hostname || n.instance_id)} <span className="text-gray-500">[{String(n.status)}]</span></div>)}
                </div>
              </div>
            </div>
          </div>
          <div className="card p-5 lg:col-span-2 space-y-3">
            <h3 className="font-semibold text-gray-900 dark:text-white">Musteri 360 (tenant detay)</h3>
            <div className="grid gap-3 md:grid-cols-3">
              <select className="input" value={selectedTenantId} onChange={(e) => setSelectedTenantId(e.target.value)}>
                <option value="">Tenant sec</option>
                {tenants.map((t) => <option key={String(t.id)} value={String(t.id)}>{String(t.name)} ({String(t.slug)})</option>)}
              </select>
              <select className="input" value={selectedLicenseTimelineId} onChange={(e) => setSelectedLicenseTimelineId(e.target.value)}>
                <option value="">Lisans timeline sec</option>
                {licenses.map((l) => <option key={String(l.id)} value={String(l.id)}>{String(l.license_key)}</option>)}
              </select>
              <div className="flex gap-2">
                <select className="input" value={licenseStatusId} onChange={(e) => setLicenseStatusId(e.target.value)}>
                  <option value="">Lisans sec</option>
                  {licenses.map((l) => <option key={String(l.id)} value={String(l.id)}>{String(l.license_key)}</option>)}
                </select>
                <select className="input" value={licenseStatus} onChange={(e) => setLicenseStatus(e.target.value as typeof licenseStatus)}>
                  {['active', 'expired', 'suspended', 'revoked'].map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
                <button className="btn-primary" disabled={setLicenseStatusM.isPending || !licenseStatusId} onClick={() => setLicenseStatusM.mutate()}>
                  Guncelle
                </button>
              </div>
            </div>
            {tenant360Q.data && (
              <div className="grid gap-4 md:grid-cols-2 text-sm">
                <div className="rounded border border-gray-200 dark:border-gray-800 p-3">
                  <p className="font-medium mb-2">Genel durum</p>
                  <p>Lisans: {String((tenant360Q.data.overview as Row).licenses_active ?? 0)} aktif / {String((tenant360Q.data.overview as Row).licenses_total ?? 0)} toplam</p>
                  <p>Node: {String((tenant360Q.data.overview as Row).nodes_online ?? 0)} online / {String((tenant360Q.data.overview as Row).nodes_total ?? 0)} toplam</p>
                  <p>Abonelik: {String((tenant360Q.data.overview as Row).subscriptions_total ?? 0)}</p>
                  <p>Acik ticket: {String((tenant360Q.data.overview as Row).tickets_open ?? 0)}</p>
                </div>
                <div className="rounded border border-gray-200 dark:border-gray-800 p-3">
                  <p className="font-medium mb-2">Node / IP / host gorunumu</p>
                  <div className="space-y-1 max-h-40 overflow-auto">
                    {(tenant360Q.data.nodes ?? []).map((n) => (
                      <div key={String(n.id)} className="rounded border border-gray-200 dark:border-gray-800 px-2 py-1">
                        {String(n.hostname ?? '-')} | instance: {String(n.instance_id ?? '-')} | ip: {String(n.ip_address ?? '-')} | {String(n.status)}
                      </div>
                    ))}
                  </div>
                </div>
                <div className="rounded border border-gray-200 dark:border-gray-800 p-3">
                  <p className="font-medium mb-2">Domain envanteri</p>
                  <div className="space-y-1 max-h-40 overflow-auto">
                    {(tenant360Q.data.domains ?? []).map((d) => (
                      <div key={String(d.id)} className="rounded border border-gray-200 dark:border-gray-800 px-2 py-1">
                        {String(d.name)} | {String(d.server_type ?? '-')} | PHP {String(d.php_version ?? '-')} | SSL {String(d.ssl_enabled ?? false)}
                      </div>
                    ))}
                  </div>
                </div>
                <div className="rounded border border-gray-200 dark:border-gray-800 p-3">
                  <p className="font-medium mb-2">Kullanilan moduller</p>
                  <div className="space-y-1 max-h-40 overflow-auto">
                    {(tenant360Q.data.modules ?? []).map((m) => (
                      <div key={String(m.id)} className="rounded border border-gray-200 dark:border-gray-800 px-2 py-1">
                        {String(m.name)} ({String(m.slug)})
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            )}
            {timelineQ.data && (
              <div className="rounded border border-gray-200 dark:border-gray-800 p-3 text-sm">
                <p className="font-medium mb-2">Lisans timeline</p>
                <div className="space-y-1 max-h-40 overflow-auto">
                  {(timelineQ.data.events ?? []).map((ev) => (
                    <div key={String(ev.id)} className="rounded border border-gray-200 dark:border-gray-800 px-2 py-1">
                      {String(ev.event)} | {String(ev.severity)} | {String(ev.created_at)}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {tab === 'billing' && (
        <div className="space-y-4">
          <div className="card p-5 space-y-3">
            <h2 className="font-semibold text-gray-900 dark:text-white">Subscription upsert (manual test)</h2>
            <div className="grid gap-3 md:grid-cols-4">
              <select className="input" value={subTenantId} onChange={(e) => setSubTenantId(e.target.value)}>
                <option value="">Tenant</option>
                {tenants.map((t) => <option key={String(t.id)} value={String(t.id)}>{String(t.name)}</option>)}
              </select>
              <select className="input" value={subLicenseId} onChange={(e) => setSubLicenseId(e.target.value)}>
                <option value="">Lisans (opsiyonel)</option>
                {licenses.map((l) => <option key={String(l.id)} value={String(l.id)}>{String(l.license_key)}</option>)}
              </select>
              <select className="input" value={subStatus} onChange={(e) => setSubStatus(e.target.value as typeof subStatus)}>
                {['active', 'trialing', 'past_due', 'canceled', 'unpaid'].map((s) => <option key={s} value={s}>{s}</option>)}
              </select>
              <button className="btn-primary" disabled={upsertSubM.isPending || !subTenantId} onClick={() => upsertSubM.mutate()}>
                Kaydet
              </button>
            </div>
          </div>
          <div className="card p-5">
            <h3 className="font-semibold mb-3 text-gray-900 dark:text-white">Subscriptions</h3>
            <div className="overflow-auto text-sm">
              <table className="w-full">
                <thead><tr className="text-left text-gray-500"><th className="py-2">ID</th><th>Status</th><th>Tenant</th><th>License</th></tr></thead>
                <tbody>
                  {subs.map((s) => (
                    <tr key={String(s.id)} className="border-t border-gray-200 dark:border-gray-800">
                      <td className="py-2">{String(s.id)}</td><td>{String(s.status)}</td><td>{String((s.tenant as Row | undefined)?.name ?? '-')}</td><td>{String((s.license as Row | undefined)?.license_key ?? '-')}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          <div className="card p-5">
            <h3 className="font-semibold mb-3 text-gray-900 dark:text-white">Invoices</h3>
            <div className="overflow-auto text-sm">
              <table className="w-full">
                <thead><tr className="text-left text-gray-500"><th className="py-2">ID</th><th>Status</th><th>Tenant</th><th>Total</th><th>Due</th></tr></thead>
                <tbody>
                  {invoices.map((inv) => (
                    <tr key={String(inv.id)} className="border-t border-gray-200 dark:border-gray-800">
                      <td className="py-2">{String(inv.id)}</td>
                      <td>{String(inv.status)}</td>
                      <td>{String((inv.tenant as Row | undefined)?.name ?? '-')}</td>
                      <td>{String(inv.total_minor ?? 0)} {String(inv.currency ?? '')}</td>
                      <td>{String(inv.due_at ?? '-')}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          <div className="card p-5">
            <h3 className="font-semibold mb-3 text-gray-900 dark:text-white">Payments</h3>
            <div className="overflow-auto text-sm">
              <table className="w-full">
                <thead><tr className="text-left text-gray-500"><th className="py-2">ID</th><th>Status</th><th>Tenant</th><th>Amount</th><th>Paid</th></tr></thead>
                <tbody>
                  {payments.map((p) => (
                    <tr key={String(p.id)} className="border-t border-gray-200 dark:border-gray-800">
                      <td className="py-2">{String(p.id)}</td>
                      <td>{String(p.status)}</td>
                      <td>{String((p.tenant as Row | undefined)?.name ?? '-')}</td>
                      <td>{String(p.amount_minor ?? 0)} {String(p.currency ?? '')}</td>
                      <td>{String(p.paid_at ?? '-')}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {tab === 'support' && (
        <div className="space-y-4">
          <div className="card p-5 space-y-3">
            <h2 className="font-semibold text-gray-900 dark:text-white">Yeni Support Ticket</h2>
            <select className="input w-full" value={ticketTenantId} onChange={(e) => setTicketTenantId(e.target.value)}>
              <option value="">Tenant seç</option>
              {tenants.map((t) => <option key={String(t.id)} value={String(t.id)}>{String(t.name)}</option>)}
            </select>
            <input className="input w-full" placeholder="Konu" value={ticketSubject} onChange={(e) => setTicketSubject(e.target.value)} />
            <textarea className="input w-full min-h-24" placeholder="Mesaj" value={ticketMessage} onChange={(e) => setTicketMessage(e.target.value)} />
            <button className="btn-primary" disabled={createTicketM.isPending || !ticketTenantId || !ticketSubject || !ticketMessage} onClick={() => createTicketM.mutate()}>
              Ticket oluştur
            </button>
          </div>
          <div className="card p-5">
            <h3 className="font-semibold mb-3 text-gray-900 dark:text-white">Ticket listesi</h3>
            <div className="space-y-2 text-sm">
              {tickets.map((t) => (
                <button key={String(t.id)} type="button" onClick={() => setSelectedTicketId(Number(t.id))} className="w-full text-left rounded border border-gray-200 dark:border-gray-800 p-3 hover:bg-gray-50 dark:hover:bg-gray-800/60">
                  <p className="font-medium">{String(t.subject)}</p>
                  <p className="text-gray-500">{String(t.status)} | {String((t.tenant as Row | undefined)?.name ?? '-')}</p>
                </button>
              ))}
            </div>
          </div>
          {selectedTicketId && (
            <div className="card p-5 space-y-3">
              <h3 className="font-semibold text-gray-900 dark:text-white">Ticket Detayı #{selectedTicketId}</h3>
              <div className="space-y-2 max-h-56 overflow-auto">
                {(ticketDetailQ.data?.item?.messages ?? []).map((m) => (
                  <div key={String((m as Row).id)} className="rounded border border-gray-200 dark:border-gray-800 px-3 py-2 text-sm">
                    <p className="font-medium">{String((m as Row).author_type ?? 'vendor')}</p>
                    <p className="text-gray-600 dark:text-gray-300">{String((m as Row).message ?? '')}</p>
                  </div>
                ))}
              </div>
              <textarea className="input w-full min-h-24" placeholder="Yanıt yaz..." value={ticketReply} onChange={(e) => setTicketReply(e.target.value)} />
              <div className="flex gap-2">
                <button className="btn-primary" disabled={ticketReplyM.isPending || !ticketReply.trim()} onClick={() => ticketReplyM.mutate()}>
                  Yanıt gönder
                </button>
                <button className="btn-secondary" onClick={() => setSelectedTicketId(null)}>Kapat</button>
              </div>
            </div>
          )}
        </div>
      )}

      {tab === 'security' && (
        <div className="space-y-4">
          <div className="card p-5 space-y-3">
            <div className="mb-1 flex items-center justify-between gap-2">
              <h3 className="font-semibold text-gray-900 dark:text-white">SIEM Export</h3>
              <button className="btn-secondary" disabled={testSiemM.isPending} onClick={() => testSiemM.mutate()}>
                Test gonder
              </button>
            </div>
            <div className="grid gap-3 md:grid-cols-5">
              <select className="input" value={siemEnabled ? '1' : '0'} onChange={(e) => setSiemEnabled(e.target.value === '1')}>
                <option value="1">Enabled</option>
                <option value="0">Disabled</option>
              </select>
              <input className="input md:col-span-2" placeholder="https://siem.example.com/ingest" value={siemEndpoint} onChange={(e) => setSiemEndpoint(e.target.value)} />
              <select className="input" value={siemAuthType} onChange={(e) => setSiemAuthType(e.target.value as 'none' | 'bearer')}>
                <option value="none">No auth</option>
                <option value="bearer">Bearer</option>
              </select>
              <input className="input" placeholder="Timeout (sn)" value={siemTimeout} onChange={(e) => setSiemTimeout(e.target.value)} />
            </div>
            <div className="grid gap-3 md:grid-cols-2">
              <input className="input" type="password" placeholder={siemQ.data?.item?.has_secret ? 'Secret set (degistirmek icin yaz)' : 'Bearer secret'} value={siemSecret} onChange={(e) => setSiemSecret(e.target.value)} />
              <button className="btn-primary" disabled={saveSiemM.isPending || !siemEndpoint.trim()} onClick={() => saveSiemM.mutate()}>
                SIEM kaydet
              </button>
            </div>
          </div>
          <div className="card p-5">
            <div className="mb-3 flex items-center justify-between gap-2">
              <h3 className="font-semibold text-gray-900 dark:text-white">Audit feed</h3>
              <button className="btn-secondary" disabled={exportAuditM.isPending} onClick={() => exportAuditM.mutate()}>
                Audit export indir
              </button>
            </div>
            <div className="space-y-2 text-sm">
              {audits.map((a) => (
                <div key={String(a.id)} className="rounded border border-gray-200 dark:border-gray-800 p-3">
                  <p className="font-medium">{String(a.event)}</p>
                  <p className="text-gray-500">{String(a.severity)} | {String(a.created_at)}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

