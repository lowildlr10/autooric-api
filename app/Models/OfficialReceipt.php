<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Particular;
use App\Models\Payor;
use App\Models\Discount;

class OfficialReceipt extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'accountable_personel_id',
        'receipt_date',
        'deposited_date',
        'cancelled_date',
        'or_no',
        'payor_id',
        'nature_collection_id',
        'amount',
        'discount_id',
        'deposit',
        'amount_words',
        'card_no',
        'payment_mode',
        'is_cancelled'
    ];

    // automatic `id` uuid generation for primary key
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = \Illuminate\Support\Str::uuid();
        });
    }

    public function accountablePersonel()
    {
        return $this->belongsTo(User::class, 'accountable_personel_id');
    }

    public function payor()
    {
        return $this->belongsTo(Payor::class, 'payor_id');
    }

    public function natureCollection()
    {
        return $this->belongsTo(Particular::class, 'nature_collection_id');
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }
}
