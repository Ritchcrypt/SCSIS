<?php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\TanodProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DaoSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Ritche Deroy',
            'email' => 'admin@daosystem.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '09123456789',
            'address' => 'Dao, Capiz',
            'status' => 'active',
        ]);

        $tanods = [];

        for ($i = 1; $i <= 3; $i++) {
            $tanod = User::create([
                'name' => 'Tanod ' . $i,
                'email' => 'tanod' . $i . '@daosystem.test',
                'password' => Hash::make('password'),
                'role' => 'tanod',
                'phone' => '0912345678' . $i,
                'address' => 'Dao, Capiz',
                'status' => 'active',
            ]);

            TanodProfile::create([
                'user_id' => $tanod->id,
                'badge_number' => 'TND-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'duty_status' => 'on_duty',
            ]);

            $tanods[] = $tanod;
        }

        $residents = [];

        for ($i = 1; $i <= 5; $i++) {
            $resident = User::create([
                'name' => 'Resident ' . $i,
                'email' => 'resident' . $i . '@daosystem.test',
                'password' => Hash::make('password'),
                'role' => 'resident',
                'phone' => '0998765432' . $i,
                'address' => 'Dao, Capiz',
                'status' => 'active',
            ]);

            $residents[] = $resident;
        }

        $sampleIncidents = [
            [
                'title' => 'Tricycle Overprice',
                'description' => 'Resident reported an alleged tricycle overpricing incident near the barangay proper.',
                'category' => 'Other',
                'location' => 'Dao Public Market',
                'status' => 'escalated',
                'severity' => 'medium',
                'days_ago' => 10,
            ],
            [
                'title' => 'Vandalism near waiting shed',
                'description' => 'Graffiti and damage reported at a public waiting shed.',
                'category' => 'Vandalism',
                'location' => 'Barangay Waiting Shed',
                'status' => 'dispatched',
                'severity' => 'low',
                'days_ago' => 14,
            ],
            [
                'title' => 'Physical assault report',
                'description' => 'Resident reported a physical altercation involving two individuals.',
                'category' => 'Assault',
                'location' => 'Purok 2',
                'status' => 'dispatched',
                'severity' => 'high',
                'days_ago' => 21,
            ],
            [
                'title' => 'Domestic disturbance',
                'description' => 'Possible domestic disturbance requiring barangay intervention.',
                'category' => 'Domestic',
                'location' => 'Purok 4',
                'status' => 'escalated',
                'severity' => 'critical',
                'days_ago' => 21,
            ],
            [
                'title' => 'Noise complaint',
                'description' => 'Loud music reported late at night.',
                'category' => 'Disturbance',
                'location' => 'Purok 1',
                'status' => 'pending',
                'severity' => 'low',
                'days_ago' => 5,
            ],
            [
                'title' => 'Road blockage',
                'description' => 'Obstruction reported along a barangay road.',
                'category' => 'Traffic',
                'location' => 'Main Road',
                'status' => 'active',
                'severity' => 'medium',
                'days_ago' => 3,
            ],
            [
                'title' => 'Suspicious person',
                'description' => 'Resident reported a suspicious person roaming near houses.',
                'category' => 'Security',
                'location' => 'Purok 3',
                'status' => 'active',
                'severity' => 'medium',
                'days_ago' => 2,
            ],
            [
                'title' => 'Lost item report',
                'description' => 'Resident reported a missing wallet.',
                'category' => 'Other',
                'location' => 'Dao Plaza',
                'status' => 'resolved',
                'severity' => 'low',
                'days_ago' => 1,
            ],
            [
                'title' => 'Minor street fight',
                'description' => 'Small fight reported between minors.',
                'category' => 'Assault',
                'location' => 'Purok 5',
                'status' => 'pending',
                'severity' => 'medium',
                'days_ago' => 4,
            ],
            [
                'title' => 'Illegal parking',
                'description' => 'Vehicle blocking public access.',
                'category' => 'Traffic',
                'location' => 'Barangay Hall Road',
                'status' => 'dispatched',
                'severity' => 'low',
                'days_ago' => 6,
            ],
            [
                'title' => 'Public drinking complaint',
                'description' => 'Group reported drinking in a public area.',
                'category' => 'Ordinance',
                'location' => 'Near Basketball Court',
                'status' => 'active',
                'severity' => 'medium',
                'days_ago' => 7,
            ],
        ];

        foreach ($sampleIncidents as $index => $incident) {
            Incident::create([
                'reporter_id' => $residents[$index % count($residents)]->id,
                'assigned_tanod_id' => $tanods[$index % count($tanods)]->id,
                'title' => $incident['title'],
                'description' => $incident['description'],
                'category' => $incident['category'],
                'location' => $incident['location'],
                'status' => $incident['status'],
                'severity' => $incident['severity'],
                'reported_at' => now()->subDays($incident['days_ago']),
            ]);
        }
    }
}