/**
 * Panel `public/` altında veya sanal kökte yayın için taban yolu.
 * Üretimde `index.html` içindeki `./assets/index-*.js` adresinden çıkarılır (XAMPP alt klasörü vb.).
 */
export function inferPublicPathPrefix(): string {
  if (typeof document === 'undefined') {
    return ''
  }
  const el = document.querySelector(
    'script[type="module"][src*="/assets/index-"]',
  ) as HTMLScriptElement | null
  if (!el?.src) {
    return ''
  }
  const path = new URL(el.src).pathname
  const marker = '/assets/index-'
  const idx = path.indexOf(marker)
  if (idx <= 0) {
    return ''
  }
  return path.slice(0, idx)
}
