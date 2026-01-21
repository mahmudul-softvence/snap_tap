<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect Facebook Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
<div class="container my-5">
    <h3>Connect a Facebook Page</h3>

    <div id="message"></div>

    @if($success ?? false && count($pages) > 0)
        <form id="connectPageForm">
            @csrf
            <input type="hidden" name="page_token" id="page_token" value="{{ $user_access_token }}">
            <input type="hidden" name="user_id"  value="{{ $user_id }}">

            @foreach($pages as $page)
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="selected_page" id="page{{ $page['id'] }}"
                           value="{{ $page['id'] }}" data-name="{{ $page['name'] }}" required>
                    <label class="form-check-label" for="page{{ $page['id'] }}">
                        {{ $page['name'] }}
                    </label>
                </div>
            @endforeach

            <button type="submit" class="btn btn-primary mt-3" id="submitBtn">Connect Page</button>
        </form>
    @else
        <div class="alert alert-danger">{{ $message ?? 'No pages found' }}</div>
    @endif
</div>
 
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
    const form = document.getElementById('connectPageForm');
    const messageDiv = document.getElementById('message');

    form?.addEventListener('submit', function(e) {
        e.preventDefault();

        const selectedRadio = document.querySelector('input[name="selected_page"]:checked');
        if (!selectedRadio) return;

        const pageId = selectedRadio.value;
        const pageName = selectedRadio.dataset.name;
        const pageToken = document.getElementById('page_token').value;

        // Axios POST request
        axios.post('/api/facebook/connect-page', {
            page_id: pageId,
            page_name: pageName,
            page_token: pageToken
        }, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(function(response) {
            if (response.data.success) {
                messageDiv.innerHTML = `<div class="alert alert-success">${response.data.message}</div>`;
            } else {
                messageDiv.innerHTML = `<div class="alert alert-danger">Something went wrong</div>`;
            }
        })
        .catch(function(error) {
            messageDiv.innerHTML = `<div class="alert alert-danger">${error.response?.data?.message || 'Error connecting page'}</div>`;
        });
    });
</script>
</body>
</html>
