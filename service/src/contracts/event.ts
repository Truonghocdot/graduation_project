export type MatchingEventType =
  | 'OFFER_CREATED'
  | 'OFFER_EXPIRED'
  | 'DRIVER_ASSIGNED'
  | 'DELIVERY_SEARCH_REQUESTED'
  | 'RIDE_SEARCH_REQUESTED'
  | 'SCHEDULED_SEARCH_STARTED'
  | 'MATCHING_RESTARTED'
  | 'DRIVER_LOCATION_UPDATED'
  | 'DELIVERY_DRIVER_AT_PICKUP'
  | 'DELIVERY_PICKED_UP'
  | 'DELIVERY_IN_TRANSIT'
  | 'DELIVERY_DELIVERED'
  | 'RIDE_DRIVER_ARRIVED'
  | 'RIDE_STARTED'
  | 'RIDE_ENDED'
  | 'PAYMENT_SETTLED'
  | 'DRIVER_EARNING_SETTLED'
  | 'WALLET_TOPPED_UP'
  | 'DRIVER_WITHDRAWAL_REQUESTED'
  | 'DRIVER_WITHDRAWAL_COMPLETED'
  | 'DRIVER_WITHDRAWAL_FAILED'
  | 'REFUND_COMPLETED'
  | 'SERVICE_REQUEST_CANCELLED'
  | 'CHAT_MESSAGE_CREATED'
  | 'NOTIFICATION_CREATED'
  | 'SUPPORT_TICKET_CREATED'
  | 'SUPPORT_TICKET_MESSAGE_CREATED'
  | 'SUPPORT_TICKET_RESOLVED'
  | 'INCIDENT_REPORTED'
  | 'SAFETY_INCIDENT_REPORTED'
  | 'INCIDENT_RESOLVED'
  | 'LOW_RATING_FLAGGED';

export interface WorkerEventEnvelope {
  event_id: string;
  event_type: MatchingEventType | string;
  aggregate_type: string;
  aggregate_id: number;
  aggregate_version: number | null;
  payload: Record<string, unknown>;
  occurred_at: string | null;
}

export type WorkerEventHandler = (event: WorkerEventEnvelope) => Promise<void>;

export function parseWorkerEvent(input: string): WorkerEventEnvelope | null {
  try {
    const event = JSON.parse(input) as Partial<WorkerEventEnvelope>;

    if (
      typeof event.event_id !== 'string' ||
      typeof event.event_type !== 'string' ||
      typeof event.aggregate_type !== 'string' ||
      typeof event.aggregate_id !== 'number' ||
      typeof event.payload !== 'object' ||
      event.payload === null
    ) {
      return null;
    }

    return event as WorkerEventEnvelope;
  } catch {
    return null;
  }
}
