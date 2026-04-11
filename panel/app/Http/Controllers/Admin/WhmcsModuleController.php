<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class WhmcsModuleController extends Controller
{
    /**
     * WHMCS sunucu modülü (hostvim/) zip indirimi — önce kaynak klasör, yoksa paketlenmiş dosya.
     */
    public function downloadModuleZip(Request $request): BinaryFileResponse
    {
        $configuredSource = trim((string) config('hostvim.whmcs_module_source_dir', ''));
        $source = $configuredSource !== '' ? $configuredSource : base_path('../integrations/whmcs/modules/servers/hostvim');
        $source = $source !== '' && is_dir($source) ? realpath($source) : false;

        if ($source !== false) {
            return $this->zipFromDirectory($source);
        }

        $configuredZip = trim((string) config('hostvim.whmcs_module_prebuilt_zip', ''));
        $prebuilt = $configuredZip !== '' ? $configuredZip : storage_path('app/whmcs/hostvim-whmcs-module.zip');
        if (! is_file($prebuilt)) {
            abort(503, (string) __('whmcs_integration.zip_missing'));
        }

        return response()->download($prebuilt, 'hostvim-whmcs-module.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    private function zipFromDirectory(string $absoluteSourceDir): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hvwhmcs');
        if ($tmp === false) {
            abort(500, (string) __('whmcs_integration.zip_failed'));
        }
        $zipPath = $tmp.'.zip';
        @unlink($tmp);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, (string) __('whmcs_integration.zip_failed'));
        }

        $absoluteSourceDir = rtrim($absoluteSourceDir, DIRECTORY_SEPARATOR);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absoluteSourceDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $full = $file->getPathname();
            $rel = substr($full, strlen($absoluteSourceDir) + 1);
            $zip->addFile($full, 'hostvim/'.str_replace('\\', '/', $rel));
        }
        $zip->close();

        return response()->download($zipPath, 'hostvim-whmcs-module.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}
