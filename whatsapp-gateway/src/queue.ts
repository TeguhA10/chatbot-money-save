export interface QueuedMessage {
  idempotencyKey?: string;
  jid: string;
  type: 'TEXT' | 'DOCUMENT';
  text?: string;
  documentUrl?: string;
  fileName?: string;
  mimetype?: string;
  replyToKey?: any;
  replyToTimestamp?: number;
}

/**
 * Outbound message rate limiter queue with anti-ban human-like jitter.
 * Prevents account ban by avoiding rapid burst message replies on WhatsApp Web/Baileys.
 */
export class OutboundQueue {
  private queue: QueuedMessage[] = [];
  private isProcessing = false;
  private sendHandler: (msg: QueuedMessage) => Promise<void>;
  private readonly sent = new Map<string, number>();
  private readonly minDelay = Number(process.env.OUTBOUND_MIN_DELAY_MS || 700);
  private readonly maxDelay = Number(process.env.OUTBOUND_MAX_DELAY_MS || 1400);

  constructor(sendHandler: (msg: QueuedMessage) => Promise<void>) {
    this.sendHandler = sendHandler;
  }

  public enqueue(msg: QueuedMessage): void {
    const now = Date.now();

    // Clean up expired entries in sent map (> 10 minutes)
    if (this.sent.size > 2000) {
      for (const [key, timestamp] of this.sent.entries()) {
        if (now - timestamp > 600000) {
          this.sent.delete(key);
        }
      }
    }

    // Auto-generate fallback debounce key if idempotencyKey is not provided
    const key = msg.idempotencyKey || (msg.jid && msg.text ? `auto:${msg.jid}:${msg.text.trim().substring(0, 40)}` : undefined);

    if (key) {
      const lastSentTime = this.sent.get(key);
      // Suppress duplicates sent within 5 seconds for auto keys, or anytime within TTL for explicit keys
      const ttl = msg.idempotencyKey ? 600000 : 5000;
      if (lastSentTime && (now - lastSentTime < ttl)) {
        console.warn(`[Queue] Deduplication: Pesan duplikat terdeteksi (${key}). Menolak pengiriman ulang double.`);
        return;
      }
      this.sent.set(key, now);
    }

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
