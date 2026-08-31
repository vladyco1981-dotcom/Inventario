<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'category_id',
        'supplier_id',
        'description',
        'cost_price',
        'sale_price',
        'min_stock',
        'expiration_date',
        'track_expiration',
        'image_path',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'expiration_date' => 'date',
        'track_expiration' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function warehouses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'product_warehouse')
            ->withPivot('quantity', 'expiration_date')
            ->withTimestamps();
    }

    public function getTotalStockAttribute(): int
    {
        return $this->warehouses()->selectRaw('SUM(product_warehouse.quantity) as total')
            ->first()
            ->total ?? 0;
    }

    public function isLowStock(): bool
    {
        return $this->total_stock <= $this->min_stock;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->track_expiration || !$this->expiration_date) {
            return false;
        }

        return $this->expiration_date->diffInDays(now(), false) <= $days;
    }
}
