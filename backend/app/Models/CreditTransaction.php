<?php
// app/Models/CreditTransaction.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CreditTransaction extends Model {
  protected $fillable = ['user_id','credit_hold_id','type','delta_micro','metadata'];
  protected $casts = ['metadata'=>'array'];
  public function user(){ return $this->belongsTo(User::class); }
  public function hold(){ return $this->belongsTo(CreditHold::class,'credit_hold_id'); }
}
