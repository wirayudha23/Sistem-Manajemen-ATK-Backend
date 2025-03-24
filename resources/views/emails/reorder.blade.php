<!DOCTYPE html>
<html>
<head>
    <title>Permintaan Reorder</title>
</head>
<body>
    <h2>Permintaan Reorder Baru</h2>

    <p>Tanggal Reorder: {{ $reorder->reorder_date }}</p>

    <h3>Detail Barang:</h3>
    <table border="1" cellpadding="10">
        <thead>
            <tr>
                <th>Nama Barang</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reorder->items as $item)
            <tr>
                <td>{{ $item->product->name }}</td>
                <td>{{ $item->reorder_quantity }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p>Silakan lakukan proses pembelian sesuai dengan permintaan di atas.</p>
</body>
</html>
