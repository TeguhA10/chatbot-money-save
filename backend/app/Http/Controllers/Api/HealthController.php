<?php
namespace App\Http\Controllers\Api;
class HealthController { public function live() { return response()->json(['status'=>'ok']); } public function ready() { return response()->json(['status'=>'ready']); } }
