export type CmsPageKind = 'landing' | 'install' | 'doc' | 'blog'

export type CmsPageDto = {
  id: number
  kind: string
  slug: string
  locale: string
  title: string | null
  body_markdown: string | null
  status: string
  published_at: string | null
  created_at?: string
  updated_at?: string
}
