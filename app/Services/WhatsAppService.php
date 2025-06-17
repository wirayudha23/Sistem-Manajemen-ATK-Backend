<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Models\Reorder;
use Carbon\Carbon;

class WhatsAppService
{
    protected Client $client;
    protected string $token;
    protected string $baseUrl;

    public function __construct()
    {
        $this->token = config('services.fonnte.token');
        $this->baseUrl = config('services.fonnte.base_url');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 10,
        ]);
    }

    public function formatPhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        return $digits;
    }

    /**
     * Build WhatsApp message for a new reorder (Option 1 template)
     * Menampilkan original price, total per item, dan total reorder price
     */
    public function buildReorderMessage(Reorder $reorder): string
    {
        $reorder->load('user', 'items.product');

        // Header
        $lines = [
            '*✅ PERMINTAAN PENGADAAN BARANG*',
            "📦 Kode Pengadaan: *{$reorder->reorder_code}*",
            '────────────────────────────',
            // 'Hai ' . $reorder->user->name . ',',
            '',
            '🗓️ Tanggal Permintaan : ' . $reorder->reorder_date->format('d M Y'),
            '🚚 Tanggal Diharapkan : ' . $reorder->delivery_date->format('d M Y'),
            '',
            '*Daftar Barang:*',
        ];

        // Item list with pricing
        $totalReorder = 0;
        foreach ($reorder->items as $detail) {
            $name = $detail->product->name;
            $qty = $detail->reorder_quantity;
            $price = $detail->product->price; // as integer
            $itemTotal = $qty * $price;
            $totalReorder += $itemTotal;

            $formattedPrice = 'Rp ' . number_format($price, 0, ',', '.');
            $formattedItemTotal = 'Rp ' . number_format($itemTotal, 0, ',', '.');

            $lines[] = sprintf(
                "• %s × %d @ %s = %s  ",
                $name,
                $qty,
                $formattedPrice,
                $formattedItemTotal
            );
        }

        // Total reorder price
        $lines[] = '';
        $lines[] = '*Total Harga:* ' . 'Rp ' . number_format($totalReorder, 0, ',', '.');
        $lines[] = '';

        // Footer
        $lines[] = 'Silakan konfirmasi segera.';
        $lines[] = 'Terima kasih!';

        return implode("\n", $lines);
    }

    /**
     * Build WhatsApp message for a canceled reorder (Option 1 template)
     */

    public function buildCancelMessage(Reorder $reorder): string
    {
        $reorder->load(['items.product']);

        // Header
        $lines = [
            '*~~❌ PEMBATALAN PENGADAAN BARANG~~*',
            "📦 Kode Pengadaan: *{$reorder->reorder_code}*",
            '────────────────────────────',
            // 'Hai ' . $reorder->user->name . ',',
            '',
            '**Permintaan pengadaan telah dibatalkan.**',
            '',
            '*Rincian Permintaan Semula:*',
            "~~🗓️ Tanggal Permintaan : {$reorder->reorder_date->format('d M Y')}~~",
            "~~🚚 Tanggal Diharapkan : {$reorder->delivery_date->format('d M Y')}~~",
            '',
            '*Daftar Barang:*',
        ];

        // Hitung total harga semula
        $totalReorder = 0;
        foreach ($reorder->items as $detail) {
            $name = $detail->product->name;
            $qty = $detail->reorder_quantity;
            $price = $detail->product->price;
            $itemTotal = $qty * $price;
            $totalReorder += $itemTotal;

            $formattedPrice = 'Rp ' . number_format($price, 0, ',', '.');
            $formattedItemTotal = 'Rp ' . number_format($itemTotal, 0, ',', '.');

            // Strikethrough setiap baris item
            $lines[] = sprintf(
                "~~• %s × %d @ %s = %s~~",
                $name,
                $qty,
                $formattedPrice,
                $formattedItemTotal
            );
        }

        // Total harga semula
        $lines[] = '';
        $lines[] = "*~~Total Harga: Rp " . number_format(
            $reorder->total_reorder_price ?? $totalReorder,
            0,
            ',',
            '.'
        ) . "~~*";
        $lines[] = '';
        $lines[] = 'Terima kasih atas perhatiannya.';

        return implode("\n", $lines);
    }


    public function buildUpdateMessage(Reorder $reorder, array $diff): string
    {
        $reorder->load('items.product', 'user');

        $lines = [
            '*📢 PEMBARUAN PENGADAAN BARANG*',
            "📦 Kode Pengadaan: *{$reorder->reorder_code}*",
            '────────────────────────────',
            'Hai ' . $reorder->user->name . ',',
            '',
            '🗓️ Tanggal Permintaan : ' . $reorder->reorder_date->format('d M Y'),
        ];

        // 1) Siapkan string untuk "Tanggal Diharapkan"
        if (isset($diff['delivery_date'])) {
            $from = Carbon::parse($diff['delivery_date']['from'])->format('d M Y');
            $to = Carbon::parse($diff['delivery_date']['to'])->format('d M Y');
            $deliveryLine = "{$from} → {$to}";
        } else {
            $deliveryLine = $reorder->delivery_date->format('d M Y');
        }

        // 2) Tambahkan baris tunggal untuk Tanggal Diharapkan
        $lines[] = '';
        $lines[] = '🚚 Tanggal Diharapkan : ' . $deliveryLine;
        $lines[] = '';
        $lines[] = '*Daftar Barang:*';

        // 3) Tampilkan daftar item dengan diff jika ada
        $itemsById = $reorder->items->keyBy('product_id');

        // Semua key produk yang pernah ada di diff
        $allPids = array_unique(array_merge(
            array_keys($itemsById->toArray()),
            array_keys($diff['items'] ?? [])
        ));

        foreach ($allPids as $pid) {
            $oldQty = $diff['items'][$pid]['from'] ?? null;
            $newQty = $diff['items'][$pid]['to'] ?? ($itemsById->has($pid) ? $itemsById[$pid]->reorder_quantity : 0);

            // Nama produk: jika sudah dihapus, ambil nama dari model lama via diff (bisa disesuaikan)
            $name = $itemsById->has($pid)
                ? $itemsById[$pid]->product->name
                : ($diff['items'][$pid]['name'] ?? 'Produk ID ' . $pid);

            // Tampilkan perubahan atau stok awal saja
            if ($oldQty !== null && $oldQty !== $newQty) {
                $lines[] = "• {$name}: {$oldQty} → {$newQty}";
            } else {
                $lines[] = "• {$name}: {$newQty}";
            }
        }

        $lines[] = '';
        $lines[] = 'Terima kasih!';

        return implode("\n", $lines);
    }



    public function sendMessage(string $to, string $message): array
    {
        $response = $this->client->post('/send', [
            'headers' => ['Authorization' => $this->token],
            'form_params' => ['target' => $to, 'message' => $message],
            'http_errors' => true,
        ]);

        $body = json_decode((string) $response->getBody(), true);
        \Log::debug('[FONNTE RESPONSE]', $body);

        if (($body['status'] ?? false) !== true) {
            $err = $body['detail'] ?? json_encode($body);
            throw new \RuntimeException('Fonnte API error: ' . $err);
        }

        return $body;
    }
}
