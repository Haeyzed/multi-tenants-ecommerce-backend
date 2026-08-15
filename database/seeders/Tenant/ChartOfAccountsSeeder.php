<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Enums\Tenant\Accounting\AccountType;
use App\Models\Tenant\Account;
use App\Services\Tenant\Commerce\CommerceSettingService;
use Illuminate\Database\Seeder;

/**
 * Seeds default system chart of accounts and commerce accounting maps.
 */
class ChartOfAccountsSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string, type: AccountType, setting: string}>
     */
    private const array ACCOUNTS = [
        ['code' => '1000', 'name' => 'Cash', 'type' => AccountType::Asset, 'setting' => 'accounting.cash'],
        ['code' => '1200', 'name' => 'Inventory', 'type' => AccountType::Asset, 'setting' => 'accounting.inventory'],
        ['code' => '2000', 'name' => 'Accounts Payable', 'type' => AccountType::Liability, 'setting' => 'accounting.ap'],
        ['code' => '2100', 'name' => 'Tax Payable', 'type' => AccountType::Liability, 'setting' => 'accounting.tax_payable'],
        ['code' => '4000', 'name' => 'Sales Revenue', 'type' => AccountType::Revenue, 'setting' => 'accounting.sales'],
        ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => AccountType::Expense, 'setting' => 'accounting.cogs'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = app(CommerceSettingService::class);

        foreach (self::ACCOUNTS as $definition) {
            $account = Account::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'is_system' => true,
                    'is_active' => true,
                ],
            );

            $settings->set($definition['setting'], $account->code);
        }
    }
}
