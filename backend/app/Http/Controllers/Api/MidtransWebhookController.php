<?php
namespace App\Http\Controllers\Api;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use RuntimeException;
class MidtransWebhookController
{
    public function handle(Request $request, MidtransService $midtrans) { try { $order=$midtrans->processNotification($request->all()); return response()->json(['success'=>true,'order_id'=>$order->order_id]); } catch (RuntimeException $e) { return response()->json(['success'=>false,'message'=>$e->getMessage()],403); } }
}
