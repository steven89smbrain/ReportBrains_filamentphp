<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'invoice_no', 'total', 'secret_note', 'branch', 'ordered_at'])]
class Order extends Model
{
    protected $table = 'fixture_orders';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['total' => 'float', 'ordered_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
