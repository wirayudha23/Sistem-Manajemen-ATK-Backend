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

    /**
     * 1. Format nomor telepon ke internasional.
     *    Contoh: '08123456789' → '628123456789'
     */
    public function formatPhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        return $digits;
    }

    /**
     * 2. Bangun pesan reorder dari model Reorder.
     */
    public function buildReorderMessage(Reorder $reorder): string
    {
        $reorder->load('items.product');

        // $reorderDate = $reorder->reorder_date instanceof Carbon
        //     ? $reorder->reorder_date
        //     : Carbon::parse($reorder->reorder_date);

        // $deliveryDate = $reorder->delivery_date instanceof Carbon
        //     ? $reorder->delivery_date
        //     : Carbon::parse($reorder->delivery_date);

        $lines = [
            '*✅ Permintaan Pengadaan Barang*',
            'Tanggal Permintaan : ' . $reorder->reorder_date->format('d M Y'),
            'Tanggal Diharapkan : ' . $reorder->delivery_date->format('d M Y'),
            '',
            '*Barang:*',
        ];

        foreach ($reorder->items as $detail) {
            $lines[] = "- {$detail->product->name} × {$detail->reorder_quantity}";
        }

        return implode("\n", $lines);
    }

    /**
     * 3. Kirim pesan teks via Fonnte API.
     *
     * @param  string  $to      Nomor tujuan internasional (e.g. '628123456789')
     * @param  string  $message Isi pesan
     * @return array            Hasil decode JSON response
     */
    public function sendMessage(string $to, string $message): array
    {
        $response = $this->client->post('/send', [
            'headers' => ['Authorization' => $this->token,],
            'form_params' => ['target' => $to, 'message' => $message,],
            'http_errors' => true,
        ]);

        $body = json_decode((string) $response->getBody(), true);
        \Log::debug('[FONNTE RESPONSE]', $body);

        if (($body['status'] ?? false) !== true) {
        // ambil 'detail' kalau ada, atau dump seluruh body
        $err = $body['detail'] ?? json_encode($body);
        throw new \RuntimeException('Fonnte API error: ' . $err);
    }

        return $body;
    }
}
