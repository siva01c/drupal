# {{project_name}}

{{project_type_label}} built with [Startup Factory](https://startupfactory.dev).

## Quick Start

1. Clone this repository
2. Copy `.env.example` to `.env` and configure your settings
3. Run `docker compose up -d`
4. Visit `http://{{project_slug}}.localhost`

## Requirements

- Docker & Docker Compose
- Git

## Project Structure

```
{{project_slug}}/
├── docker-compose.yml          # Docker services
├── Dockerfile                  # PHP-FPM image
├── .env.example               # Environment template
├── config/sync/               # Drupal configuration
├── web/                       # Drupal webroot
│   └── modules/custom/        # Custom modules
└── .github/workflows/         # CI/CD pipeline
```

## Deployment

### Option 1: VPS with ops-proxy (Recommended)

1. Push to your Git repository
2. Deploy to your VPS using the included CI/CD pipeline
3. The ops-proxy will automatically configure SSL

### Option 2: Manual Deployment

1. Clone on your VPS
2. Run `docker compose up -d`
3. Configure your domain in `.env`

## Development

### Local Development

```bash
docker compose up -d
docker compose exec drush cr
docker compose exec drush updb -y
```

### Accessing Services

- **Drupal:** http://{{project_slug}}.localhost
- **Admin:** http://{{project_slug}}.localhost/user/login
- **Mailhog:** http://localhost:8025

## Configuration

Configuration is managed via Drupal's config sync system. Edit files in `config/sync/` and import:

```bash
docker compose exec drush config:import -y
```

## AI Chat Support

This project includes an AI chat widget powered by [RagChat](https://ragchat.dev). The chat can help you:

- **Configure your project** - Add pages, change layouts, configure payments
- **Get support** - Ask questions about your project setup
- **Manage content** - Create and edit content using natural language

### Chat Widget

The chat widget is automatically loaded on your site. Click the chat icon in the bottom-right corner to start a conversation.

### Configuration

Set the following environment variables in your `.env` file:

```bash
RAGCHAT_BASE_URL=http://your-ragchat-instance:8080
RAGCHAT_API_TOKEN=your-api-token
```

## License

MIT License. See [LICENSE](LICENSE) for details.

---

Built with [Startup Factory](https://startupfactory.dev) - Open-source MVP platform for web, e-commerce, chatbots, and SaaS.
