<x-filament-panels::page>
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                <tr>
                    <th class="px-4 py-3">Request</th>
                    <th class="px-4 py-3">Service</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Batch</th>
                    <th class="px-4 py-3">Offers</th>
                    <th class="px-4 py-3">Driver</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($requests as $request)
                    <tr wire:key="matching-request-{{ $request->id }}">
                        <td class="px-4 py-3">
                            <a
                                class="font-medium text-primary-600 hover:underline"
                                href="{{ \App\Filament\Resources\ServiceRequests\ServiceRequestResource::getUrl('view', ['record' => $request]) }}"
                            >
                                {{ $request->public_id }}
                            </a>
                            <div class="text-xs text-gray-500">{{ $request->creator->name }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $request->service_type->value }}</td>
                        <td class="px-4 py-3">{{ $request->status->value }}</td>
                        <td class="px-4 py-3">{{ $request->search_attempt }}</td>
                        <td class="px-4 py-3">{{ $request->driverOffers->count() }}</td>
                        <td class="px-4 py-3">
                            {{ $request->assignments->first()?->driverProfile?->user?->name ?? 'Unassigned' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-8 text-center text-gray-500" colspan="6">No active matching requests.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
