<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FinanceSimulateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:simulate 
                            {message? : Optional single message to process without starting interactive session}
                            {--jid=628123456789@s.whatsapp.net : Simulated WhatsApp JID}
                            {--name=Teguh : Simulated WhatsApp push name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactive terminal chat simulator for 100% offline WhatsApp Finance Bot testing';

    /**
     * Execute the console command.
     */
    public function handle(WebhookController $controller): int
    {
        $jid      = $this->option('jid');
        $pushName = $this->option('name');
        $singleMsg = $this->argument('message');

        if ($singleMsg) {
            $this->processMessage($controller, $jid, $pushName, $singleMsg);
            return self::SUCCESS;
        }

        $this->outputBanner($jid, $pushName);

        while (true) {
            $input = $this->ask("💬 [{$pushName}] Kirim pesan");

            if ($input === null) {
                continue;
            }

            $trimmed = trim($input);

            if (in_array(strtolower($trimmed), ['exit', 'quit', 'q'], true)) {
                $this->info("👋 Simulator ditutup. Sampai jumpa!");
                break;
            }

            if ($trimmed === '') {
                continue;
            }

            $this->processMessage($controller, $jid, $pushName, $trimmed);
        }

        return self::SUCCESS;
    }

    private function processMessage(WebhookController $controller, string $jid, string $pushName, string $message): void
    {
        $messageId = 'SIM_' . Str::random(16);
        $secret    = config('app.gateway_webhook_secret', env('GATEWAY_WEBHOOK_SECRET', 'local_dev_secret_12345'));

        $request = Request::create('/api/webhook/whatsapp', 'POST', [
            'message_id'   => $messageId,
            'from_jid'     => $jid,
            'push_name'    => $pushName,
            'message_text' => $message,
            'timestamp'    => time(),
        ]);

        if ($secret) {
            $request->headers->set('X-Gateway-Secret', $secret);
        }

        $start = microtime(true);
        $response = $controller->handle($request);
        $latencyMs = round((microtime(true) - $start) * 1000, 1);

        $data = $response->getData(true);

        $this->line("");
        $this->line("<fg=yellow>🤖 Bot WA Reply ({$latencyMs}ms):</>");
        $this->line("<fg=gray>────────────────────────────────────────────</>");

        $action = $data['action'] ?? 'UNKNOWN';

        if ($action === 'REPLY_TEXT') {
            $this->line("<fg=green>" . ($data['reply_text'] ?? '') . "</>");
        } elseif ($action === 'SEND_DOCUMENT') {
            $this->line("<fg=cyan>" . ($data['reply_text'] ?? '') . "</>");
            $this->line("<fg=yellow>📎 File: " . ($data['file_name'] ?? '') . "</>");
            $this->line("<fg=blue>🔗 URL : " . ($data['file_url'] ?? '') . "</>");
        } elseif ($action === 'IGNORE') {
            $this->line("<fg=gray>[Pesan diabaikan: " . ($data['reason'] ?? '') . "]</>");
        } else {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $this->line("<fg=gray>────────────────────────────────────────────</>");
        $this->line("");
    }

    private function outputBanner(string $jid, string $pushName): void
    {
        $this->info("╔════════════════════════════════════════════════════════════════╗");
        $this->info("║  🤖 WhatsApp Personal Finance Simulator (100% Offline Mode)   ║");
        $this->info("╚════════════════════════════════════════════════════════════════╝");
        $this->line("<fg=gray>User: <fg=white>{$pushName}</> | JID: <fg=white>{$jid}</></>");
        $this->line("<fg=gray>Ketik pesan alami seperti:</>");
        $this->line("  • <fg=cyan>keluar 25000 makan siang</>  | <fg=cyan>+1.5jt gaji</>");
        $this->line("  • <fg=cyan>saldo</>                      | <fg=cyan>rekap hari ini</> | <fg=cyan>rekap bulan ini</>");
        $this->line("  • <fg=cyan>batal</>                      | <fg=cyan>export excel</>   | <fg=cyan>bantuan</>");
        $this->line("<fg=gray>Ketik <fg=red>'exit'</> atau <fg=red>'quit'</> untuk keluar.</>");
        $this->line("");
    }
}
