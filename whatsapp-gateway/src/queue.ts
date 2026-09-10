export interface QueuedMessage {
  idempotencyKey?: string;
  jid: string;
  type: 'TEXT' | 'DOCUMENT';
  text?: string;
  documentUrl?: string;
  fileName?: string;
  mimetype?: string;
}

/**
 * Outbound message rate limiter queue with anti-ban human-like jitter.
 * Prevents account ban by avoiding rapid burst message replies on WhatsApp Web/Baileys.
 */
export class OutboundQueue {
  private queue: QueuedMessage[] = [];
  private isProcessing = false;
  private sendHandler: (msg: QueuedMessage) => Promise<void>;
  private readonly sent = new Set<string>();
  private readonly minDelay = Number(process.env.OUTBOUND_MIN_DELAY_MS || 700);
  private readonly maxDelay = Number(process.env.OUTBOUND_MAX_DELAY_MS || 1400);

  constructor(sendHandler: (msg: QueuedMessage) => Promise<void>) {
    this.sendHandler = sendHandler;
  }

  public enqueue(msg: QueuedMessage): void {
    if (msg.idempotencyKey && this.sent.has(msg.idempotencyKey)) return;
    if (msg.idempotencyKey) this.sent.add(msg.idempotencyKey);
    this.queue.push(msg);
    if (!this.isProcessing) {
      this.processQueue();
    }
  }

  private async processQueue(): Promise<void> {
    if (this.queue.length === 0) {
      this.isProcessing = false;
      return;
    }

    this.isProcessing = true;
    const current = this.queue.shift();

    if (current) {
      try {
        await this.sendHandler(current);
      } catch (err) {
        console.error(`[Queue] Failed to send message to ${current.jid}:`, err);
      }

      // Human-like random jitter: 600ms - 1400ms delay between consecutive messages
      const jitterMs = Math.floor(Math.random() * Math.max(1, this.maxDelay - this.minDelay)) + this.minDelay;
      await new Promise((resolve) => setTimeout(resolve, jitterMs));
    }

    this.processQueue();
  }
}
