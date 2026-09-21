import { Redis } from 'ioredis';

export interface DriverPresence {
  driverProfileId: number;
  latitude: number;
  longitude: number;
  serviceTypes: string[];
  expiresAt: number;
}

export class DriverPresenceStore {
  private readonly prefix = 'driver:presence:';

  public constructor(private readonly redis: Redis) {}

  public async get(driverProfileId: number): Promise<DriverPresence | null> {
    const value = await this.redis.get(this.prefix + driverProfileId);

    if (!value) {
      return null;
    }

    try {
      return JSON.parse(value) as DriverPresence;
    } catch {
      return null;
    }
  }

  public async markOffline(driverProfileId: number): Promise<void> {
    await this.redis.del(this.prefix + driverProfileId);
  }
}
