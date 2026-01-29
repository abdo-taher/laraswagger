<!DOCTYPE html>
<html>
<head>
    <title>API Docs</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
<div id="swagger-ui"></div>

<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script>
window.onload = function() {
    SwaggerUIBundle({
        url: "{{ url('/openapi.json') }}",
        dom_id: '#swagger-ui',

        // ✅ search/filter
        filter: "",

        deepLinking: true,
        docExpansion: "none",
        persistAuthorization: true,
    });
}
</script>
</body>
</html>
