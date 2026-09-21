<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WalletResource;
use App\Models\User;
use App\Services\Finance\TopupService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request, TopupService $topups): WalletResource
    {
        $user = $request->user();
        assert($user instanceof User);
        $wallet = $topups->ensureWallet($user)->load([
            'ledgerAccount.entries' => fn ($query) => $query->latest('id')->limit(50),
        ]);

        return new WalletResource($wallet);
    }
}
