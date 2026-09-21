import assert from 'node:assert/strict';
import test from 'node:test';

import { parseWorkerEvent } from './event.js';

test('parses a valid worker event envelope', () => {
  const event = parseWorkerEvent(JSON.stringify({
    event_id: 'event-1',
    event_type: 'DRIVER_ASSIGNED',
    aggregate_type: 'SERVICE_REQUEST',
    aggregate_id: 10,
    aggregate_version: 2,
    payload: { service_request_id: 'request-1' },
    occurred_at: '2026-09-22T00:00:00Z',
  }));

  assert.equal(event?.event_type, 'DRIVER_ASSIGNED');
  assert.equal(event?.payload.service_request_id, 'request-1');
});

test('rejects malformed and incomplete event envelopes', () => {
  assert.equal(parseWorkerEvent('not-json'), null);
  assert.equal(parseWorkerEvent(JSON.stringify({ event_id: 'event-1' })), null);
});
