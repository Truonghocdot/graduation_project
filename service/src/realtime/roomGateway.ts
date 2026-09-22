import { Server, Socket } from 'socket.io';

import { WorkerEventEnvelope } from '../contracts/event.js';

export class RoomGateway {
  private readonly versions = new Map<string, number>();
  private readonly seenEventIds = new Set<string>();

  public constructor(
    private readonly io: Server,
    private readonly authorizeRoom: (
      token: string,
      serviceRequestId: string,
    ) => Promise<boolean>,
  ) {}

  public authorize(socket: Socket): boolean {
    const token = socket.handshake.auth?.token;

    if (typeof token !== 'string' || token.length < 8) {
      return false;
    }

    socket.data.token = token;
    return true;
  }

  public registerHandlers(socket: Socket): void {
    const userId = socket.data.userId;

    if (typeof userId === 'string') {
      void socket.join('user:' + userId);
    }

    socket.on('booking:join', async (
      serviceRequestId: string,
      acknowledge?: (value: unknown) => void,
    ) => {
      const token = socket.data.token;
      const allowed = typeof token === 'string'
        && await this.authorizeRoom(token, serviceRequestId);

      if (allowed) {
        void socket.join('service-request:' + serviceRequestId);
      }

      acknowledge?.({ ok: allowed });
    });

    socket.on('booking:leave', (serviceRequestId: string) => {
      void socket.leave('service-request:' + serviceRequestId);
    });
  }

  public publish(event: WorkerEventEnvelope): void {
    if (this.seenEventIds.has(event.event_id)) {
      return;
    }

    const requestId = this.stringPayload(event, 'service_request_id');
    const userId = this.stringPayload(event, 'user_id');

    if (!requestId && !userId) {
      return;
    }

    this.seenEventIds.add(event.event_id);

    const key = event.aggregate_type + ':' + event.aggregate_id;
    const version = event.aggregate_version ?? 0;
    const currentVersion = this.versions.get(key) ?? 0;

    if (version > 0 && version < currentVersion) {
      return;
    }

    if (version > 0) {
      this.versions.set(key, version);
    }

    const payload = {
      event_id: event.event_id,
      event_type: event.event_type,
      aggregate_version: event.aggregate_version,
      payload: event.payload,
      occurred_at: event.occurred_at,
    };

    if (requestId) {
      this.io.to('service-request:' + requestId).emit('booking:event', payload);
    }

    if (userId) {
      this.io.to('user:' + userId).emit('notification:event', payload);
    }
  }

  private stringPayload(event: WorkerEventEnvelope, key: string): string | null {
    const value = event.payload[key];

    return typeof value === 'string' ? value : null;
  }
}
