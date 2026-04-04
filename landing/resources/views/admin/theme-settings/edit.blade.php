@php
    $top = old('theme_neon_top', $neonTop);
    $stackSec = old('theme_neon_stack_section', $neonStackSection);
    $stackRows = old('theme_neon_stack', $neonStackItems);
    $gridSec = old('theme_neon_grid_section', $neonGridSection);
    $gridRows = old('theme_neon_grid', $neonGridItems);
@endphp
<x-admin.layout title="Tema & görünüm">
    <div class="mx-auto max-w-3xl space-y-8">
        <p class="admin-muted">
            Ön yüz için tema seçin, arka plan grafik desenini belirleyin ve isteğe bağlı ana marka rengini (hex) tanımlayın.
            <strong>Tema 3</strong> için aşağıdaki metinleri düzenleyin; yalnızca Tema 3 aktifken ana sayfada kullanılır.
        </p>

        <form method="POST" action="{{ route('admin.theme-settings.update') }}" class="space-y-8">
            @csrf
            @method('PUT')

            <div class="admin-card">
                <h2 class="admin-label-block text-base">Aktif tema</h2>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">Tema 3: neon/material, ayrı üst-alt çerçeve ve özel ana sayfa yerleşimi.</p>
                <div class="mt-4 space-y-3">
                    @foreach ($themes as $key => $meta)
                        <label class="admin-radio-tile">
                            <input type="radio" name="active_theme" value="{{ $key }}" class="mt-1" @checked($activeTheme === $key)>
                            <div>
                                <div class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $meta['label'] ?? $key }}</div>
                                <div class="mt-0.5 text-xs text-slate-600 dark:text-slate-500">{{ $meta['description'] ?? '' }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="admin-card">
                <h2 class="admin-label-block text-base">Arka plan grafiği</h2>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">Tema 1 ve 2 için köşe desenleri. Tema 3 kendi arka planını kullanır.</p>
                <select name="graphic_motif" class="admin-field mt-4">
                    @foreach ($motifs as $key => $label)
                        <option value="{{ $key }}" @selected($graphicMotif === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="admin-card">
                <h2 class="admin-label-block text-base">Ana renk (isteğe bağlı)</h2>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">Tüm temalarda vurgu tonları; boş bırakırsanız tema varsayılanı.</p>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <input type="color" id="theme_primary_hex_picker" value="{{ strlen((string) $primaryHex) === 7 ? $primaryHex : '#22d3ee' }}" class="h-10 w-16 cursor-pointer rounded-lg border border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900">
                    <input type="text" name="theme_primary_hex" id="theme_primary_hex" value="{{ old('theme_primary_hex', $primaryHex) }}" placeholder="#RRGGBB" maxlength="7" pattern="#[0-9A-Fa-f]{6}" class="admin-field min-w-[10rem] flex-1">
                    <button type="button" id="clear_hex" class="admin-btn-outline px-3 py-2 text-xs">Temizle</button>
                </div>
            </div>

            <div class="admin-card space-y-6">
                <div>
                    <h2 class="admin-label-block text-base">Tema 3 — Üst tanıtım alanı</h2>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">Başlık, lead ve çağrı metinleri. Yanında mobil uyumlu soyut grafik gösterilir.</p>
                </div>
                <div>
                    <label class="admin-label" for="neon_badge">Rozet</label>
                    <input id="neon_badge" type="text" name="theme_neon_top[badge]" value="{{ $top['badge'] ?? '' }}" class="admin-field mt-1">
                </div>
                <div>
                    <label class="admin-label" for="neon_title">Ana başlık</label>
                    <input id="neon_title" type="text" name="theme_neon_top[title]" value="{{ $top['title'] ?? '' }}" class="admin-field mt-1">
                </div>
                <div>
                    <label class="admin-label" for="neon_lead">Tanıtım metni</label>
                    <textarea id="neon_lead" name="theme_neon_top[lead]" rows="3" class="admin-field mt-1">{{ $top['lead'] ?? '' }}</textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="admin-label" for="neon_cta1">Birincil düğme</label>
                        <input id="neon_cta1" type="text" name="theme_neon_top[cta_primary]" value="{{ $top['cta_primary'] ?? '' }}" class="admin-field mt-1" placeholder="Panele git">
                    </div>
                    <div>
                        <label class="admin-label" for="neon_cta2">İkincil düğme</label>
                        <input id="neon_cta2" type="text" name="theme_neon_top[cta_secondary]" value="{{ $top['cta_secondary'] ?? '' }}" class="admin-field mt-1" placeholder="Kurulum">
                    </div>
                </div>
            </div>

            <div class="admin-card space-y-6">
                <div>
                    <h2 class="admin-label-block text-base">Tema 3 — Orta bölüm (5 madde, alt alta)</h2>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">Bölüm başlığı + beş özellik satırı.</p>
                </div>
                <div>
                    <label class="admin-label">Bölüm başlığı</label>
                    <input type="text" name="theme_neon_stack_section[title]" value="{{ $stackSec['title'] ?? '' }}" class="admin-field mt-1">
                </div>
                <div>
                    <label class="admin-label">Bölüm alt metni</label>
                    <textarea name="theme_neon_stack_section[lead]" rows="2" class="admin-field mt-1">{{ $stackSec['lead'] ?? '' }}</textarea>
                </div>
                @foreach ($stackRows as $idx => $row)
                    <div class="rounded-xl border border-slate-200/90 p-4 dark:border-slate-700">
                        <p class="mb-3 text-xs font-semibold text-slate-500">Madde {{ $idx + 1 }} / 5</p>
                        <div class="space-y-3">
                            <div>
                                <label class="admin-label text-xs">Başlık</label>
                                <input type="text" name="theme_neon_stack[{{ $idx }}][title]" value="{{ $row['title'] ?? '' }}" class="admin-field mt-1">
                            </div>
                            <div>
                                <label class="admin-label text-xs">Açıklama</label>
                                <textarea name="theme_neon_stack[{{ $idx }}][body]" rows="2" class="admin-field mt-1">{{ $row['body'] ?? '' }}</textarea>
                            </div>
                            <div>
                                <label class="admin-label text-xs">İkon</label>
                                <select name="theme_neon_stack[{{ $idx }}][icon]" class="admin-field mt-1">
                                    @foreach ($featureIcons as $ikey => $ilabel)
                                        <option value="{{ $ikey }}" @selected(($row['icon'] ?? 'layers') === $ikey)>{{ $ilabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="admin-card space-y-6">
                <div>
                    <h2 class="admin-label-block text-base">Tema 3 — Alt ızgara (6 madde, 3×2)</h2>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">İkinci özellik bloğu; mobilde tek sütun.</p>
                </div>
                <div>
                    <label class="admin-label">Bölüm başlığı</label>
                    <input type="text" name="theme_neon_grid_section[title]" value="{{ $gridSec['title'] ?? '' }}" class="admin-field mt-1">
                </div>
                <div>
                    <label class="admin-label">Bölüm alt metni</label>
                    <textarea name="theme_neon_grid_section[lead]" rows="2" class="admin-field mt-1">{{ $gridSec['lead'] ?? '' }}</textarea>
                </div>
                @foreach ($gridRows as $idx => $row)
                    <div class="rounded-xl border border-slate-200/90 p-4 dark:border-slate-700">
                        <p class="mb-3 text-xs font-semibold text-slate-500">Kutu {{ $idx + 1 }} / 6</p>
                        <div class="space-y-3">
                            <div>
                                <label class="admin-label text-xs">Başlık</label>
                                <input type="text" name="theme_neon_grid[{{ $idx }}][title]" value="{{ $row['title'] ?? '' }}" class="admin-field mt-1">
                            </div>
                            <div>
                                <label class="admin-label text-xs">Açıklama</label>
                                <textarea name="theme_neon_grid[{{ $idx }}][body]" rows="2" class="admin-field mt-1">{{ $row['body'] ?? '' }}</textarea>
                            </div>
                            <div>
                                <label class="admin-label text-xs">İkon</label>
                                <select name="theme_neon_grid[{{ $idx }}][icon]" class="admin-field mt-1">
                                    @foreach ($featureIcons as $ikey => $ilabel)
                                        <option value="{{ $ikey }}" @selected(($row['icon'] ?? 'layers') === $ikey)>{{ $ilabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-full bg-orange-500 px-6 py-2.5 text-sm font-semibold text-white hover:bg-orange-600">Kaydet</button>
                <a href="{{ route('landing.home') }}" target="_blank" rel="noopener" class="admin-btn-outline items-center px-6 py-2.5">Siteyi aç</a>
            </div>
        </form>
    </div>

    <script>
        (function () {
            var p = document.getElementById('theme_primary_hex_picker');
            var t = document.getElementById('theme_primary_hex');
            var c = document.getElementById('clear_hex');
            if (p && t) {
                p.addEventListener('input', function () { t.value = p.value; });
                t.addEventListener('input', function () {
                    if (/^#[0-9A-Fa-f]{6}$/.test(t.value)) p.value = t.value;
                });
            }
            if (c && t && p) {
                c.addEventListener('click', function () { t.value = ''; p.value = '#22d3ee'; });
            }
        })();
    </script>
</x-admin.layout>
