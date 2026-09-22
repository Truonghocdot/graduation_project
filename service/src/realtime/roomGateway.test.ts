import assert from 'node:assert/strict';
import test from 'node:test';
import { Server, Socket } from 'socket.io';

import { WorkerEventEnvelope } from '../contracts/event.js';
import { RoomGateway } from './roomGateway.js';

test('drops duplicate and older aggregate events', () => {
  const emitted: Array<{ room: string; event: string; payload: unknown }> = [];
  const io = {
    to(room: string) {
      return {
        emit(event: string, payload: unknown) {
          emitted.push({ room, event, payload });
        },
      };
    },
  } as unknown as Server;
  const gateway = new RoomGateway(io, async () => true);
  const latest = event('event-2', 2);

  gateway.publish(latest);
  gateway.publish(latest);
  gateway.publish(event('event-1', 1));
  gateway.publish(event('event-3', 3));

  assert.equal(emitted.length, 2);
  assert.equal(emitted[0]?.room, 'service-request:request-1');
  assert.equal(emitted[1]?.payload && (emitted[1].payload as { aggregate_version: number }).aggregate_version, 3);
});

test('allows only authorized service request rooms', async () => {
  const handlers = new Map<string, (...args: unknown[]) => void>();
  const joined: string[] = [];
  const socket = {
    handshake: {
      auth: {
        token: 'valid-token',
      },
    },
    data: {},
    on(name: string, handler: (...args: unknown[]) => void) {
      handlers.set(name, handler);
    },
    join(room: string) {
      joined.push(room);
      return Promise.resolve();
    },
    leave() {
      return Promise.resolve();
    },
  } as unknown as Socket;
  const gateway = new RoomGateway(
    {} as Server,
    async (_token, requestId) => requestId === 'request-1',
  );

  assert.equal(gateway.authorize(socket), true);
  socket.data.userId = 'trusted-user-id';
  gateway.registerHandlers(socket);

  let allowed: unknown;
  handlers.get('booking:join')?.('request-1', (value: unknown) => {
    allowed = value;
  });
  let denied: unknown;
  handlers.get('booking:join')?.('request-2', (value: unknown) => {
    denied = value;
  });
  await new Promise((resolve) => setTimeout(resolve, 0));

  assert.deepEqual(allowed, { ok: true });
  assert.deepEqual(denied, { ok: false });
  assert.deepEqual(joined, ['user:trusted-user-id', 'service-request:request-1']);
});

test('publishes notification events only to the addressed user room', () => {
  const emitted: Array<{ room: string; event: string; payload: unknown }> = [];
  const io = {
    to(room: string) {
      return {
        emit(event: string, payload: unknown) {
          emitted.push({ room, event, payload });
        },
      };
    },
  } as unknown as Server;
  const gateway = new RoomGateway(io, async () => true);

  gateway.publish({
    event_id: 'notification-1',
    event_type: 'NOTIFICATION_CREATED',
    aggregate_type: 'NOTIFICATION',
    aggregate_id: 0,
    aggregate_version: null,
    payload: {
      notification_id: 'notification-public-id',
      user_id: 'user-public-id',
      type: 'CHAT_MESSAGE_RECEIVED',
    },
    occurred_at: '2026-09-22T00:00:00Z',
  });

  assert.equal(emitted.length, 1);
  assert.equal(emitted[0]?.room, 'user:user-public-id');
  assert.equal(emitted[0]?.event, 'notification:event');
});

function event(eventId: string, version: number): WorkerEventEnvelope {
  return {
    event_id: eventId,
    event_type: 'DRIVER_ASSIGNED',
    aggregate_type: 'SERVICE_REQUEST',
    aggregate_id: 10,
    aggregate_version: version,
    payload: { service_request_id: 'request-1' },
    occurred_at: '2026-09-22T00:00:00Z',
  };
}
