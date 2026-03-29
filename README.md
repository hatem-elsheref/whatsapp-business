# WhatsApp Business Package

A comprehensive WhatsApp Business SaaS package for Laravel with multi-tenant support, OAuth authentication, conversation management, and more.

## Installation

1. Add the package to your Laravel project:
```bash
composer require whatsapp/business
```

2. Publish the configuration:
```bash
php artisan vendor:publish --tag=whatsapp-config
php artisan vendor:publish --tag=whatsapp-migrations
```

3. Run migrations:
```bash
php artisan migrate
```

## Configuration

Add these environment variables to your `.env` file:

```env
# Meta/WhatsApp Cloud API
META_APP_ID=your_app_id
META_APP_SECRET=your_app_secret
META_API_VERSION=v18.0
META_OAUTH_REDIRECT_URI=https://yourapp.com/api/wa/oauth/callback

# Webhook
WA_WEBHOOK_VERIFY_TOKEN=your_random_verify_token

# Pusher (for real-time notifications)
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_CLUSTER=mt1
```

## Features

- **Multi-Tenant Architecture**: Each customer has their own isolated data
- **Facebook OAuth**: Customers connect via Facebook Login
- **Multi-Number Support**: Connect multiple WhatsApp numbers
- **Conversation Management**: Full inbox system with assignment
- **Template Messages**: Send WhatsApp-approved templates
- **Conversation Flows**: Build automated flows with triggers
- **Ticketing System**: Convert conversations to support tickets
- **Analytics Dashboard**: Track messages, conversations, agent performance
- **Real-time Updates**: Via Pusher/Laravel Echo
- **Agent Management**: Multi-agent support with roles

## API Endpoints

### Public Endpoints
- `GET /api/wa/webhook` - Webhook verification
- `POST /api/wa/webhook` - Handle incoming messages
- `GET /api/wa/oauth/redirect` - Redirect to Facebook OAuth
- `GET /api/wa/oauth/callback` - OAuth callback

### Protected Endpoints (require Sanctum token)

#### Conversations
- `GET /api/wa/api/conversations` - List conversations
- `GET /api/wa/api/conversations/{id}` - Get conversation
- `GET /api/wa/api/conversations/{id}/messages` - Get messages
- `POST /api/wa/api/conversations/{id}/messages` - Send message
- `POST /api/wa/api/conversations/{id}/assign` - Assign agent
- `POST /api/wa/api/conversations/{id}/archive` - Archive

#### Templates
- `GET /api/wa/api/templates` - List templates
- `GET /api/wa/api/templates/sync` - Sync from Meta
- `POST /api/wa/api/templates/{id}/send` - Send template

#### Phone Numbers
- `GET /api/wa/api/phone-numbers` - List numbers
- `POST /api/wa/api/phone-numbers/{id}/webhook/test` - Test webhook

#### Flows
- `GET /api/wa/api/flows` - List flows
- `POST /api/wa/api/flows` - Create flow
- `PUT /api/wa/api/flows/{id}` - Update flow
- `POST /api/wa/api/flows/{id}/toggle` - Enable/disable

#### Tickets
- `GET /api/wa/api/tickets` - List tickets
- `POST /api/wa/api/tickets` - Create ticket
- `POST /api/wa/api/tickets/{id}/resolve` - Resolve ticket

#### Agents
- `GET /api/wa/api/agents` - List agents
- `POST /api/wa/api/agents` - Invite agent

#### Analytics
- `GET /api/wa/api/analytics/overview` - Dashboard overview
- `GET /api/wa/api/analytics/messages` - Message stats
- `GET /api/wa/api/analytics/agents` - Agent performance

## Usage

### Sending a Message

```php
use WhatsApp\Business\Services\ConversationService;

$service = app(ConversationService::class);

$message = $service->sendMessage(
    $conversation,
    'Hello, how can I help you?',
    $agentId
);
```

### Starting a Flow

```php
use WhatsApp\Business\Services\FlowEngine;

$flowEngine = app(FlowEngine::class);
$flowEngine->startFlow($flow, $conversation);
```

## Broadcasting (Real-time)

The package includes Laravel Echo compatible broadcasting channels:

### Channel Structure
- `whatsapp.customer.{id}` - Customer-specific events
- `whatsapp.conversation.{id}` - Conversation updates
- `whatsapp.agent.{id}` - Agent notifications

### Frontend Integration

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_CLUSTER,
});

Echo.private(`whatsapp.agent.${agentId}`)
  .listen('message.new', (e) => {
    console.log('New message:', e.message);
  })
  .listen('notification.new', (e) => {
    console.log('New notification:', e.notification);
  });
```

## Testing

```bash
composer test
```

Or with Orchestra Testbench:
```bash
vendor/bin/phpunit
```

## Service Providers

Register the providers in your `config/app.php`:

```php
'providers' => [
    // ...
    WhatsApp\Business\Providers\WhatsAppServiceProvider::class,
    WhatsApp\Business\Providers\WhatsAppEventServiceProvider::class,
],
```

## License

MIT License
