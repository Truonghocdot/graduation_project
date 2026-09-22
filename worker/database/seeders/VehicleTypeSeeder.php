<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VehicleTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        VehicleType::query()->updateOrCreate(
            ['unique_key' => 'MOTORBIKE'],
            [
                'public_id' => (string) Str::uuid(),
                'name' => 'Xe máy',
                'passenger_capacity' => 1,
                'max_weight_kg' => 30,
                'max_length_cm' => 60,
                'max_width_cm' => 50,
                'max_height_cm' => 50,
                'is_active' => true,
            ],
        );

        VehicleType::query()->updateOrCreate(
            ['unique_key' => 'CAR_4_SEAT'],
            [
                'public_id' => (string) Str::uuid(),
                'name' => 'Ô tô 4 chỗ',
                'passenger_capacity' => 4,
                'max_weight_kg' => 100,
                'max_length_cm' => 100,
                'max_width_cm' => 80,
                'max_height_cm' => 80,
                'is_active' => true,
            ],
        );
    }
}
