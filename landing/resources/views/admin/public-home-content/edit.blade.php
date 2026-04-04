<x-admin.layout title="Ön yüz — ana sayfa içeriği">
    <div class="mx-auto max-w-4xl space-y-10">
        <p class="admin-muted">
            Alanları doldurduğunuzda çeviri dosyası ve veritabanı çevirilerinin üzerine yazılır; boş bıraktığınız alanlar mevcut çeviriye döner.
            Özellik kartları ve kahraman görseli yalnızca buradan yönetilir.
        </p>

        <form method="POST" action="{{ route('admin.public-home-content.update') }}" enctype="multipart/form-data" class="space-y-10">
            @csrf
            @method('PUT')

            <div class="admin-card">
                <h2 class="admin-label-block text-base">Kahraman görseli</h2>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">PNG / JPEG / WebP, en fazla 5 MB. Görsel, ana sayfa sağ sütununda mock kartın üstünde gösterilir.</p>
                @if ($heroImageUrl)
                    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                        <img src="{{ $heroImageUrl }}" alt="" class="max-h-48 w-full object-cover">
                    </div>
                    <label class="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                        <input type="checkbox" name="remove_hero_image" value="1" class="admin-checkbox">
                        Görseli kaldır
                    </label>
                @endif
                <input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp" class="admin-file">
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="admin-label" for="hero_image_alt">Alt metin (erişilebilirlik)</label>
                        <input id="hero_image_alt" type="text" name="hero_image_alt" value="{{ old('hero_image_alt', $heroImageAlt) }}" class="admin-field mt-1">
                    </div>
                    <div>
                        <label class="admin-label" for="hero_image_caption">Görsel altı açıklama (isteğe bağlı)</label>
                        <input id="hero_image_caption" type="text" name="hero_image_caption" value="{{ old('hero_image_caption', $heroImageCaption) }}" class="admin-field mt-1">
                    </div>
                </div>
            </div>

            @foreach ($groups as $groupTitle => $labels)
                <div class="admin-card">
                    <h2 class="admin-label-block capitalize text-base">{{ $groupTitle }}</h2>
                    <div class="mt-4 space-y-4">
                        @foreach ($labels as $key => $label)
                            <div>
                                <label class="admin-label" for="c_{{ md5($key) }}">{{ $label }} <span class="font-mono text-[10px] text-slate-500 dark:text-slate-600">({{ $key }})</span></label>
                                <textarea id="c_{{ md5($key) }}" name="content[{{ $key }}]" rows="{{ str_contains($key, 'lead') || str_contains($key, 'title') ? 3 : 2 }}" class="admin-field mt-1" placeholder="Boş = çeviri dosyası / çeviri kaydı kullanılır">{{ old('content.'.$key, $overrides[$key] ?? '') }}</textarea>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="admin-card">
                <h2 class="admin-label-block text-base">Özellik kartları</h2>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">Başlık ve gövde dolu olan satırlar listelenir. İkon, temadaki hazır SVG setinden seçilir.</p>
                <div class="mt-4 space-y-6">
                    @foreach ($featureCards as $i => $card)
                        <div class="admin-inner">
                            <div class="text-xs font-medium text-slate-600 dark:text-slate-500">Kart {{ $i + 1 }}</div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="admin-label">Başlık</label>
                                    <input type="text" name="feature_cards[{{ $i }}][title]" value="{{ old('feature_cards.'.$i.'.title', $card['title'] ?? '') }}" class="admin-field mt-1 rounded-lg">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="admin-label">Açıklama</label>
                                    <textarea name="feature_cards[{{ $i }}][body]" rows="2" class="admin-field mt-1 rounded-lg">{{ old('feature_cards.'.$i.'.body', $card['body'] ?? '') }}</textarea>
                                </div>
                                <div>
                                    <label class="admin-label">İkon</label>
                                    <select name="feature_cards[{{ $i }}][icon]" class="admin-field mt-1 rounded-lg">
                                        @foreach ($icons as $iconKey => $iconLabel)
                                            <option value="{{ $iconKey }}" @selected(old('feature_cards.'.$i.'.icon', $card['icon'] ?? 'layers') === $iconKey)>{{ $iconLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @for ($j = count($featureCards); $j < 8; $j++)
                        <div class="admin-inner-dashed">
                            <div class="text-xs font-medium text-slate-600 dark:text-slate-500">Yeni kart</div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <input type="text" name="feature_cards[{{ $j }}][title]" placeholder="Başlık" class="admin-field rounded-lg">
                                </div>
                                <div class="sm:col-span-2">
                                    <textarea name="feature_cards[{{ $j }}][body]" rows="2" placeholder="Açıklama" class="admin-field rounded-lg"></textarea>
                                </div>
                                <div>
                                    <select name="feature_cards[{{ $j }}][icon]" class="admin-field rounded-lg">
                                        @foreach ($icons as $iconKey => $iconLabel)
                                            <option value="{{ $iconKey }}">{{ $iconLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="rounded-full bg-orange-500 px-6 py-2.5 text-sm font-semibold text-white hover:bg-orange-600">Kaydet</button>
                <a href="{{ route('landing.home') }}" target="_blank" rel="noopener" class="admin-btn-outline items-center px-6 py-2.5">Ön izleme</a>
            </div>
        </form>
    </div>
</x-admin.layout>
