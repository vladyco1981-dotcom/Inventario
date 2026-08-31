<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'product_id',
        'warehouse_id',
        'quantity',
        'previous_quantity',
        'new_quantity',
        'user_id',
        'supplier_id',
        'reason',
        'reference_number',
        'expiration_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'previous_quantity' => 'integer',
        'new_quantity' => 'integer',
        'expiration_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
