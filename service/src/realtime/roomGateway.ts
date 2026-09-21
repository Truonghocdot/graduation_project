import { Server, Socket } from 'socket.io';

import { WorkerEventEnvelope } from '../contracts/event.js';

interface SocketAuth {
  userId?: number;
}

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
    const auth = socket.handshake.auth as SocketAuth | undefined;
    const token = socket.handshake.auth?.token;

    if (typeof token !== 'string' || token.length < 8) {
      return false;
    }

    socket.data.token = token;
    socket.data.userId = auth?.userId;
    return true;
  }

  public registerHandlers(socket: Socket): void {
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

    const requestId = this.requestId(event);

    if (!requestId) {
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

    this.io.to('service-request:' + requestId).emit('booking:event', {
      event_id: event.event_id,
      event_type: event.event_type,
      aggregate_version: event.aggregate_version,
      payload: event.payload,
      occurred_at: event.occurred_at,
    });
  }

  private requestId(event: WorkerEventEnvelope): string | null {
    const value = event.payload.service_request_id;

    return typeof value === 'string' ? value : null;
  }
}
