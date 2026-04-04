<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CmsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $kind = $request->input('kind');
        if (! is_string($kind) || ! in_array($kind, CmsItem::kinds(), true)) {
            return response()->json(['message' => 'Invalid kind'], 422);
        }

        $q = CmsItem::query()->where('kind', $kind)->orderBy('slug')->orderBy('locale');
        if ($request->filled('locale')) {
            $q->where('locale', $request->string('locale'));
        }

        return response()->json(['pages' => $q->get()]);
    }

    /**
     * GET /admin/cms/{kind} — plan ile uyumlu alias (query parametresi olmadan).
     */
    public function indexByKind(Request $request, string $kind): JsonResponse
    {
        $request->merge(['kind' => $kind]);

        return $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, null);
        $publish = $request->boolean('publish');
        $payload = $this->withPublishState($validated, $publish, null);

        $page = CmsItem::create($payload);

        return response()->json([
            'message' => __('messages.success'),
            'page' => $page,
        ], 201);
    }

    public function update(Request $request, CmsItem $cmsItem): JsonResponse
    {
        $validated = $this->validatePayload($request, $cmsItem);
        $publish = $request->boolean('publish');
        $payload = $this->withPublishState($validated, $publish, $cmsItem);

        $cmsItem->update($payload);

        return response()->json([
            'message' => __('messages.success'),
            'page' => $cmsItem->fresh(),
        ]);
    }

    public function destroy(CmsItem $cmsItem): JsonResponse
    {
        $cmsItem->delete();

        return response()->json(['message' => __('messages.success')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?CmsItem $existing): array
    {
        $creating = $existing === null;
        $kind = $creating ? (string) $request->input('kind') : $existing->kind;

        $localeRules = $creating
            ? ['required', 'string', 'max:16']
            : ['sometimes', 'string', 'max:16'];

        $rules = [
            'locale' => $localeRules,
            'title' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'body_markdown' => ['nullable', 'string'],
            'featured' => ['sometimes', 'boolean'],
            'section' => ['nullable', 'string', 'max:128'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'publish' => ['sometimes', 'boolean'],
        ];

        if ($creating) {
            $rules['kind'] = ['required', Rule::in(CmsItem::kinds())];
        }

        if (in_array($kind, [CmsItem::KIND_LANDING, CmsItem::KIND_INSTALL], true)) {
            $rules['slug'] = ['prohibited'];
        } else {
            $uniqueSlug = Rule::unique('cms_items', 'slug')
                ->where('kind', $kind)
                ->where('locale', (string) $request->input('locale', $existing?->locale ?? ''));
            if (! $creating) {
                $uniqueSlug = $uniqueSlug->ignore($existing->id);
            }
            $rules['slug'] = array_filter([
                $creating ? 'required' : 'sometimes',
                'string',
                'max:255',
                $uniqueSlug,
            ]);
        }

        $validated = $request->validate($rules);

        if (in_array($kind, [CmsItem::KIND_LANDING, CmsItem::KIND_INSTALL], true)) {
            $validated['slug'] = '';
        }

        $validated['kind'] = $kind;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withPublishState(array $data, bool $publish, ?CmsItem $existing): array
    {
        unset($data['publish']);

        if ($publish) {
            $data['status'] = 'published';
            $data['published_at'] = $existing?->published_at ?? now();
            $data['is_published'] = true;
        } else {
            $data['status'] = 'draft';
            $data['published_at'] = null;
            $data['is_published'] = false;
        }

        return $data;
    }
}
