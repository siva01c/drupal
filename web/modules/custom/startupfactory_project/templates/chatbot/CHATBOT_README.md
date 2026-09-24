# AI Chatbot Template

This is a Drupal 11 chatbot template with AI capabilities.

## Included Modules

- Core Drupal 11
- Simple OAuth (API authentication)
- Custom AI Chat module

## Configuration

### AI Provider
Configure your AI provider in `settings.php`:
```php
$settings['ai_api_key'] = 'your-api-key';
$settings['ai_model'] = 'gpt-4';
```

### Chat Interface
The chat interface is available at `/chat` for authenticated users.

## API Endpoints

- `POST /api/chat` - Send a message to the chatbot
- `GET /api/chat/history` - Get chat history

## Customization

Edit the custom module in `web/modules/custom/` to:
- Add new intents
- Customize responses
- Integrate with external APIs
