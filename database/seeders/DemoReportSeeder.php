<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use ReportBrains\ReportDesigner\Models\ReportTemplate;
use ReportBrains\ReportDesigner\TemplateRepository;

/**
 * Sample sales data and report templates for trying the report designer.
 *
 * Safe to run again: orders are only generated while the demo tables are empty,
 * and the sample templates are updated in place from resources/reports.
 */
class DemoReportSeeder extends Seeder
{
    /**
     * File templates in resources/reports that are loaded into the designer.
     */
    private const TEMPLATES = ['sales-by-branch', 'order-list', 'user-directory'];

    /**
     * @var array<int, array{name: string, city: string}>
     */
    private const BRANCHES = [
        ['name' => 'Jakarta Pusat', 'city' => 'Jakarta'],
        ['name' => 'Bandung', 'city' => 'Bandung'],
        ['name' => 'Surabaya', 'city' => 'Surabaya'],
        ['name' => 'Medan', 'city' => 'Medan'],
    ];

    private const CUSTOMER_CITIES = [
        'Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Semarang', 'Yogyakarta', 'Makassar', 'Denpasar',
    ];

    /**
     * Run the database seeds.
     */
    public function run(TemplateRepository $templates): void
    {
        if (Order::query()->doesntExist()) {
            $this->seedSales();
        } else {
            $this->command?->info('Demo orders already exist; leaving them unchanged.');
        }

        foreach (self::TEMPLATES as $key) {
            $document = $templates->fromFileKey($key);

            ReportTemplate::query()->updateOrCreate(
                ['key' => $key],
                ['title' => $document['title'], 'schema' => $document],
            );
        }

        $this->command?->info('Loaded sample report templates: '.implode(', ', self::TEMPLATES).'.');
    }

    private function seedSales(): void
    {
        $faker = fake('id_ID');
        $faker->seed(2026);

        $branches = array_map(
            fn (array $branch): Branch => Branch::query()->create($branch),
            self::BRANCHES,
        );

        $customers = array_map(fn (): Customer => Customer::query()->create([
            'name' => $faker->boolean(35) ? $faker->company() : $faker->name(),
            'email' => $faker->unique()->safeEmail(),
            'city' => $faker->randomElement(self::CUSTOMER_CITIES),
        ]), range(1, 40));

        $dates = array_map(
            fn (): Carbon => Carbon::instance($faker->dateTimeBetween('-90 days', 'now')),
            range(1, 240),
        );

        usort($dates, fn (Carbon $a, Carbon $b): int => $a <=> $b);

        foreach ($dates as $index => $orderedAt) {
            Order::query()->create([
                'invoice_no' => sprintf('INV-%s-%04d', $orderedAt->format('Ym'), $index + 1),
                'customer_id' => $faker->randomElement($customers)->id,
                'branch_id' => $faker->randomElement($branches)->id,
                'status' => $faker->randomElement(['paid', 'paid', 'paid', 'paid', 'paid', 'paid', 'paid', 'pending', 'pending', 'cancelled']),
                'total' => $faker->numberBetween(15, 2500) * 10_000,
                'ordered_at' => $orderedAt,
            ]);
        }

        $this->command?->info('Created 4 branches, 40 customers and 240 orders across the last 90 days.');
    }
}
