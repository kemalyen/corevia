<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    /** @use HasFactory<\Database\Factories\SalesOrderFactory> */
    use HasFactory;

    public function lines()
    {
        return $this->hasMany(SalesOrderLine::class);
    }
}
