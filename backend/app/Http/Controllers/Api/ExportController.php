<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\ExcelExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use Illuminate\Support\Facades\Cache;

/**
 * ExportController
 *
 * Handles binary spreadsheet downloads (.xlsx) for authenticated clients
 * or WhatsApp direct document download links via short-lived signed routes.
 */
class ExportController extends Controller
{
    private ExcelExportService $exportService;

    public function __construct(ExcelExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * GET /api/v1/export/excel/{export_id}
     *
     * Streams the generated multi-sheet Excel spreadsheet using a single-use signed ticket.
     * Uses atomic claim via Cache::pull() to prevent concurrent replays.
     */
    public function excelSigned(Request $request, string $export_id): BinaryFileResponse|JsonResponse
    {
        // Atomic pull: retrieves and removes the ticket in one atomic operation
        $ticket = Cache::pull("export:{$export_id}");

        if (!$ticket || !is_array($ticket) || ($ticket['purpose'] ?? '') !== 'whatsapp_excel') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Export ticket expired, invalid, or already consumed.',
            ], 410);
        }

        $user = User::where('jid', $ticket['user_jid'])->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        $year = isset($ticket['year']) && is_numeric($ticket['year']) ? (int) $ticket['year'] : null;

        return $this->exportService->download($user, $year);
    }

    /**
     * GET /api/v1/export/excel
     *
     * Streams the generated multi-sheet Excel spreadsheet directly to authenticated clients.
     */
    public function excel(Request $request): BinaryFileResponse|JsonResponse
    {
        // 1. Resolve User from Sanctum
        $user = $request->user('sanctum') ?? $request->user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pengguna tidak terautentikasi.',
            ], 401);
        }

        // 2. Validate optional year
        $year = $request->has('year') ? (int) $request->query('year') : null;
        if ($year !== null && ($year < 2000 || $year > 2100)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Parameter tahun tidak valid.',
            ], 422);
        }

        // 3. Stream Excel download
        return $this->exportService->download($user, $year);
    }
}
