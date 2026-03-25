<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['slug' => 'bank_charges',         'name' => 'Bank / MPESA Charges',          'color' => '#fee2e2'],
            ['slug' => 'secretary_fee',        'name' => 'Secretary / Secretarial Fee',   'color' => '#fef3c7'],
            ['slug' => 'audit',                'name' => 'Audit & Legal Fees',            'color' => '#dbeafe'],
            ['slug' => 'agm',                  'name' => 'AGM Expenses',                  'color' => '#fee2e2'],
            ['slug' => 'admin',                'name' => 'Admin Expenses',                'color' => '#fef3c7'],
            ['slug' => 'welfare_token',        'name' => 'Welfare Token',                 'color' => '#d8f3dc'],
            ['slug' => 'development',          'name' => 'Development Expenses',          'color' => '#e0e7ff'],
            ['slug' => 'cic_transfer',         'name' => 'CIC Transfer',                  'color' => '#fce7f3'],
            ['slug' => 'cic_dividend',         'name' => 'CIC Dividends Payout',          'color' => '#fce7f3'],
            ['slug' => 'investment_transfer',  'name' => 'Investment Transfer (KCB/CBK)', 'color' => '#e0e7ff'],
            ['slug' => 'sagana',               'name' => 'Sagana Shares',                 'color' => '#d8f3dc'],
            ['slug' => 'other',                'name' => 'Other',                         'color' => '#f3f4f6'],
        ];

        foreach ($defaults as $cat) {
            ExpenseCategory::firstOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}
