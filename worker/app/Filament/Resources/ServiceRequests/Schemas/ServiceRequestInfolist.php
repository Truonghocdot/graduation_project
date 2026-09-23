<?php

namespace App\Filament\Resources\ServiceRequests\Schemas;

use App\Models\ServiceEvidence;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Yêu cầu')->schema([
                TextEntry::make('public_id')->label('Mã yêu cầu')->copyable(),
                TextEntry::make('service_type')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('booking_type')->badge(),
                TextEntry::make('creator.name')->label('Khách hàng'),
                TextEntry::make('creator.phone')->label('Số điện thoại khách hàng'),
                TextEntry::make('vehicleType.name')->label('Loại xe'),
                TextEntry::make('scheduled_at')->dateTime()->placeholder('Ngay bây giờ'),
                TextEntry::make('search_started_at')->dateTime()->placeholder('Chưa bắt đầu'),
                TextEntry::make('cancellation_reason_code')->placeholder('Không có'),
            ])->columns(2),
            Section::make('Thanh toán')->schema([
                TextEntry::make('payment.public_id')->label('Mã thanh toán')->copyable(),
                TextEntry::make('payment.payer_type')->badge(),
                TextEntry::make('payment.payer.name')->label('Người thanh toán'),
                TextEntry::make('payment.method')->badge(),
                TextEntry::make('payment.status')->badge(),
                TextEntry::make('payment.gross_fare')->money('VND'),
                TextEntry::make('payment.voucher_discount')->money('VND'),
                TextEntry::make('payment.customer_payable')->money('VND'),
                TextEntry::make('payment.settlement.driver_net_earning')
                    ->label('Thu nhập thực nhận của tài xế')
                    ->money('VND')
                    ->placeholder('Chưa quyết toán'),
            ])->columns(2),
            Section::make('Chi tiết dịch vụ')->schema([
                TextEntry::make('deliveryOrder.goods_type')->label('Hàng hóa')->placeholder('Không phải đơn giao hàng'),
                TextEntry::make('deliveryOrder.cod_amount')->label('COD')->money('VND')->placeholder('0'),
                TextEntry::make('rideBooking.passenger_count')->label('Số hành khách')->placeholder('Không phải chuyến xe'),
                TextEntry::make('quote.distance_meters')->label('Quãng đường')->suffix(' m'),
                TextEntry::make('quote.duration_seconds')->label('Thời lượng')->suffix(' giây'),
            ])->columns(2),
            Section::make('Bằng chứng riêng tư')->schema([
                RepeatableEntry::make('evidences')
                    ->schema([
                        TextEntry::make('evidence_type')->badge(),
                        TextEntry::make('original_name')
                            ->url(fn (ServiceEvidence $record): string => route(
                                'api.v1.service-evidence.file',
                                ['evidence' => $record],
                            ))
                            ->openUrlInNewTab(),
                        TextEntry::make('uploader.name')->label('Người tải lên'),
                        TextEntry::make('created_at')->dateTime(),
                    ])->columns(4),
            ]),
        ]);
    }
}
