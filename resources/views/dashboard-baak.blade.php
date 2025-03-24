<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BAAK</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card shadow-lg p-4 text-center" style="width: 400px;">
            <h2 class="mb-4">Dashboard BAAK</h2>
            <img src="{{ auth()->user()->avatar }}" alt="Avatar" class="rounded-circle border mx-auto d-block" width="100">
            <h4 class="mt-3">{{ auth()->user()->name }}</h4>
            <p class="text-muted">{{ auth()->user()->email }}</p>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-danger w-100">Logout</button>
            </form>
        </div>
    </div>
</body>
</html>
