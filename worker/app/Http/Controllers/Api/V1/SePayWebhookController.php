<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Finance\TopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SePayWebhookController extends Controller
{
    public function __invoke(Request $request, TopupService $topups): JsonResponse
    {
        $secret = (string) config('services.sepay.webhook_secret');
        $provided = (string) $request->header('X-SePay-Secret');
        abort_unless($secret !== '' && hash_equals($secret, $provided), 401);
        $data = Validator::validate($request->all(), [
            'event_id' => ['required', 'string', 'max:191'],
            'transaction_id' => ['required', 'string', 'max:100'],
            'reference' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);
        $topup = $topups->complete(
            $data['event_id'],
            $data['transaction_id'],
            $data['reference'],
            (float) $data['amount'],
            $request->all(),
        );

        return response()->json(['data' => ['topup_id' => $topup->public_id]]);
    }
}
