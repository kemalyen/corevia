<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderLine extends Model
{
    /** @use HasFactory<\Database\Factories\SalesOrderLineFactory> */
    use HasFactory;


    public function salesOrder()
    {
        return $this->belongsTo(\App\Models\SalesOrder::class);
    }
}
