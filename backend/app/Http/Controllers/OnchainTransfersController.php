<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnchainTransfersController extends Controller {
    public function index(Request $req) {
        $limit = min((int)$req->query('limit', 20), 100);
        $cursor = $req->query('cursor');
        $q = DB::table('onchain_transfers')
            ->where('user_id', $req->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($cursor) {
            [$createdAt, $id] = explode('|', $cursor);
            $q->where(function($sub) use ($createdAt, $id) {
                $sub->where('created_at', '<', $createdAt)
                    ->orWhere(function($q2) use ($createdAt, $id) {
                        $q2->where('created_at', '=', $createdAt)->where('id', '<', $id);
                    });
            });
        }

        $rows = $q->limit($limit+1)->get();
        $next = null;
        if ($rows->count() > $limit) {
            $last = $rows[$limit-1];
            $next = $last->created_at.'|'.$last->id;
            $rows = $rows->slice(0, $limit)->values();
        }
        return response()->json(['data' => $rows, 'next' => $next]);
    }
}
