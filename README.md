It looks like you are working with a Laravel package called **LaraSwagger**, which automatically generates Swagger/OpenAPI documentation for your Laravel API. Below is a detailed breakdown of the package features, installation instructions, configuration, and usage steps, along with the corresponding code files.

### **Features of LaraSwagger:**

1. **Auto-detect API routes** (`api/*` routes).
2. **Detect route parameters** and **query parameters** from controller methods.
3. **Extract validation rules** from `FormRequest` classes automatically.
4. **Auto-capture GET responses** for example data.
5. **Supports authentication** (via Bearer token or login endpoint).
6. **Laravel auto-discovery** via a service provider.

---

### **Installation Instructions:**

To install **LaraSwagger** via Composer directly from Git, follow these steps:


1. **Require the package using Composer:**

   Run the following command in your terminal to install the package:

   ```bash
   composer require abdo-taher/laraswagger:dev-main
   ```

2. **Publish the config file:**

   Publish the configuration file for the package to customize settings:

   ```bash
   php artisan vendor:publish --provider="LaraSwagger\LaraSwaggerServiceProvider" --tag="config"
   ```

---

### **Usage Instructions:**

Once installed, you can generate the Swagger JSON file by running the following Artisan command:

```bash
php artisan api:docs-json
```

This command will automatically generate the Swagger/OpenAPI JSON file that contains all your API routes and validation rules. You can pass several options to configure the command further:

It looks like you might be referring to the **options** available in the `php artisan api:docs-json` command from the **LaraSwagger** package. Here’s a breakdown of the options you can use when running the command:

It seems like you are presenting the options for the `php artisan api:docs-json` command, and you're looking to verify or format it. The content you've provided looks mostly correct, but I'll format it in a cleaner, more readable way, and ensure it's consistent with typical markdown and table formatting.

Here’s the properly formatted version:

---

### **Options for `php artisan api:docs-json`**

- **`--path`**: This option allows you to specify the location where the Swagger JSON documentation will be saved. The default is `public/api-docs.json`.
  
- **`--base-url`**: Defines the base URL for all the endpoints documented. If left empty, it defaults to the `app.url` config in Laravel.

- **`--capture-response`**: When set to `true`, the command will attempt to capture real responses for GET requests, which are used to generate example response data in the Swagger documentation. By default, it is enabled.

- **`--timeout`**: Sets how long the command should wait for a response from the API when generating the docs. The default is 15 seconds.

- **`--auth-mode`**: This option defines the authentication method to be used:
  - **`none`**: No authentication is used.
  - **`manual`**: Use a manually provided Bearer token (via `--token`).
  - **`login`**: Automatically logs in via a login endpoint and retrieves a Bearer token.

- **`--token`**: This is only relevant if `--auth-mode=manual` is used. Here, you provide a pre-generated Bearer token for authentication.

- **`--token-type`**: This is the prefix for the token used in Bearer authentication, such as `Bearer` or `JWT`.

- **`--login-url`**: Specifies the URL for the login endpoint when using `--auth-mode=login`. It defaults to `/api/login`.

- **`--login-method`**: Defines the HTTP method (`POST` or `GET`) for the login request.

- **`--login-email` and `--login-password`**: These are the credentials used to log in if `--auth-mode=login` is selected.

- **`--token-key`**: Specifies which key in the response contains the Bearer token when using `--auth-mode=login`. By default, it expects the key to be `token`.

- **`--skip`**: Allows you to specify routes that should be excluded from the generated Swagger documentation. You pass a comma-separated list of route keywords that should be skipped.

---

### **Examples:**

#### 1. **Basic Example:**

Generate the Swagger JSON file using default settings:

```bash
php artisan api:docs-json
```

This will generate the Swagger documentation and save it to the `public/api-docs.json` file.

#### 2. **Custom Output Path:**

If you want to specify a custom path for the output JSON file, you can use the `--path` option:

```bash
php artisan api:docs-json --path=public/swagger/api-docs.json
```

#### 3. **Using Base URL:**

To set a custom base URL for your API documentation:

```bash
php artisan api:docs-json --base-url=http://127.0.0.1:8000
```

This is useful if your app is running on a different URL or port than the default Laravel environment.

#### 4. **Authentication (Login Mode):**

If your API requires authentication and you want LaraSwagger to automatically log in and use the generated token, you can configure `--auth-mode=login`. Make sure your login endpoint is configured in the `config/laraswagger.php` file.

```bash
php artisan api:docs-json --auth-mode=login --login-url=/api/login --login-email=docs@local.test --login-password=12345678
```

This will log in using the credentials and capture the token to be used for subsequent requests.

#### 5. **Skip Routes:**

To exclude certain routes from the generated Swagger documentation, use the `--skip` option. You can pass a comma-separated list of route keywords to skip.

```bash
php artisan api:docs-json --skip=auth,admin
```

This would skip any routes with the keywords `auth` or `admin` in their route names.

---

If you meant something else by "lock for options," let me know, and I’ll clarify further!