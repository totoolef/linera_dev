<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AlgorandService;

class AsaController extends Controller
{
    public function __construct(private AlgorandService $algorand) {}

    public function transferToUser(Request $req)
    {
        $data = $req->validate([
            'user_id' => 'required|integer|exists:users,id',
            'amount_micro' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        $settings = DB::table('algorand_settings')->first();
        if (!$settings?->asa_id || !$settings?->bank_address) {
            return response()->json(['error' => 'Algorand not configured'], 400);
        }

        $wallet = DB::table('wallets')->where('user_id', $data['user_id'])->first();
        if (!$wallet?->address) {
            return response()->json(['error' => 'User wallet not found'], 404);
        }

        // Journal pending
        $transferId = DB::table('onchain_transfers')->insertGetId([
            'user_id' => $data['user_id'],
            'direction' => 'in', // banque -> user
            'amount_units' => $data['amount_micro'],
            'asa_id' => $settings->asa_id,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $tx = $this->algorand->transfer($wallet->address, (int)$settings->asa_id, (int)$data['amount_micro']);

            DB::table('onchain_transfers')->where('id', $transferId)->update([
                'tx_id' => $tx['txId'] ?? null,
                'status' => 'confirmed', // simple: confirmé à l’envoi (option: job de réconciliation)
                'updated_at' => now(),
            ]);

            return response()->json([
                'transfer_id' => $transferId,
                'tx_id' => $tx['txId'] ?? null
            ]);
        } catch (\Throwable $e) {
            DB::table('onchain_transfers')->where('id', $transferId)->update([
                'status' => 'failed',
                'updated_at' => now(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
