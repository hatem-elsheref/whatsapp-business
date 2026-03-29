<?php

namespace WhatsApp\Business\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use WhatsApp\Business\Services\WhatsAppCloudService;
use WhatsApp\Business\Services\OAuthService;
use WhatsApp\Business\Services\ConversationService;
use WhatsApp\Business\Services\FlowEngine;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/whatsapp.php',
            'whatsapp'
        );

        $this->app->singleton(WhatsAppCloudService::class, function ($app) {
            return new WhatsAppCloudService();
        });

        $this->app->singleton(OAuthService::class, function ($app) {
            return new OAuthService();
        });

        $this->app->singleton(FlowEngine::class, function ($app) {
            return new FlowEngine(
                $app->make(WhatsAppCloudService::class)
            );
        });

        $this->app->singleton(ConversationService::class, function ($app) {
            return new ConversationService(
                $app->make(WhatsAppCloudService::class),
                $app->make(FlowEngine::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->registerRoutes();

        $this->publishes([
            __DIR__ . '/../Config/whatsapp.php' => config_path('whatsapp.php'),
        ], 'whatsapp-config');

        $this->publishes([
            __DIR__ . '/../Database/Migrations/' => database_path('migrations'),
        ], 'whatsapp-migrations');
    }

    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => 'api/wa',
            'namespace' => 'WhatsApp\\Business\\Http\\Controllers',
            'as' => 'whatsapp.',
        ], function () {
            Route::get('/webhook', 'WebhookController@verify')->name('webhook.verify');
            Route::post('/webhook', 'WebhookController@handle')->name('webhook.handle');

            Route::prefix('oauth')->group(function () {
                Route::get('/redirect', 'OAuthController@redirectToProvider')->name('oauth.redirect');
                Route::get('/callback', 'OAuthController@handleCallback')->name('oauth.callback');
                Route::post('/manual-setup', 'OAuthController@manualSetup')->name('oauth.manual')->middleware('auth:sanctum');
                Route::delete('/disconnect', 'OAuthController@disconnect')->name('oauth.disconnect')->middleware('auth:sanctum');
                Route::post('/refresh', 'OAuthController@refreshToken')->name('oauth.refresh')->middleware('auth:sanctum');
            });

            Route::prefix('api')->group(function () {
                Route::post('/auth/login', 'Api\\AuthController@login')->name('auth.login');
            });

            Route::prefix('api')->middleware(['auth:sanctum'])->group(function () {
                Route::post('/auth/logout', 'Api\\AuthController@logout')->name('auth.logout');
                Route::get('/auth/me', 'Api\\AuthController@me')->name('auth.me');
                
                Route::get('/conversations', 'Api\\ConversationController@index');
                Route::get('/conversations/{id}', 'Api\\ConversationController@show');
                Route::get('/conversations/{id}/messages', 'Api\\ConversationController@messages');
                Route::post('/conversations/{id}/messages', 'Api\\ConversationController@sendMessage');
                Route::post('/conversations/{id}/assign', 'Api\\ConversationController@assign');
                Route::post('/conversations/{id}/archive', 'Api\\ConversationController@archive');
                Route::post('/conversations/{id}/block', 'Api\\ConversationController@block');

                Route::get('/templates', 'Api\\TemplateController@index');
                Route::get('/templates/sync', 'Api\\TemplateController@sync');
                Route::get('/templates/{id}', 'Api\\TemplateController@show');
                Route::post('/templates/{id}/send', 'Api\\TemplateController@send');

                Route::get('/phone-numbers', 'Api\\PhoneNumberController@index');
                Route::get('/phone-numbers/sync', 'Api\\PhoneNumberController@sync');
                Route::get('/phone-numbers/{id}', 'Api\\PhoneNumberController@show');
                Route::post('/phone-numbers/{id}/webhook/test', 'Api\\PhoneNumberController@testWebhook');
                Route::delete('/phone-numbers/{id}', 'Api\\PhoneNumberController@destroy');

                Route::get('/quick-replies', 'Api\\QuickReplyController@index');
                Route::post('/quick-replies', 'Api\\QuickReplyController@store');
                Route::put('/quick-replies/{id}', 'Api\\QuickReplyController@update');
                Route::delete('/quick-replies/{id}', 'Api\\QuickReplyController@destroy');

                Route::get('/flows', 'Api\\FlowController@index');
                Route::post('/flows', 'Api\\FlowController@store');
                Route::get('/flows/{id}', 'Api\\FlowController@show');
                Route::put('/flows/{id}', 'Api\\FlowController@update');
                Route::delete('/flows/{id}', 'Api\\FlowController@destroy');
                Route::post('/flows/{id}/toggle', 'Api\\FlowController@toggle');
                Route::post('/flows/{id}/steps', 'Api\\FlowController@updateSteps');

                Route::get('/tickets', 'Api\\TicketController@index');
                Route::post('/tickets', 'Api\\TicketController@store');
                Route::get('/tickets/{id}', 'Api\\TicketController@show');
                Route::put('/tickets/{id}', 'Api\\TicketController@update');
                Route::post('/tickets/{id}/assign', 'Api\\TicketController@assign');
                Route::post('/tickets/{id}/resolve', 'Api\\TicketController@resolve');
                Route::post('/tickets/{id}/close', 'Api\\TicketController@close');

                Route::get('/agents', 'Api\\AgentController@index');
                Route::post('/agents', 'Api\\AgentController@store');
                Route::put('/agents/{id}', 'Api\\AgentController@update');
                Route::delete('/agents/{id}', 'Api\\AgentController@destroy');

                Route::get('/notifications', 'Api\\NotificationController@index');
                Route::put('/notifications/{id}/read', 'Api\\NotificationController@markAsRead');
                Route::put('/notifications/read-all', 'Api\\NotificationController@markAllAsRead');

                Route::get('/analytics/overview', 'Api\\AnalyticsController@overview');
                Route::get('/analytics/messages', 'Api\\AnalyticsController@messages');
                Route::get('/analytics/conversations', 'Api\\AnalyticsController@conversations');
                Route::get('/analytics/agents', 'Api\\AnalyticsController@agents');
            });
        });
    }

    public function provides(): array
    {
        return [
            WhatsAppCloudService::class,
            OAuthService::class,
            ConversationService::class,
            FlowEngine::class,
        ];
    }
}
