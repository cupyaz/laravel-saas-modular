<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::take(5)->get();
        $freePlan = DB::table('plans')->where('slug', 'free')->first();

        foreach ($users as $index => $user) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => "Demo Company " . ($index + 1),
                'slug' => 'demo-company-' . ($index + 1),
                'domain' => null,
                'database' => null,
                'config' => json_encode([
                    'theme' => 'default',
                    'locale' => 'en',
                    'timezone' => 'UTC',
                ]),
                'is_active' => true,
                'trial_ends_at' => now()->addDays(14),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign user to tenant
            DB::table('tenant_users')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create subscription
            DB::table('subscriptions')->insert([
                'tenant_id' => $tenantId,
                'plan_id' => $freePlan->id,
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}