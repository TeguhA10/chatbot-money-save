<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\ExcelExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ExportController
 *
 * Handles binary spreadsheet downloads (.xlsx) for authenticated clients
 * or WhatsApp direct document download links.
 */
class ExportController extends Controller
{
    private ExcelExportService $exportService;

    public function __construct(ExcelExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * GET /api/v1/export/excel
     *
     * Streams the generated multi-sheet Excel spreadsheet directly to the client.
     */
    public function excel(Request $request): BinaryFileResponse|JsonResponse
    {
        // 1. Resolve User from Sanctum or user_jid query parameter
        $user = $request->user('sanctum') ?? $request->user();

        if (!$user && $request->has('user_jid')) {
            $user = User::where('jid', $request->query('user_jid'))->first();
        }

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pengguna tidak terautentikasi atau JID tidak ditemukan.',
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
