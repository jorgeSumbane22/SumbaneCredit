<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\Documents\PdfService;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly PdfService $pdf,
        private readonly AuditService $audit,
    ) {}

    public function index(): View
    {
        return view('admin.reports.index', ['types' => ReportService::TYPES]);
    }

    public function show(Request $request, string $type): View
    {
        $report = $this->reports->present($this->reports->build($type, $request));

        return view('admin.reports.show', [
            'report' => $report,
            'type' => $type,
            'preset' => $request->query('periodo', $type === 'annual' ? 'custom' : 'this_month'),
        ]);
    }

    public function pdf(Request $request, string $type): Response
    {
        $report = $this->reports->present($this->reports->build($type, $request));
        $this->audit->log('report.exported', null, null, ['type' => $type, 'format' => 'pdf', 'filters' => $request->query()]);

        return $this->pdf->report($report);
    }

    public function excel(Request $request, string $type): BinaryFileResponse
    {
        return $this->reports->excel($type, $request);
    }
}
