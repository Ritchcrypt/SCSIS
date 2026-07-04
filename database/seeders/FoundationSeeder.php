<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        /*
        |--------------------------------------------------------------------------
        | Barangays
        |--------------------------------------------------------------------------
        */

        $barangays = [
            ['barangay_name' => 'Poblacion', 'location' => 'Dao, Capiz'],
            ['barangay_name' => 'Agtambi', 'location' => 'Dao, Capiz'],
            ['barangay_name' => 'Balucuan', 'location' => 'Dao, Capiz'],
            ['barangay_name' => 'Duyoc', 'location' => 'Dao, Capiz'],
            ['barangay_name' => 'Lacaron', 'location' => 'Dao, Capiz'],
            ['barangay_name' => 'Ilas Sur', 'location' => 'Dao, Capiz'],
            ['barangay_name' => 'Ilas Norte', 'location' => 'Dao, Capiz'],
        ];

        foreach ($barangays as $barangay) {
            DB::table('barangays')->updateOrInsert(
                ['barangay_name' => $barangay['barangay_name']],
                [
                    'location' => $barangay['location'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Incident Categories
        |--------------------------------------------------------------------------
        | These are reference values for filter/dropdowns.
        | These are not fake incident records.
        */

        $categories = [
            ['category_name' => 'Theft', 'description' => 'Theft, robbery, and property-related reports.'],
            ['category_name' => 'Assault', 'description' => 'Physical assault and violence-related incidents.'],
            ['category_name' => 'Fire', 'description' => 'Fire-related incidents including house fires, grass fires, and electrical fires.'],
            ['category_name' => 'Flood', 'description' => 'Flooding and water-related emergency reports.'],
            ['category_name' => 'Medical Emergency', 'description' => 'Reports requiring urgent medical response.'],
            ['category_name' => 'Domestic Violence', 'description' => 'Domestic violence and household safety-related reports.'],
            ['category_name' => 'Vandalism', 'description' => 'Property damage, vandalism, and public disturbance reports.'],
            ['category_name' => 'Missing Person', 'description' => 'Missing person reports and related emergency information.'],
            ['category_name' => 'Noise Complaint', 'description' => 'Noise disturbance and nuisance reports.'],
            ['category_name' => 'Drug Activity', 'description' => 'Reports related to suspected illegal drug activity.'],
            ['category_name' => 'Other', 'description' => 'Other safety incidents not listed in the main categories.'],
        ];

        $activeCategoryNames = collect($categories)->pluck('category_name')->all();

        DB::table('incident_categories')
            ->whereNotIn('category_name', $activeCategoryNames)
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

        foreach ($categories as $category) {
            DB::table('incident_categories')->updateOrInsert(
                ['category_name' => $category['category_name']],
                [
                    'description' => $category['description'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Incident Statuses
        |--------------------------------------------------------------------------
        | These match the prototype workflow.
        */

        $statuses = [
            [
                'status_name' => 'Reported',
                'description' => 'Incident has been submitted by a resident or user.',
                'sort_order' => 1,
            ],
            [
                'status_name' => 'Acknowledged',
                'description' => 'Incident has been acknowledged by authorized personnel.',
                'sort_order' => 2,
            ],
            [
                'status_name' => 'Dispatched',
                'description' => 'Incident has been assigned or dispatched to responders.',
                'sort_order' => 3,
            ],
            [
                'status_name' => 'Responding',
                'description' => 'Assigned responder is currently handling the incident.',
                'sort_order' => 4,
            ],
            [
                'status_name' => 'Resolved',
                'description' => 'Incident has been handled and resolved.',
                'sort_order' => 5,
            ],
            [
                'status_name' => 'Closed',
                'description' => 'Incident record has been finalized and closed.',
                'sort_order' => 6,
            ],
            [
                'status_name' => 'Escalated',
                'description' => 'Incident has been escalated to another agency or authority.',
                'sort_order' => 7,
            ],
        ];

        $activeStatusNames = collect($statuses)->pluck('status_name')->all();

        DB::table('statuses')
            ->whereNotIn('status_name', $activeStatusNames)
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

        foreach ($statuses as $status) {
            DB::table('statuses')->updateOrInsert(
                ['status_name' => $status['status_name']],
                [
                    'description' => $status['description'],
                    'sort_order' => $status['sort_order'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}