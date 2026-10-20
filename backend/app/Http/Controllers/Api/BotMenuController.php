<?php

namespace App\Http\Controllers\Api;

use App\Models\BotMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BotMenuController extends Controller
{
    /**
     * Get all active bot menus grouped by category, plus quick commands.
     */
    public function index(): JsonResponse
    {
        $allMenus = BotMenu::active()->get();

        $grouped = $allMenus->groupBy('category')->map(function ($items, $category) {
            return [
                'category'      => $category,
                'category_icon' => $items->first()->category_icon ?? '📌',
                'items'         => $items->map(fn($item) => [
                    'id'          => $item->id,
                    'command'     => $item->command,
                    'description' => $item->description,
                ])->values(),
            ];
        })->values();

        $quickCommands = $allMenus->where('is_quick_command', true)->values()->map(fn($item) => [
            'id'          => $item->id,
            'category'    => $item->category,
            'command'     => $item->command,
            'description' => $item->description,
        ]);

        return response()->json([
            'success'        => true,
            'grouped'        => $grouped,
            'quick_commands' => $quickCommands,
            'all'            => $allMenus,
        ]);
    }
}
