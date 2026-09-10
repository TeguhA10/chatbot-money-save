<?php
namespace App\Http\Controllers\Api;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
class SubscriptionController
{
    public function status(Request $request, SubscriptionService $subscriptions) { $user=User::findOrFail($request->user_jid ?? $request->user()->jid); return response()->json($subscriptions->status($user)); }
    public function purchase(Request $request, MidtransService $midtrans) { $data=$request->validate(['user_jid'=>'required|string']); return response()->json(['order'=>$midtrans->createOrder(User::findOrFail($data['user_jid']))],201); }
}
