<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Credit extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'branch_id',
        'amount_owed',
        'amount_paid',
        'status',
        'balance',
        'user_id',
        'order_number',
        'description'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->branch_id = optional(Auth::user())->branch_id;
            $model->user_id = optional(Auth::user())->id;
        });

        // Global scope to automatically filter credits by the user's branch
        static::addGlobalScope('branch', function ($query) {
            $query->where('branch_id', Auth::user()->branch_id);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'customer_id', 'customer_id')
            ->where('payment_status', 'credit');
    }

    // Helper method to get total credit amount for a customer
    public function getTotalCreditAmount()
    {
        return $this->sales()->sum('total_amount');
    }

    // Helper method to get unpaid credit sales
    public function getUnpaidSales()
    {
        return $this->sales()
            ->where('payment_status', 'credit')
            ->where(function ($query) {
                $query->whereNull('paid_amount')
                    ->orWhereRaw('paid_amount < total_amount');
            });
    }

    // Relationship with customer
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // Helper method to calculate remaining balance
    public function calculateBalance()
    {
        return $this->amount_owed - $this->amount_paid;
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
