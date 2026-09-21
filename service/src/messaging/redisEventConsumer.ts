import { Redis } from 'ioredis';

import { parseWorkerEvent, WorkerEventHandler } from '../contracts/event.js';

export class RedisEventConsumer {
  private readonly subscriber: Redis;

  public constructor(
    private readonly redisUrl: string,
    private readonly channel: string,
    private readonly onEvent: WorkerEventHandler,
  ) {
    this.subscriber = new Redis(redisUrl, { lazyConnect: true, maxRetriesPerRequest: 1 });
  }

  public async start(): Promise<void> {
    await this.subscriber.connect();
    await this.subscriber.subscribe(this.channel);
    this.subscriber.on('message', (_channel: string, message: string) => {
      const event = parseWorkerEvent(message);

      if (event) {
        void this.onEvent(event);
      }
    });
  }

  public async stop(): Promise<void> {
    await this.subscriber.unsubscribe(this.channel);
    await this.subscriber.quit();
  }
}
