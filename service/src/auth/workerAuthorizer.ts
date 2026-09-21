export class WorkerAuthorizer {
  public constructor(private readonly workerApiUrl: string) {}

  public async authenticate(token: string): Promise<boolean> {
    return this.authorized('/me', token);
  }

  public async canJoin(token: string, serviceRequestId: string): Promise<boolean> {
    return this.authorized('/service-requests/' + encodeURIComponent(serviceRequestId), token);
  }

  private async authorized(path: string, token: string): Promise<boolean> {
    try {
      const response = await fetch(this.workerApiUrl.replace(/\/$/, '') + path, {
        headers: {
          Accept: 'application/json',
          Authorization: 'Bearer ' + token,
        },
        signal: AbortSignal.timeout(5_000),
      });

      return response.ok;
    } catch {
      return false;
    }
  }
}
