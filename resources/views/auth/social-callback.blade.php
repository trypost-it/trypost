<!DOCTYPE html>
<html>
<head>
    <title>{{ $success ? 'Connected' : 'Error' }}</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f9fafb;
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .message {
            color: #374151;
            font-size: 1.125rem;
        }
        .submessage {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">{{ $success ? '✓' : '✕' }}</div>
        <div class="message">{{ $message }}</div>
        <div class="submessage">This window will close automatically...</div>
    </div>

    <script>
        (function() {
            const result = {
                success: {{ $success ? 'true' : 'false' }},
                message: @json($message),
                platform: @json($platform ?? null)
            };

            // Try to notify the parent window
            if (window.opener && !window.opener.closed) {
                try {
                    window.opener.postMessage({
                        type: 'social-oauth-callback',
                        ...result
                    }, window.location.origin);
                } catch (e) {
                    // If postMessage fails, just reload the opener
                    try {
                        window.opener.location.reload();
                    } catch (e2) {}
                }
            }

            // Try to close immediately and with delay
            function closeWindow() {
                try {
                    window.open('', '_self', '');
                    window.close();
                } catch (e) {}

                // Fallback: try again
                try {
                    window.close();
                } catch (e) {}
            }

            // Close after a short delay
            setTimeout(closeWindow, 1000);

            // If still open after 2 seconds, show manual close message
            setTimeout(function() {
                if (!window.closed) {
                    document.querySelector('.submessage').textContent = 'You can close this window now.';
                }
            }, 2000);
        })();
    </script>
</body>
</html>
