<x-filament-panels::page>
    @vite('resources/css/app.css',
        'resources/js/app.js',
    )
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                <tr>
                    <th class="px-4 py-3">Yêu cầu</th>
                    <th class="px-4 py-3">Dịch vụ</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">Lần tìm</th>
                    <th class="px-4 py-3">Đề nghị</th>
                    <th class="px-4 py-3">Tài xế</th>
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
                        <td class="px-4 py-3">{{ $request->service_type->getLabel() }}</td>
                        <td class="px-4 py-3">{{ $request->status->getLabel() }}</td>
                        <td class="px-4 py-3">{{ $request->search_attempt }}</td>
                        <td class="px-4 py-3">{{ $request->driverOffers->count() }}</td>
                        <td class="px-4 py-3">
                            {{ $request->assignments->first()?->driverProfile?->user?->name ?? 'Chưa phân công' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-8 text-center text-gray-500" colspan="6">Không có yêu cầu tìm tài xế đang hoạt động.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
