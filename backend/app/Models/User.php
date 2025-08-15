<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Mass assignable.
     *
     * Ajoute les champs de billing pour C2.
     * Décommente 'subject' seulement si tu as la colonne en DB.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        // 'subject',
        'balance_micro',
        'reserved_micro',
    ];

    /**
     * Hidden.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'balance_micro'     => 'integer',
            'reserved_micro'    => 'integer',
        ];
    }

    /* =========================
     * Relations (C2)
     * ========================= */
    public function holds()
    {
        return $this->hasMany(CreditHold::class);
    }

    public function tx()
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /* =========================
     * Helpers (pratique)
     * ========================= */
    public function availableMicro(): int
    {
        $balance  = (int) ($this->balance_micro ?? 0);
        $reserved = (int) ($this->reserved_micro ?? 0);
        $avail = $balance - $reserved;
        return $avail > 0 ? $avail : 0;
    }
}
