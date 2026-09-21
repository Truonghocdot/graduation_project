<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX vehicles_one_selected_per_driver
            ON vehicles (driver_profile_id)
            WHERE is_selected = true AND deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX driver_bank_accounts_one_default
            ON driver_bank_accounts (driver_profile_id)
            WHERE is_default = true AND deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX driver_documents_unique_active_number
            ON driver_documents (document_type, document_number)
            WHERE document_number IS NOT NULL AND status IN ('PENDING', 'APPROVED')
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX pricing_rules_one_current_active
            ON pricing_rules (service_type, vehicle_type_id)
            WHERE is_active = true AND effective_to IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX assignments_one_active_per_request
            ON assignments (service_request_id)
            WHERE status = 'ACTIVE'
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX assignments_one_active_per_driver
            ON assignments (driver_profile_id)
            WHERE status = 'ACTIVE'
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX scheduled_service_requests_due
            ON service_requests (scheduled_at, id)
            WHERE status = 'SCHEDULED'
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX outbox_publishable
            ON outbox_events (available_at, id)
            WHERE status IN ('PENDING', 'FAILED')
        SQL);

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_active_phone_verified CHECK (status <> 'ACTIVE' OR phone_verified_at IS NOT NULL)");
        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_scheduled_at_required CHECK (booking_type <> 'SCHEDULED' OR scheduled_at IS NOT NULL)");
        DB::statement("ALTER TABLE service_requests ADD CONSTRAINT service_requests_scheduled_at_required CHECK (booking_type <> 'SCHEDULED' OR scheduled_at IS NOT NULL)");
        DB::statement('ALTER TABLE service_stops ADD CONSTRAINT service_stops_latitude_range CHECK (latitude BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE service_stops ADD CONSTRAINT service_stops_longitude_range CHECK (longitude BETWEEN -180 AND 180)');
        DB::statement('ALTER TABLE delivery_orders ADD CONSTRAINT delivery_orders_cod_consistent CHECK ((is_cod = false AND cod_amount = 0) OR (is_cod = true AND cod_amount > 0))');
        DB::statement('ALTER TABLE ride_bookings ADD CONSTRAINT ride_bookings_passenger_count_positive CHECK (passenger_count > 0)');
        DB::statement('ALTER TABLE pricing_rules ADD CONSTRAINT pricing_rules_values_valid CHECK (base_distance_km >= 0 AND base_fare >= 0 AND price_per_extra_km >= 0 AND driver_rate BETWEEN 0 AND 1)');
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_reserved_withdrawal_nonnegative CHECK (reserved_withdrawal_amount >= 0)');
        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_amount_positive CHECK (amount > 0 AND direction IN ('DEBIT', 'CREDIT'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amounts_nonnegative CHECK (gross_fare >= 0 AND voucher_discount >= 0 AND customer_payable >= 0 AND cash_collected >= 0)');
        DB::statement("ALTER TABLE vouchers ADD CONSTRAINT vouchers_values_valid CHECK ((discount_type = 'PERCENT' AND discount_value > 0 AND discount_value <= 100) OR (discount_type = 'FIXED' AND discount_value > 0))");
        DB::statement('ALTER TABLE ratings ADD CONSTRAINT ratings_score_range CHECK (score BETWEEN 1 AND 5 AND reviewer_user_id <> reviewee_user_id)');
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ratings DROP CONSTRAINT IF EXISTS ratings_score_range');
            DB::statement('ALTER TABLE vouchers DROP CONSTRAINT IF EXISTS vouchers_values_valid');
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_amounts_nonnegative');
            DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_amount_positive');
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS wallets_reserved_withdrawal_nonnegative');
            DB::statement('ALTER TABLE pricing_rules DROP CONSTRAINT IF EXISTS pricing_rules_values_valid');
            DB::statement('ALTER TABLE ride_bookings DROP CONSTRAINT IF EXISTS ride_bookings_passenger_count_positive');
            DB::statement('ALTER TABLE delivery_orders DROP CONSTRAINT IF EXISTS delivery_orders_cod_consistent');
            DB::statement('ALTER TABLE service_stops DROP CONSTRAINT IF EXISTS service_stops_longitude_range');
            DB::statement('ALTER TABLE service_stops DROP CONSTRAINT IF EXISTS service_stops_latitude_range');
            DB::statement('ALTER TABLE service_requests DROP CONSTRAINT IF EXISTS service_requests_scheduled_at_required');
            DB::statement('ALTER TABLE quotes DROP CONSTRAINT IF EXISTS quotes_scheduled_at_required');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_active_phone_verified');
        }

        DB::statement('DROP INDEX IF EXISTS outbox_publishable');
        DB::statement('DROP INDEX IF EXISTS scheduled_service_requests_due');
        DB::statement('DROP INDEX IF EXISTS assignments_one_active_per_driver');
        DB::statement('DROP INDEX IF EXISTS assignments_one_active_per_request');
        DB::statement('DROP INDEX IF EXISTS pricing_rules_one_current_active');
        DB::statement('DROP INDEX IF EXISTS driver_documents_unique_active_number');
        DB::statement('DROP INDEX IF EXISTS driver_bank_accounts_one_default');
        DB::statement('DROP INDEX IF EXISTS vehicles_one_selected_per_driver');
    }
};
