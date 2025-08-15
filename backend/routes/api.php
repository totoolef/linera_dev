<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;
use Stripe\PaymentIntent;

use App\Http\Controllers\MeController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\OnchainTransfersController;
use App\Http\Controllers\AsaController;
use App\Http\Controllers\TokenController;

/*
|--------------------------------------------------------------------------
| API Routes (Laravel 11)
|--------------------------------------------------------------------------
| - /api/ping                    (public)
| - /api/stripe/checkout         (dev.token)
| - /api/stripe/webhook          (public)
| - /api/me/credits              (dev.token)
| - /api/payments                (dev.token)
| - /api/onchain-transfers       (dev.token)
| - /api/asa/transfer            (dev.token)
| - /api/token/ephemeral         (dev.token)
| - /api/token/_verify           (dev.token)  // debug vérif locale
*/

// Ping public
Route::get('/ping', fn () => response()->json(['message' => 'pong']));

/**
 * Stripe: créer une session Checkout (protégé dev.token)
 */
Route::middleware('dev.token')->post('/stripe/checkout', function (Request $request) {
    $amountEur = (float) $request->input('amount');
    if ($amountEur <= 0) {
        return response()->json(['error' => 'Montant invalide'], 422);
    }

    Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
    $userId = 1; // DEV

    $session = Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => env('STRIPE_CURRENCY', 'eur'),
                'product_data' => ['name' => 'Achat crédits Linera'],
                'unit_amount' => (int) round($amountEur * 100),
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'http://localhost:5173/payment-success',
        'cancel_url'  => 'http://localhost:5173/payment-cancel',
        'payment_intent_data' => [
            'metadata' => [
                'user_id'    => (string) $userId,
                'amount_eur' => (string) $amountEur,
            ]
        ],
        'metadata' => [
            'user_id'    => (string) $userId,
            'amount_eur' => (string) $amountEur,
        ],
    ]);

    return response()->json(['id' => $session->id, 'url' => $session->url]);
});

/**
 * Stripe: webhook (public)
 */
Route::post('/stripe/webhook', function (Request $request) {
    $payload   = $request->getContent();
    $sigHeader = $request->header('Stripe-Signature', '');
    $secret    = env('STRIPE_WEBHOOK_SECRET');

    try {
        $event = Webhook::constructEvent($payload, $sigHeader, $secret);
    } catch (\UnexpectedValueException) {
        return response('Invalid payload', 400);
    } catch (\Stripe\Exception\SignatureVerificationException) {
        return response('Invalid signature', 400);
    }

    $creditOnce = function (int $userId, float $amountEur, string $extId) {
        $ratio = (int) env('MICRO_CREDIT_RATIO', 100000);
        $amountMicro = (int) round($amountEur * $ratio);

        try {
            DB::table('payments')->insert([
                'user_id'      => $userId,
                'provider'     => 'stripe',
                'amount_eur'   => $amountEur,
                'amount_micro' => $amountMicro,
                'external_id'  => $extId,
                'status'       => 'completed',
                'created_at'   => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23505') {
                Log::info("Paiement déjà traité: {$extId}");
                return;
            }
            throw $e;
        }

        DB::table('credits')->updateOrInsert(
            ['user_id' => $userId],
            [
                'balance_micro' => DB::raw("COALESCE(balance_micro,0) + {$amountMicro}"),
                'updated_at'    => now(),
            ]
        );
        Log::info("Crédits +{$amountMicro} pour user {$userId} (ext={$extId})");
    };

    switch ($event->type) {
        case 'payment_intent.succeeded':
            $pi       = $event->data->object;
            $userId   = isset($pi->metadata->user_id) ? (int) $pi->metadata->user_id : null;
            $amountEur= isset($pi->amount_received) ? $pi->amount_received / 100.0 : null;
            if ($userId && $amountEur !== null) {
                $creditOnce($userId, $amountEur, $pi->id);
            }
            break;

        case 'checkout.session.completed':
            $sess = $event->data->object;
            $paymentIntentId = $sess->payment_intent ?? null;
            if (!$paymentIntentId) break;

            try {
                Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
                $pi = PaymentIntent::retrieve($paymentIntentId);
            } catch (\Throwable $e) {
                Log::error("PI fetch fail: {$paymentIntentId} - ".$e->getMessage());
                break;
            }

            $userId    = isset($pi->metadata->user_id) ? (int) $pi->metadata->user_id : null;
            $amountEur = isset($pi->amount_received) ? $pi->amount_received / 100.0 : null;
            if ($userId && $amountEur !== null) {
                $creditOnce($userId, $amountEur, $pi->id);
            }
            break;

        default:
            Log::debug("Webhook ignoré: {$event->type}");
    }

    return response('Webhook handled', 200);
});

/**
 * Routes protégées par dev.token
 */
Route::middleware('dev.token')->group(function () {
    // Dashboard
    Route::get('/me/credits', [MeController::class, 'credits']);
    Route::get('/payments', [PaymentsController::class, 'index']);
    Route::get('/onchain-transfers', [OnchainTransfersController::class, 'index']);
    Route::post('/asa/transfer', [AsaController::class, 'transferToUser']);

    // C1 — JWT éphémère
    Route::post('/token/ephemeral', [TokenController::class, 'issue']);
    Route::post('/token/_verify',   [TokenController::class, 'verifyLocal']); // debug
});

Route::middleware('ephemeral.jwt')->group(function () {
    Route::post('/provider/openai/chat', function (Request $req) {
        return response()->json([
            'ok'  => true,
            'jwt' => $req->attributes->get('ephemeral_jwt'),
            'echo'=> json_decode($req->getContent() ?: '""', true),
        ]);
    });
});

