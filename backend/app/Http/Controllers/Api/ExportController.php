<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\ExcelExportService;
use App\Services\PdfExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ExportController
 *
 * Handles both Excel spreadsheet (.xlsx) and Official Bank-Grade Statement (.pdf)
 * downloads for authenticated web clients and WhatsApp direct single-use signed routes.
 */
class ExportController extends Controller
{
    public function __construct(
        private readonly ExcelExportService $excelService,
        private readonly PdfExportService $pdfService,
    ) {}

    /**
     * Helper to extract common query filters from request.
     */
    private function extractFilters(Request $request): array
    {
        $filters = [];
        if ($request->filled('start_date'))  $filters['start_date']  = $request->query('start_date');
        if ($request->filled('end_date'))    $filters['end_date']    = $request->query('end_date');
        if ($request->filled('wallet_id'))   $filters['wallet_id']   = $request->query('wallet_id');
        if ($request->filled('type'))        $filters['type']        = $request->query('type');
        if ($request->filled('month'))       $filters['month']       = $request->query('month');
        if ($request->filled('year'))        $filters['year']        = $request->query('year');

        return $filters;
    }

    /**
     * GET /api/v1/export/excel/{export_id}
     *
     * Streams the generated multi-sheet Excel spreadsheet using a single-use signed ticket.
     */
    public function excelSigned(Request $request, string $export_id): BinaryFileResponse|JsonResponse
    {
        $ticket = Cache::pull("export:{$export_id}");

        if (!$ticket || !is_array($ticket) || ($ticket['purpose'] ?? '') !== 'whatsapp_excel') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tiket unduh telah kedaluwarsa atau sudah pernah digunakan.',
            ], 410);
        }

        $user = User::where('jid', $ticket['user_jid'])->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        $filters = $ticket['filters'] ?? [];
        $year    = isset($ticket['year']) && is_numeric($ticket['year']) ? (int) $ticket['year'] : null;

        return $this->excelService->download($user, $year, $filters);
    }

    /**
     * GET /api/v1/export/excel
     *
     * Streams the generated Excel spreadsheet directly to authenticated clients with filters.
     */
    public function excel(Request $request): BinaryFileResponse|JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pengguna tidak terautentikasi.',
            ], 401);
        }

        $filters = $this->extractFilters($request);
        $year = $request->has('year') ? (int) $request->query('year') : null;

        return $this->excelService->download($user, $year, $filters);
    }

    /**
     * GET /api/v1/export/pdf/{export_id}
     *
     * Streams the official Bank-grade Statement PDF using a single-use signed ticket for WhatsApp.
     */
    public function pdfSigned(Request $request, string $export_id): Response|JsonResponse
    {
        $ticket = Cache::pull("export:{$export_id}");

        if (!$ticket || !is_array($ticket) || ($ticket['purpose'] ?? '') !== 'whatsapp_pdf') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tiket unduh telah kedaluwarsa atau sudah pernah digunakan.',
            ], 410);
        }

        $user = User::where('jid', $ticket['user_jid'])->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        $filters = $ticket['filters'] ?? [];

        return $this->pdfService->download($user, $filters);
    }

    /**
     * GET /api/v1/export/pdf
     *
     * Streams the official Bank-grade Statement PDF directly to authenticated clients with filters.
     */
    public function pdf(Request $request): Response|JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pengguna tidak terautentikasi.',
            ], 401);
        }

        $filters = $this->extractFilters($request);

        return $this->pdfService->download($user, $filters);
    }
}
