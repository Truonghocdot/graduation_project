<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VehicleTypeResource;
use App\Models\VehicleType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehicleTypeController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return VehicleTypeResource::collection(
            VehicleType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        );
    }
}
