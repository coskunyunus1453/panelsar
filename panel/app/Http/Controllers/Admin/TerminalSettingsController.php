<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PanelSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TerminalSettingsController extends Controller
{
    private const KEY = 'security.terminal_root';

    public function show(): JsonResponse
    {
        return response()->json([
            'use_root' => $this->readUseRoot(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'use_root' => 'required|boolean',
        ]);

        if (Schema::hasTable('panel_settings')) {
            PanelSetting::query()->updateOrCreate(
                ['key' => self::KEY],
                ['value' => $validated['use_root'] ? '1' : '0']
            );
        }

        return response()->json([
            'use_root' => (bool) $validated['use_root'],
        ]);
    }

    private function readUseRoot(): bool
    {
        if (! Schema::hasTable('panel_settings')) {
            return true;
        }
        $v = PanelSetting::query()->where('key', self::KEY)->value('value');
        if ($v === null) {
            return true;
        }

        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }
}
