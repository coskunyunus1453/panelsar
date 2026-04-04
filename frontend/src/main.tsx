import React from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from 'react-hot-toast'
import App from './App'
import './i18n'
import './index.css'
import { inferPublicPathPrefix } from './lib/publicPath'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
      staleTime: 5 * 60 * 1000,
    },
  },
})

function routerBasename(): string | undefined {
  if ((import.meta as any).env?.DEV) {
    const b = String((import.meta as any).env?.BASE_URL || '/').replace(/\/+$/, '')
    if (!b || b === '/' || b === '.' || b === './') {
      return undefined
    }
    return b.startsWith('/') ? b : `/${b}`
  }
  const inferred = inferPublicPathPrefix()
  if (inferred && inferred !== '/') {
    return inferred
  }
  const b = String((import.meta as any).env?.BASE_URL || '/').replace(/\/+$/, '')
  if (!b || b === '/' || b === '.' || b === './') {
    return undefined
  }
  return b.startsWith('/') ? b : `/${b}`
}

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter basename={routerBasename()}>
        <App />
        <Toaster
          position="top-right"
          toastOptions={{
            duration: 4000,
            style: {
              background: '#1e293b',
              color: '#f1f5f9',
              border: '1px solid #334155',
            },
          }}
        />
      </BrowserRouter>
    </QueryClientProvider>
  </React.StrictMode>,
)
