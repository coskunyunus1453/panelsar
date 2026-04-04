import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import ReactMarkdown from 'react-markdown'
import { Save, Send, Eye, FileText } from 'lucide-react'

type LocaleOpt = { value: string; label: string }

type Props = {
  locale: string
  localeOptions: readonly LocaleOpt[]
  onLocaleChange: (locale: string) => void
  title: string
  onTitleChange: (v: string) => void
  body: string
  onBodyChange: (v: string) => void
  slug?: string
  onSlugChange?: (v: string) => void
  showSlug?: boolean
  disabled?: boolean
  status?: string
  onSaveDraft: () => void
  onPublish: () => void
}

export default function CmsMarkdownEditor({
  locale,
  localeOptions,
  onLocaleChange,
  title,
  onTitleChange,
  body,
  onBodyChange,
  slug = '',
  onSlugChange,
  showSlug = false,
  disabled = false,
  status,
  onSaveDraft,
  onPublish,
}: Props) {
  const { t } = useTranslation()
  const preview = useMemo(
    () => (
      <div className="markdown-preview max-h-[min(60vh,520px)] overflow-auto rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-950">
        <ReactMarkdown
          components={{
            h1: (p) => <h1 className="mb-2 text-xl font-bold text-gray-900 dark:text-white" {...p} />,
            h2: (p) => <h2 className="mb-2 mt-4 text-lg font-semibold text-gray-900 dark:text-white" {...p} />,
            h3: (p) => <h3 className="mb-1 mt-3 text-base font-medium text-gray-800 dark:text-gray-100" {...p} />,
            p: (p) => <p className="mb-2 leading-relaxed text-gray-700 dark:text-gray-300" {...p} />,
            ul: (p) => <ul className="mb-2 list-disc pl-5 text-gray-700 dark:text-gray-300" {...p} />,
            ol: (p) => <ol className="mb-2 list-decimal pl-5 text-gray-700 dark:text-gray-300" {...p} />,
            li: (p) => <li className="mb-1" {...p} />,
            a: (p) => (
              <a className="text-primary-600 underline hover:text-primary-500 dark:text-primary-400" {...p} />
            ),
            code: (p) => (
              <code
                className="rounded bg-gray-100 px-1 py-0.5 font-mono text-xs text-gray-800 dark:bg-gray-800 dark:text-gray-200"
                {...p}
              />
            ),
            pre: (p) => (
              <pre
                className="mb-2 overflow-x-auto rounded-lg bg-gray-100 p-3 font-mono text-xs dark:bg-gray-900"
                {...p}
              />
            ),
            blockquote: (p) => (
              <blockquote
                className="border-l-4 border-primary-400 pl-3 italic text-gray-600 dark:text-gray-400"
                {...p}
              />
            ),
          }}
        >
          {body || '*…*'}
        </ReactMarkdown>
      </div>
    ),
    [body],
  )

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end gap-4">
        <div className="min-w-[160px]">
          <label className="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
            {t('cms_admin.locale')}
          </label>
          <select
            className="input w-full max-w-xs"
            value={locale}
            disabled={disabled}
            onChange={(e) => onLocaleChange(e.target.value)}
          >
            {localeOptions.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
        </div>
        {showSlug && onSlugChange && (
          <div className="min-w-[200px] flex-1">
            <label className="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
              {t('cms_admin.slug')}
            </label>
            <input
              type="text"
              className="input w-full"
              value={slug}
              disabled={disabled}
              onChange={(e) => onSlugChange(e.target.value)}
              placeholder="ornek-belge"
            />
          </div>
        )}
        {status && (
          <span className="inline-flex items-center gap-1 rounded-full border border-gray-200 px-2 py-1 text-xs dark:border-gray-700">
            <FileText className="h-3.5 w-3.5" />
            {status === 'published' ? t('cms_admin.status_published') : t('cms_admin.status_draft')}
          </span>
        )}
      </div>

      <div>
        <label className="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
          {t('cms_admin.title')}
        </label>
        <input
          type="text"
          className="input w-full"
          value={title}
          disabled={disabled}
          onChange={(e) => onTitleChange(e.target.value)}
          placeholder={t('cms_admin.title_placeholder')}
        />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
          <div className="mb-1 flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
            <Eye className="h-3.5 w-3.5" />
            {t('cms_admin.markdown')}
          </div>
          <textarea
            className="input min-h-[min(50vh,440px)] w-full resize-y font-mono text-sm"
            value={body}
            disabled={disabled}
            onChange={(e) => onBodyChange(e.target.value)}
            spellCheck
          />
        </div>
        <div>
          <div className="mb-1 flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
            <Eye className="h-3.5 w-3.5" />
            {t('cms_admin.preview')}
          </div>
          {preview}
        </div>
      </div>

      <div className="flex flex-wrap gap-2">
        <button type="button" className="btn-secondary inline-flex items-center gap-2" disabled={disabled} onClick={onSaveDraft}>
          <Save className="h-4 w-4" />
          {t('cms_admin.save_draft')}
        </button>
        <button type="button" className="btn-primary inline-flex items-center gap-2" disabled={disabled} onClick={onPublish}>
          <Send className="h-4 w-4" />
          {t('cms_admin.publish')}
        </button>
      </div>
    </div>
  )
}
