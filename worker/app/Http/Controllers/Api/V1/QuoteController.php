<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Quote\StoreQuoteRequest;
use App\Http\Resources\Api\V1\QuoteResource;
use App\Models\User;
use App\Services\Quote\QuoteService;

class QuoteController extends Controller
{
    public function __invoke(
        StoreQuoteRequest $request,
        QuoteService $quoteService,
    ): QuoteResource {
        $user = $request->user();
        assert($user instanceof User);

        $quote = $quoteService->create($user, $request->validated());

        return new QuoteResource($quote);
    }
}
