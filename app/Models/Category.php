<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'category_name',
        'order_no'
    ];

    // automatic `id` uuid generation for primary key and `order_no` generation
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = \Illuminate\Support\Str::uuid();
            $model->order_no = Category::count();
        });
    }

    public function particulars()
    {
        return $this->hasMany(Particular::class, 'category_id', 'id')->orderBy('order_no');
    }
}
