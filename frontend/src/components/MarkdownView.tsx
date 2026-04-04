import ReactMarkdown from 'react-markdown'
import rehypeSanitize from 'rehype-sanitize'

type Props = {
  markdown: string
  className?: string
}

export default function MarkdownView({ markdown, className = '' }: Props) {
  return (
    <div className={`markdown-view text-gray-800 dark:text-gray-200 ${className}`}>
      <ReactMarkdown
        rehypePlugins={[rehypeSanitize]}
        components={{
          h1: (p) => <h1 className="mb-3 text-2xl font-bold text-gray-900 dark:text-white" {...p} />,
          h2: (p) => <h2 className="mb-2 mt-8 text-xl font-semibold text-gray-900 dark:text-white" {...p} />,
          h3: (p) => <h3 className="mb-2 mt-6 text-lg font-medium text-gray-900 dark:text-white" {...p} />,
          p: (p) => <p className="mb-3 leading-relaxed" {...p} />,
          ul: (p) => <ul className="mb-3 list-disc pl-6" {...p} />,
          ol: (p) => <ol className="mb-3 list-decimal pl-6" {...p} />,
          li: (p) => <li className="mb-1" {...p} />,
          a: (p) => (
            <a
              className="text-primary-600 underline hover:text-primary-500 dark:text-primary-400"
              target="_blank"
              rel="noopener noreferrer"
              {...p}
            />
          ),
          code: (p) => (
            <code
              className="rounded bg-gray-100 px-1 py-0.5 font-mono text-sm text-gray-900 dark:bg-gray-800 dark:text-gray-100"
              {...p}
            />
          ),
          pre: (p) => (
            <pre
              className="mb-4 overflow-x-auto rounded-lg border border-gray-200 bg-gray-50 p-4 font-mono text-sm dark:border-gray-700 dark:bg-gray-900/60"
              {...p}
            />
          ),
          blockquote: (p) => (
            <blockquote className="mb-3 border-l-4 border-primary-500 pl-4 italic text-gray-600 dark:text-gray-400" {...p} />
          ),
          table: (p) => (
            <div className="mb-4 overflow-x-auto">
              <table className="min-w-full border-collapse border border-gray-200 text-sm dark:border-gray-700" {...p} />
            </div>
          ),
          th: (p) => <th className="border border-gray-200 bg-gray-100 px-3 py-2 text-left dark:border-gray-700 dark:bg-gray-800" {...p} />,
          td: (p) => <td className="border border-gray-200 px-3 py-2 dark:border-gray-700" {...p} />,
        }}
      >
        {markdown}
      </ReactMarkdown>
    </div>
  )
}
