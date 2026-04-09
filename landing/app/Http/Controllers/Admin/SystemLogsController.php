<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\LogExplorerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemLogsController extends Controller
{
    public function __construct(
        private LogExplorerService $logs,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filtersFromRequest($request);
        $this->audit($request, 'view', $filters);

        $data = $this->logs->list($filters);

        return view('admin.system.logs', $data);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->filtersFromRequest($request);
        $this->audit($request, 'export_csv', $filters);

        $data = $this->logs->list($filters);
        $entries = array_slice((array) $data['entries'], 0, 5000);

        $filename = 'system-logs-'.date('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($entries): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['timestamp', 'level', 'source', 'file', 'site', 'message']);
            foreach ($entries as $e) {
                fputcsv($out, [
                    (string) ($e['timestamp'] ?? '-'),
                    (string) ($e['level'] ?? 'other'),
                    (string) ($e['source_label'] ?? ''),
                    (string) ($e['file_name'] ?? ''),
                    (string) ($e['site'] ?? 'genel'),
                    (string) ($e['message'] ?? ''),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @return array{tab:string,level:string,site:string,q:string,today:bool}
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'tab' => (string) $request->query('tab', 'all'),
            'level' => (string) $request->query('level', 'all'),
            'site' => (string) $request->query('site', 'all'),
            'q' => (string) $request->query('q', ''),
            'today' => $request->boolean('today'),
        ];
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function audit(Request $request, string $action, array $filters): void
    {
        Log::info('admin.system_logs.'.$action, [
            'user_id' => $request->user()?->getKey(),
            'ip' => $request->ip(),
            'filters' => $filters,
        ]);
    }
}
