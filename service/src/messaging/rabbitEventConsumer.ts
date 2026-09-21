import amqp, { Channel, ChannelModel } from 'amqplib';

import { parseWorkerEvent, WorkerEventHandler } from '../contracts/event.js';

export class RabbitEventConsumer {
  private connection?: ChannelModel;
  private channel?: Channel;

  public constructor(
    private readonly url: string,
    private readonly queue: string,
    private readonly onEvent: WorkerEventHandler,
  ) {}

  public async start(): Promise<void> {
    this.connection = await amqp.connect(this.url);
    this.channel = await this.connection.createChannel();
    await this.channel.assertQueue(this.queue, { durable: true });
    await this.channel.consume(this.queue, (message) => {
      if (!message) {
        return;
      }

      const event = parseWorkerEvent(message.content.toString());

      if (!event) {
        this.channel?.nack(message, false, false);
        return;
      }

      void this.onEvent(event).then(
        () => this.channel?.ack(message),
        () => this.channel?.nack(message, false, true),
      );
    });
  }

  public async stop(): Promise<void> {
    await this.channel?.close();
    await this.connection?.close();
  }
}
