<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables for the fixture models used by the data source tests.
 *
 * Prefixed so they never collide with the demo application's own customers and
 * orders tables. `secret_note` exists so the tests can prove a column the
 * source never exposed stays unreachable, however a template asks for it.
 */
function createOrderTables(): void
{
    Schema::create('fixture_customers', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('city')->nullable();
    });

    Schema::create('fixture_orders', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('customer_id')->nullable();
        $table->string('invoice_no');
        $table->float('total')->default(0);
        $table->string('branch')->nullable();
        $table->string('secret_note')->nullable();
        $table->dateTime('ordered_at')->nullable();
    });
}
