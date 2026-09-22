import 'dotenv/config';

import express from 'express';
import http from 'node:http';
import { Server } from 'socket.io';

import { WorkerAuthorizer } from './auth/workerAuthorizer.js';
import { RedisEventConsumer } from './messaging/redisEventConsumer.js';
import { RoomGateway } from './realtime/roomGateway.js';

const port = Number(process.env.SERVICE_PORT ?? 3000);
const redisUrl = process.env.REDIS_URL ?? 'redis://127.0.0.1:6379';
const eventChannel = process.env.MATCHING_OUTBOX_CHANNEL ?? 'worker.outbox';
const locationChannel = process.env.DRIVER_LOCATION_CHANNEL ?? 'worker.location';
const workerApiUrl = process.env.WORKER_API_URL ?? 'http://127.0.0.1:8000/api/v1';

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
  cors: {
    origin: (process.env.CORS_ALLOWED_ORIGINS ?? '*').split(','),
    credentials: false,
  },
});
const authorizer = new WorkerAuthorizer(workerApiUrl);
const gateway = new RoomGateway(
  io,
  (token, serviceRequestId) => authorizer.canJoin(token, serviceRequestId),
);

app.get('/health', (_request, response) => {
  response.json({ status: 'ok', service: 'realtime', timestamp: new Date().toISOString() });
});

io.use(async (socket, next) => {
  const token = socket.handshake.auth?.token;
  const userId = gateway.authorize(socket) && typeof token === 'string'
    ? await authorizer.identity(token)
    : null;

  if (userId) {
    socket.data.userId = userId;
  }

  next(userId ? undefined : new Error('UNAUTHORIZED_SOCKET'));
});
io.on('connection', (socket) => gateway.registerHandlers(socket));

const handleEvent = async (event: Parameters<RoomGateway['publish']>[0]): Promise<void> => {
  gateway.publish(event);
};
const redisConsumer = new RedisEventConsumer(redisUrl, eventChannel, handleEvent);
const locationConsumer = new RedisEventConsumer(redisUrl, locationChannel, handleEvent);

await redisConsumer.start();
await locationConsumer.start();

server.listen(port, () => {
  console.log('Realtime service listening on http://127.0.0.1:' + port);
});

const shutdown = async (): Promise<void> => {
  await redisConsumer.stop();
  await locationConsumer.stop();
  io.close();
  server.close();
};

process.once('SIGINT', () => void shutdown());
process.once('SIGTERM', () => void shutdown());
