<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\StoreBankAccountRequest;
use App\Http\Resources\Api\V1\DriverBankAccountResource;
use App\Models\DriverBankAccount;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BankAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        assert($user instanceof User);
        $profile = DriverProfile::query()->where('user_id', $user->id)->firstOrFail();

        return DriverBankAccountResource::collection(
            $profile->bankAccounts()->latest()->get(),
        );
    }

    public function store(StoreBankAccountRequest $request): DriverBankAccountResource
    {
        $user = $request->user();
        assert($user instanceof User);
        $profile = DriverProfile::query()->where('user_id', $user->id)->firstOrFail();
        $accountNumber = preg_replace('/\s+/', '', $request->string('account_number')->toString());
        $account = DriverBankAccount::query()->create([
            'driver_profile_id' => $profile->id,
            'bank_code' => mb_strtoupper($request->string('bank_code')->toString()),
            'account_number_encrypted' => $accountNumber,
            'account_number_hash' => hash('sha256', $accountNumber),
            'account_name' => mb_strtoupper($request->string('account_name')->toString()),
            'is_verified' => false,
            'is_default' => false,
        ]);

        return new DriverBankAccountResource($account);
    }
}
