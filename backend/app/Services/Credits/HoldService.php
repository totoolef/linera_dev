<?php
// app/Services/Credits/HoldService.php
namespace App\Services\Credits;

use App\Models\{User,CreditHold,CreditTransaction};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class HoldService
{
  private function resolveUser(?int $userId, ?string $sub): User
  {
    if ($userId) {
      return User::lockForUpdate()->findOrFail($userId);
    }
    // si tu as ajouté users.subject
    if ($sub !== null && schema()->hasColumn('users','subject')) {
      $u = User::lockForUpdate()->where('subject',$sub)->first();
      if ($u) return $u;
      // possibilité de créer à la volée si tu veux:
      // return User::create(['subject'=>$sub, 'name'=>$sub, 'email'=>$sub.'@local', 'password'=>'']);
    }
    // sinon on tente sub numérique = users.id
    if (ctype_digit((string)$sub)) {
      return User::lockForUpdate()->findOrFail((int)$sub);
    }
    throw ValidationException::withMessages(['subject'=>'USER_NOT_RESOLVED']);
  }

  public function createHold(?int $userId, ?string $sub, int $amountMicro, string $idempotencyKey, ?array $meta, ?string $providerRef, int $ttlSeconds = 900): CreditHold
  {
    return DB::transaction(function () use ($userId,$sub,$amountMicro,$idempotencyKey,$meta,$providerRef,$ttlSeconds) {
      $user = $this->resolveUser($userId, $sub);

      if ($existing = CreditHold::where('idempotency_key',$idempotencyKey)->lockForUpdate()->first()) {
        return $existing;
      }

      $available = (int) max(0, $user->balance_micro - $user->reserved_micro);
      if ($amountMicro > $available) {
        throw ValidationException::withMessages(['balance' => 'INSUFFICIENT_FUNDS']);
      }

      $user->reserved_micro += $amountMicro;
      $user->save();

      $hold = CreditHold::create([
        'user_id'        => $user->id,
        'amount_micro'   => $amountMicro,
        'status'         => 'HELD',
        'idempotency_key'=> $idempotencyKey,
        'provider_ref'   => $providerRef,
        'expires_at'     => Carbon::now()->addSeconds($ttlSeconds),
        'metadata'       => $meta,
      ]);

      CreditTransaction::create([
        'user_id'        => $user->id,
        'credit_hold_id' => $hold->id,
        'type'           => 'HOLD',
        'delta_micro'    => 0,
        'metadata'       => ['amount_micro'=>$amountMicro],
      ]);

      return $hold->fresh();
    });
  }

  public function capture(?int $userId, ?string $sub, int $holdId, int $actualCostMicro, string $captureKey): CreditHold
  {
    return DB::transaction(function () use ($userId,$sub,$holdId,$actualCostMicro,$captureKey) {
      $hold = CreditHold::whereKey($holdId)->lockForUpdate()->firstOrFail();
      $user = $this->resolveUser($userId, $sub);
      if ($hold->user_id !== $user->id) {
        throw ValidationException::withMessages(['hold' => 'FORBIDDEN']);
      }

      if ($hold->capture_key && $hold->capture_key === $captureKey) {
        return $hold;
      }
      if ($hold->status !== 'HELD') {
        return $hold;
      }

      $toCapture = (int) min($actualCostMicro, $hold->remainingMicro());

      if ($toCapture > 0) {
        if ($toCapture > $user->balance_micro) {
          throw ValidationException::withMessages(['balance'=>'INSUFFICIENT_FUNDS_AT_CAPTURE']);
        }
        $user->balance_micro  -= $toCapture;
        $user->reserved_micro -= $toCapture;
        $hold->captured_micro += $toCapture;

        CreditTransaction::create([
          'user_id'        => $user->id,
          'credit_hold_id' => $hold->id,
          'type'           => 'CAPTURE',
          'delta_micro'    => -$toCapture,
          'metadata'       => ['actual_cost_micro'=>$actualCostMicro],
        ]);
      }

      $surplus = (int) max(0, $hold->amount_micro - $hold->captured_micro);
      if ($surplus > 0) {
        $user->reserved_micro -= $surplus;
        CreditTransaction::create([
          'user_id'        => $user->id,
          'credit_hold_id' => $hold->id,
          'type'           => 'RELEASE',
          'delta_micro'    => 0,
          'metadata'       => ['released_micro'=>$surplus],
        ]);
        $hold->status = 'CAPTURED';
      } elseif ($hold->remainingMicro() === 0) {
        $hold->status = 'CAPTURED';
      }

      $hold->capture_key = $captureKey;
      $user->save(); $hold->save();

      return $hold->fresh();
    });
  }

  public function releaseHold(int $holdId, string $reason = 'EXPIRE'): CreditHold
  {
    return DB::transaction(function () use ($holdId,$reason) {
      $hold = CreditHold::whereKey($holdId)->lockForUpdate()->firstOrFail();
      if ($hold->status !== 'HELD') return $hold;

      $user = User::lockForUpdate()->findOrFail($hold->user_id);
      $remaining = $hold->remainingMicro();
      if ($remaining > 0) {
        $user->reserved_micro = max(0, $user->reserved_micro - $remaining);
        CreditTransaction::create([
          'user_id'        => $user->id,
          'credit_hold_id' => $hold->id,
          'type'           => $reason === 'EXPIRE' ? 'EXPIRE' : 'RELEASE',
          'delta_micro'    => 0,
          'metadata'       => ['released_micro'=>$remaining,'reason'=>$reason],
        ]);
        $user->save();
      }
      $hold->status = $reason === 'EXPIRE' ? 'EXPIRED' : 'RELEASED';
      $hold->save();

      return $hold->fresh();
    });
  }

  public function releaseExpired(): int
  {
    $ids = CreditHold::where('status','HELD')->where('expires_at','<=',now())->pluck('id');
    foreach ($ids as $id) { $this->releaseHold($id, 'EXPIRE'); }
    return count($ids);
  }
}
