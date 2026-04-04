<x-admin.layout title="Site ayarları">
    <div class="mx-auto max-w-3xl space-y-8">
        <div>
            <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Site ayarları</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Logo ve favicon doğrudan yüklenir; site adı çevirilerden önce bu alandaki değeri kullanır (özel metin &gt; çeviri anahtarı).</p>
        </div>

        <form method="POST" action="{{ route('admin.site-settings.update') }}" enctype="multipart/form-data" class="space-y-8">
            @csrf
            @method('PUT')

            <div class="admin-form-panel space-y-5 !shadow-none">
                <p class="admin-label-block">Kimlik</p>

                <div>
                    <label for="site_name" class="admin-label">Site adı</label>
                    <input id="site_name" name="site_name" type="text" value="{{ old('site_name', $siteName) }}" class="admin-field mt-1" placeholder="Boş bırakılırsa dil dosyasındaki marka adı kullanılır" />
                    @error('site_name')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="site_tagline" class="admin-label">Kısa slogan / alt başlık</label>
                    <input id="site_tagline" name="site_tagline" type="text" value="{{ old('site_tagline', $siteTagline) }}" class="admin-field mt-1" placeholder="Örn. Linux Hosting Panel" />
                    @error('site_tagline')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="site_logo" class="admin-label">Site logosu</label>
                        <input id="site_logo" name="site_logo" type="file" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml" class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-orange-500/15 file:px-3 file:py-2 file:text-sm file:font-medium file:text-orange-800 dark:text-slate-400 dark:file:bg-orange-500/10 dark:file:text-orange-200" />
                        <p class="mt-1 text-[11px] text-slate-500">PNG, JPG, WebP veya SVG. En fazla 4 MB.</p>
                        @if ($logoUrl)
                            <div class="mt-3 flex items-center gap-3">
                                <img src="{{ $logoUrl }}" alt="Önizleme" class="h-12 w-auto max-w-[200px] rounded-lg border border-slate-200 object-contain dark:border-slate-700" />
                                <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                                    <input type="checkbox" name="remove_site_logo" value="1" class="admin-checkbox" />
                                    Logoyu kaldır
                                </label>
                            </div>
                        @endif
                        @error('site_logo')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <p class="admin-label mb-2">Logo boyutu (piksel)</p>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label for="site_logo_max_height_px" class="text-[11px] font-medium text-slate-600 dark:text-slate-400">Üst menü · max yükseklik</label>
                                <input id="site_logo_max_height_px" name="site_logo_max_height_px" type="number" min="20" max="200" step="1"
                                       value="{{ old('site_logo_max_height_px', $logoMaxHeightPx) }}"
                                       class="admin-field mt-1 font-mono text-sm" placeholder="44" />
                                <p class="mt-0.5 text-[10px] text-slate-500">Boş: 44 px</p>
                                @error('site_logo_max_height_px')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="site_logo_max_width_px" class="text-[11px] font-medium text-slate-600 dark:text-slate-400">Üst menü · max genişlik</label>
                                <input id="site_logo_max_width_px" name="site_logo_max_width_px" type="number" min="0" max="600" step="1"
                                       value="{{ old('site_logo_max_width_px', $logoMaxWidthPx) }}"
                                       class="admin-field mt-1 font-mono text-sm" placeholder="Örn. 208" />
                                <p class="mt-0.5 text-[10px] text-slate-500">0 veya boş: genişlik sınırı yok (max-width: 100%)</p>
                                @error('site_logo_max_width_px')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="site_logo_footer_max_height_px" class="text-[11px] font-medium text-slate-600 dark:text-slate-400">Altbilgi · max yükseklik</label>
                                <input id="site_logo_footer_max_height_px" name="site_logo_footer_max_height_px" type="number" min="16" max="120" step="1"
                                       value="{{ old('site_logo_footer_max_height_px', $logoFooterMaxHeightPx) }}"
                                       class="admin-field mt-1 font-mono text-sm" placeholder="Otomatik" />
                                <p class="mt-0.5 text-[10px] text-slate-500">Boş: üst menüye göre hesaplanır</p>
                                @error('site_logo_footer_max_height_px')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="site_logo_footer_max_width_px" class="text-[11px] font-medium text-slate-600 dark:text-slate-400">Altbilgi · max genişlik</label>
                                <input id="site_logo_footer_max_width_px" name="site_logo_footer_max_width_px" type="number" min="0" max="600" step="1"
                                       value="{{ old('site_logo_footer_max_width_px', $logoFooterMaxWidthPx) }}"
                                       class="admin-field mt-1 font-mono text-sm" placeholder="Otomatik" />
                                <p class="mt-0.5 text-[10px] text-slate-500">0 veya boş: üst genişliğe göre</p>
                                @error('site_logo_footer_max_width_px')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="favicon" class="admin-label">Favicon</label>
                        <input id="favicon" name="favicon" type="file" accept=".png,.jpg,.jpeg,.webp,.ico,.svg" class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-orange-500/15 file:px-3 file:py-2 file:text-sm file:font-medium file:text-orange-800 dark:text-slate-400 dark:file:bg-orange-500/10 dark:file:text-orange-200" />
                        <p class="mt-1 text-[11px] text-slate-500">PNG, ICO, SVG veya WebP. En fazla 1 MB.</p>
                        @if ($faviconUrl)
                            <div class="mt-3 flex items-center gap-3">
                                <img src="{{ $faviconUrl }}" alt="Favicon" class="h-8 w-8 rounded border border-slate-200 object-contain dark:border-slate-700" />
                                <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                                    <input type="checkbox" name="remove_favicon" value="1" class="admin-checkbox" />
                                    Favicon’u kaldır
                                </label>
                            </div>
                        @endif
                        @error('favicon')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <details class="admin-form-panel space-y-5 !shadow-none open:ring-1 open:ring-orange-500/20">
                <summary class="cursor-pointer list-none font-semibold text-slate-900 dark:text-slate-100">
                    <span class="inline-flex items-center gap-2">Gelişmiş ayarlar <span class="text-xs font-normal text-slate-500">(iletişim, sosyal, analitik)</span></span>
                </summary>
                <div class="mt-5 space-y-5 border-t border-slate-200/80 pt-5 dark:border-slate-700/80">
                    <div>
                        <label for="contact_email" class="admin-label">İletişim e-postası</label>
                        <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $contactEmail) }}" class="admin-field mt-1 font-mono text-sm" placeholder="destek@ornek.com" />
                        <p class="mt-1 text-[11px] text-slate-500">Altbilgide mailto bağlantısı olarak gösterilir.</p>
                        @error('contact_email')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-1">
                        <div>
                            <label for="social_twitter_url" class="admin-label">X (Twitter) profil URL</label>
                            <input id="social_twitter_url" name="social_twitter_url" type="url" value="{{ old('social_twitter_url', $socialTwitter) }}" class="admin-field mt-1 font-mono text-sm" placeholder="https://x.com/…" />
                            @error('social_twitter_url')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="social_github_url" class="admin-label">GitHub URL</label>
                            <input id="social_github_url" name="social_github_url" type="url" value="{{ old('social_github_url', $socialGithub) }}" class="admin-field mt-1 font-mono text-sm" placeholder="https://github.com/…" />
                            @error('social_github_url')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="social_linkedin_url" class="admin-label">LinkedIn URL</label>
                            <input id="social_linkedin_url" name="social_linkedin_url" type="url" value="{{ old('social_linkedin_url', $socialLinkedin) }}" class="admin-field mt-1 font-mono text-sm" placeholder="https://www.linkedin.com/…" />
                            @error('social_linkedin_url')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="analytics_ga4_id" class="admin-label">Google Analytics 4 ölçüm kodu</label>
                        <input id="analytics_ga4_id" name="analytics_ga4_id" type="text" value="{{ old('analytics_ga4_id', $analyticsGa4) }}" class="admin-field mt-1 font-mono text-sm" placeholder="G-XXXXXXXXXX" />
                        <p class="mt-1 text-[11px] text-slate-500">Yalnızca G- ile başlayan GA4 kimliği kabul edilir.</p>
                        @error('analytics_ga4_id')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="footer_extra_note" class="admin-label">Altbilgi ek notu</label>
                        <textarea id="footer_extra_note" name="footer_extra_note" rows="2" class="admin-field mt-1" placeholder="Tescilli marka uyarısı, KVKK kısa metni vb.">{{ old('footer_extra_note', $footerExtraNote) }}</textarea>
                        @error('footer_extra_note')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </details>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="admin-btn-emerald-lg">Kaydet</button>
                <a href="{{ route('admin.dashboard') }}" class="admin-btn-outline">Panele dön</a>
                <a href="{{ route('landing.home') }}" target="_blank" rel="noopener" class="admin-btn-outline">Siteyi aç</a>
            </div>
        </form>
    </div>
</x-admin.layout>
