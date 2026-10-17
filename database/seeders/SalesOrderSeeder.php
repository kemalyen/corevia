<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SalesOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SalesOrder::factory(10)
            ->create()
            ->each(function ($salesOrder) {
                $salesOrder->lines()->createMany(
                    \App\Models\SalesOrderLine::factory(3)->make()->toArray()
                );
            });
    }
}
