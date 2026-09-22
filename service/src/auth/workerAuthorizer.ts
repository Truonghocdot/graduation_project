export class WorkerAuthorizer {
  public constructor(private readonly workerApiUrl: string) {}

  public async authenticate(token: string): Promise<boolean> {
    return (await this.identity(token)) !== null;
  }

  public async identity(token: string): Promise<string | null> {
    try {
      const response = await this.request('/me', token);

      if (!response.ok) {
        return null;
      }

      const payload = await response.json() as { data?: { id?: unknown } };

      return typeof payload.data?.id === 'string' ? payload.data.id : null;
    } catch {
      return null;
    }
  }

  public async canJoin(token: string, serviceRequestId: string): Promise<boolean> {
    return this.authorized(
      '/service-requests/' + encodeURIComponent(serviceRequestId) + '/realtime-access',
      token,
    );
  }

  private async authorized(path: string, token: string): Promise<boolean> {
    try {
      const response = await this.request(path, token);

      return response.ok;
    } catch {
      return false;
    }
  }

  private request(path: string, token: string): Promise<Response> {
    return fetch(this.workerApiUrl.replace(/\/$/, '') + path, {
      headers: {
        Accept: 'application/json',
        Authorization: 'Bearer ' + token,
      },
      signal: AbortSignal.timeout(5_000),
    });
  }
}
