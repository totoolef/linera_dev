<?php
// app/Models/User.php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable {
  protected $fillable = [
    'name','email','password',
    // 'subject', // si tu l’utilises
    'balance_micro','reserved_micro'
  ];
  public function holds(){ return $this->hasMany(CreditHold::class); }
  public function tx(){ return $this->hasMany(CreditTransaction::class); }
}
