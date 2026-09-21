<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Finance\StoreWithdrawalRequest;
use App\Http\Resources\Api\V1\WithdrawalRequestResource;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Finance\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WithdrawalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        assert($user instanceof User);

        return WithdrawalRequestResource::collection(
            WithdrawalRequest::query()
                ->whereHas('wallet', fn ($query) => $query->where('user_id', $user->id))
                ->with('bankAccount')
                ->latest()
                ->limit(50)
                ->get(),
        );
    }

    public function store(
        StoreWithdrawalRequest $request,
        WithdrawalService $withdrawals,
    ): WithdrawalRequestResource {
        $user = $request->user();
        assert($user instanceof User);

        return new WithdrawalRequestResource($withdrawals->request(
            $user,
            $request->string('bank_account_id')->toString(),
            $request->float('amount'),
            $request->idempotencyKey(),
        ));
    }
}
