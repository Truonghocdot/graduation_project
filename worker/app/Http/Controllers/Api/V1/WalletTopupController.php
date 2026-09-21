<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Finance\StoreTopupRequest;
use App\Http\Resources\Api\V1\WalletTopupResource;
use App\Models\User;
use App\Models\WalletTopup;
use App\Services\Finance\TopupService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletTopupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        assert($user instanceof User);

        return WalletTopupResource::collection(
            WalletTopup::query()
                ->whereHas('wallet', fn ($query) => $query->where('user_id', $user->id))
                ->latest()
                ->limit(50)
                ->get(),
        );
    }

    public function store(
        StoreTopupRequest $request,
        TopupService $topups,
    ): WalletTopupResource {
        $user = $request->user();
        assert($user instanceof User);

        return new WalletTopupResource($topups->create(
            $user,
            $request->float('amount'),
            $request->idempotencyKey(),
        ));
    }
}
