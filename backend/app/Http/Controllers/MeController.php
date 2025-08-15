<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeController extends Controller
{
    public function credits(Request $req)
    {
        // adapte la source si tu stockes le solde ailleurs
        $row = DB::table('credits')->where('user_id', $req->user()->id ?? 1)->first();
        return response()->json([
            'balance_micro' => (int)($row->balance_micro ?? 0),
        ]);
    }
}
