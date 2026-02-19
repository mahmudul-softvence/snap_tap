<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SnapTap - Under Development</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Styles -->
    <style>
        body,
        html {
            height: 100%;
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #f8f9fa;
        }

        .under-construction {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            text-align: center;
            padding: 20px;
        }

        .under-construction h1 {
            font-size: 3rem;
            color: #343a40;
        }

        .under-construction p {
            font-size: 1.2rem;
            color: #6c757d;
        }

        .social-icons a {
            margin: 0 10px;
            color: #343a40;
            font-size: 1.5rem;
            transition: color 0.3s;
        }

        .social-icons a:hover {
            color: #0d6efd;
        }

        .btn-notify {
            margin-top: 20px;
        }
    </style>
</head>

<body>

    <div class="under-construction">
        {{-- <img src="https://via.placeholder.com/150" alt="Logo" class="mb-4"> --}}
        <h1>Coming Soon!</h1>
        <p>We are working hard to launch our website. Stay tuned!</p>
        <p>{{ app()->version() }}</p>

        <a href="#" class="btn btn-primary btn-notify">Notify Me</a>

        <div class="social-icons mt-4">
            <a href="#" target="_blank"><i class="bi bi-facebook"></i></a>
            <a href="#" target="_blank"><i class="bi bi-twitter"></i></a>
            <a href="#" target="_blank"><i class="bi bi-instagram"></i></a>
        </div>
    </div>

    <!-- Bootstrap JS + Icons -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</body>

</html>
