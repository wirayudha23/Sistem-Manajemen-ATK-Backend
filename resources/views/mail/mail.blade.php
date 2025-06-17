<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Informasi Pengambilan ATK</title>
</head>
<body>
    <h2>Halo, {{ $user->name }}</h2>

    <p>Ini adalah pemberitahuan bahwa Anda telah melakukan pengambilan ATK pada <strong>{{ $checkout->checkout_date->format('d M Y') }}</strong>.</p>

    <p>Kebutuhan: {{ $checkout->purpose->name }}</p>

    <br>

    <p>Berikut detail barang yang diambil:</p>
    <ul>
        @foreach ($items as $item)
            <li>{{ $item->product->name }} &times; {{ $item->checkout_quantity }}</li>
        @endforeach
    </ul>

    @if($checkout->description)
        <p>Deskripsi: {{ $checkout->description }}</p>
    @endif

    <br>
    <p>Terima kasih,</p>
    <p>ATK BAAK Politeknik Caltex Riau</p>
</body>
</html>
