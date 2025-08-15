<?php

namespace App\Http\Controllers;

use App\Http\Requests\{HoldRequest, CaptureRequest};
use App\Services\Credits\HoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditsController extends Controller
{
    public function __construct(private HoldService $svc) {}

    private function subjectFrom(Request $req): array
    {
        /** @var array<string,mixed>|null $claims */
        $claims = $req->attributes->get('ephemeral_jwt');

        $sub = $claims['sub'] ?? null;

        // Si ton sub = users.id → on passe en $userId directement
        if (ctype_digit((string)$sub)) {
            return [(int)$sub, null]; // userId, sub=null
        }

        // Sinon, on passe le sub tel quel au service
        return [null, (string)$sub];
    }

    /** POST /api/credits/hold */
    public function hold(HoldRequest $req): JsonResponse
    {
        [$userId, $sub] = $this->subjectFrom($req);

        $hold = $this->svc->createHold(
            userId:         $userId,
            sub:            $sub,
            amountMicro:    (int)$req->integer('amount_micro'),
            idempotencyKey: (string)$req->string('idempotencyKey'),
            meta:           $req->input('metadata'),
            providerRef:    $req->input('provider_ref'),
            ttlSeconds:     (int)$req->input('ttl_seconds', 900)
        );

        return response()->json([
            'id'             => $hold->id,
            'status'         => $hold->status,
            'amount_micro'   => $hold->amount_micro,
            'captured_micro' => $hold->captured_micro,
            'expires_at'     => $hold->expires_at?->toIso8601String(),
        ], 201, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** POST /api/credits/capture */
    public function capture(CaptureRequest $req): JsonResponse
    {
        [$userId, $sub] = $this->subjectFrom($req);

        $hold = $this->svc->capture(
            userId:           $userId,
            sub:              $sub,
            holdId:           (int)$req->integer('hold_id'),
            actualCostMicro:  (int)$req->integer('actual_cost_micro'),
            captureKey:       (string)$req->string('captureKey')
        );

        return response()->json([
            'id'             => $hold->id,
            'status'         => $hold->status,
            'amount_micro'   => $hold->amount_micro,
            'captured_micro' => $hold->captured_micro,
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
