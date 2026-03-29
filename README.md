# WhatsApp Business Package

A comprehensive WhatsApp Business SaaS package for Laravel with multi-tenant support, OAuth authentication, conversation management, and more.

## Installation

1. Add the package to your Laravel project:
```bash
composer require hatem-elsheref/whatsapp-business
```

2. Run migrations (migrations run automatically):
```bash
php artisan migrate
```

3. Seed default admin user (optional):
```bash
php artisan db:seed --class=WhatsAppBusinessSeeder
```

## Configuration

1. Publish the configuration:
```bash
php artisan vendor:publish --tag=whatsapp-config
```

2. Add these environment variables to your `.env` file:

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
- **Manual Setup**: Enter App ID, App Secret, and Access Token directly
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
- `POST /api/wa/oauth/manual-setup` - Manual setup with access token

### Protected Endpoints (require Sanctum token)

#### Auth
- `POST /api/wa/api/auth/login` - Login
- `POST /api/wa/api/auth/logout` - Logout
- `GET /api/wa/api/auth/me` - Get current user

#### Conversations
- `GET /api/wa/api/conversations` - List conversations
- `GET /api/wa/api/conversations/{id}` - Get conversation
- `GET /api/wa/api/conversations/{id}/messages` - Get messages
- `POST /api/wa/api/conversations/{id}/messages` - Send message
- `POST /api/wa/api/conversations/{id}/assign` - Assign agent
- `POST /api/wa/api/conversations/{id}/archive` - Archive
- `POST /api/wa/api/conversations/{id}/block` - Block

#### Templates
- `GET /api/wa/api/templates` - List templates
- `GET /api/wa/api/templates/sync` - Sync from Meta
- `GET /api/wa/api/templates/{id}` - Get template
- `POST /api/wa/api/templates/{id}/send` - Send template

#### Phone Numbers
- `GET /api/wa/api/phone-numbers` - List numbers
- `GET /api/wa/api/phone-numbers/sync` - Sync from Meta
- `GET /api/wa/api/phone-numbers/{id}` - Get number
- `POST /api/wa/api/phone-numbers/{id}/webhook/test` - Test webhook
- `DELETE /api/wa/api/phone-numbers/{id}` - Remove number

#### Quick Replies
- `GET /api/wa/api/quick-replies` - List quick replies
- `POST /api/wa/api/quick-replies` - Create
- `PUT /api/wa/api/quick-replies/{id}` - Update
- `DELETE /api/wa/api/quick-replies/{id}` - Delete

#### Flows
- `GET /api/wa/api/flows` - List flows
- `POST /api/wa/api/flows` - Create flow
- `GET /api/wa/api/flows/{id}` - Get flow
- `PUT /api/wa/api/flows/{id}` - Update flow
- `DELETE /api/wa/api/flows/{id}` - Delete flow
- `POST /api/wa/api/flows/{id}/toggle` - Enable/disable
- `POST /api/wa/api/flows/{id}/steps` - Update steps

#### Tickets
- `GET /api/wa/api/tickets` - List tickets
- `POST /api/wa/api/tickets` - Create ticket
- `GET /api/wa/api/tickets/{id}` - Get ticket
- `PUT /api/wa/api/tickets/{id}` - Update ticket
- `POST /api/wa/api/tickets/{id}/assign` - Assign agent
- `POST /api/wa/api/tickets/{id}/resolve` - Resolve ticket
- `POST /api/wa/api/tickets/{id}/close` - Close ticket

#### Agents
- `GET /api/wa/api/agents` - List agents
- `POST /api/wa/api/agents` - Create agent
- `PUT /api/wa/api/agents/{id}` - Update agent
- `DELETE /api/wa/api/agents/{id}` - Delete agent

#### Notifications
- `GET /api/wa/api/notifications` - List notifications
- `PUT /api/wa/api/notifications/{id}/read` - Mark as read
- `PUT /api/wa/api/notifications/read-all` - Mark all as read

#### Analytics
- `GET /api/wa/api/analytics/overview` - Dashboard overview
- `GET /api/wa/api/analytics/messages` - Message stats
- `GET /api/wa/api/analytics/conversations` - Conversation stats
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

### Manual Setup (Without OAuth)

```php
use WhatsApp\Business\Services\OAuthService;

$service = app(OAuthService::class);

$result = $service->manualSetup($customerId, [
    'app_id' => 'your_app_id',
    'app_secret' => 'your_app_secret',
    'access_token' => 'your_permanent_access_token',
]);
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

## Database Tables

The package creates the following tables:

- `wa_customers` - Customer accounts
- `wa_agents` - Agent accounts
- `wa_phone_numbers` - WhatsApp phone numbers
- `wa_templates` - Message templates
- `wa_quick_replies` - Quick reply messages
- `wa_flows` - Automated conversation flows
- `wa_flow_steps` - Flow steps
- `wa_flow_user_data` - User flow progress
- `wa_conversations` - Customer conversations
- `wa_messages` - Messages
- `wa_tickets` - Support tickets
- `wa_ticket_messages` - Ticket messages
- `wa_notifications` - Notifications
- `wa_analytics_events` - Analytics events

## Default Credentials

After seeding:
- Admin: `admin@whatsapp.local` / `admin123`
- Agent: `agent@whatsapp.local` / `agent123`

## Testing

```bash
composer test
```

Or with Orchestra Testbench:
```bash
vendor/bin/phpunit
```

## Auto-Discovery

Laravel 5.5+ auto-discovers the service providers. No manual registration needed.

## License

MIT License
