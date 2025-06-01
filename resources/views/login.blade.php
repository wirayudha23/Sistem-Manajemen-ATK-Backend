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
    <img
    src="{{ asset('storage/images/login-art.jpg')}}"
    alt="Login art"
    class="me-4 rounded"
    width="400"
    >
    {{-- <!-- Video YouTube -->
    <div class="me-4">
      <iframe width="400" height="225" src="https://www.youtube.com/embed/lB8ASupNtlw"
        title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen>
      </iframe>
    </div> --}}

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
