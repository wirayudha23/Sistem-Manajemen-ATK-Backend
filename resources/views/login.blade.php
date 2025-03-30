<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Manajemen ATK BAAK PCR</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">
  <div class="d-flex align-items-center">
    <!-- Gambar di sebelah kiri -->
    <img src="https://i.redd.it/oh2ho-oh4s-jane-drawings-are-so-adorable-v0-z04jurt1jb1e1.png?width=669&format=png&auto=webp&s=bd47fe63be880364c3d646e014f5e3a77ec7db9c" alt="Logo" class="me-4 rounded" width="400">

    <!-- Card Login -->
    <div class="card text-center p-4 shadow-lg" style="width: 350px;">
      <h2 class="mb-3">Manajemen ATK BAAK PCR</h2>
      <p class="text-muted">Silakan login menggunakan akun Google</p>
      <a href="{{ route('redirect') }}" class="btn btn-danger w-100">
        {{-- <img src="" width="20" class="me-2" alt="Icon Google"> --}}
        Login dengan Google
      </a>
    </div>
  </div>
</body>
</html>
