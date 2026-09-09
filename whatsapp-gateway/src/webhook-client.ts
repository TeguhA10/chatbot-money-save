import axios from 'axios';

export interface WebhookPayload {
  message_id: string;
  from_jid: string;
  push_name?: string;
  message_text: string;
  timestamp?: number;
}

export interface WebhookResponse {
  action: 'REPLY_TEXT' | 'SEND_DOCUMENT' | 'IGNORE';
  reply_text?: string;
  file_url?: string;
  file_name?: string;
  mimetype?: string;
  reason?: string;
}

export class WebhookClient {
  private url: string;
  private secret: string;

  constructor(url: string, secret: string) {
    this.url = url;
    this.secret = secret;
  }

  public async forwardMessage(payload: WebhookPayload): Promise<WebhookResponse | null> {
    try {
      const response = await axios.post<WebhookResponse>(this.url, payload, {
        headers: {
          'Content-Type': 'application/json',
          'X-Gateway-Secret': this.secret,
        },
        timeout: 10000,
      });

      return response.data;
    } catch (err: any) {
      if (err.response) {
        console.error(`[Webhook] Laravel returned HTTP ${err.response.status}:`, err.response.data);
      } else {
        console.error(`[Webhook] Failed to connect to Laravel:`, err.message);
      }
      return null;
    }
  }
}
