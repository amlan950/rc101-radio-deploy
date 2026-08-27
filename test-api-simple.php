<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Simple API Test</h1>
        
        <button id="test-btn" class="btn btn-primary mb-3">Test API</button>
        
        <div id="loading" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p>Loading...</p>
        </div>
        
        <div id="results" class="mt-3"></div>
        
        <div id="error" class="alert alert-danger" style="display: none;"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            
            const testBtn = document.getElementById('test-btn');
            if (testBtn) {
                testBtn.addEventListener('click', testAPI);
            }
        });
        
        function testAPI() {
            console.log('Testing API...');
            
            const loading = document.getElementById('loading');
            const results = document.getElementById('results');
            const error = document.getElementById('error');
            
            loading.style.display = 'block';
            results.style.display = 'none';
            error.style.display = 'none';
            results.innerHTML = '';
            
            fetch('/api/concerts/homepage')
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('API Data:', data);
                    loading.style.display = 'none';
                    
                    let html = '<h3>API Response:</h3>';
                    html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    
                    if (data.success && data.concerts) {
                        html += '<h4>Concerts Found: ' + data.concerts.length + '</h4>';
                        data.concerts.forEach((concert, index) => {
                            html += '<div class="card mb-2">';
                            html += '<div class="card-body">';
                            html += '<h5>' + concert.name + '</h5>';
                            html += '<p><strong>Artist:</strong> ' + (concert.artist || 'N/A') + '</p>';
                            html += '<p><strong>Date:</strong> ' + (concert.date || 'N/A') + '</p>';
                            html += '<p><strong>Venue:</strong> ' + (concert.venue?.name || 'N/A') + '</p>';
                            html += '<p><strong>Image:</strong> <img src="' + (concert.image || '') + '" style="max-width: 100px;"></p>';
                            html += '</div></div>';
                        });
                    }
                    
                    results.innerHTML = html;
                    results.style.display = 'block';
                })
                .catch(err => {
                    console.error('Error:', err);
                    loading.style.display = 'none';
                    error.textContent = 'Error: ' + err.message;
                    error.style.display = 'block';
                });
        }
    </script>
</body>
</html>
