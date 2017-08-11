<?php
namespace NZTim\Mailchimp;

use Illuminate\Support\ServiceProvider;

class MailchimpServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $this->app->bind(Mailchimp::class, function () {
            return new Mailchimp(config('mailchimp.apikey'));
        });
        $this->publishes([
            __DIR__.'/../config/mailchimp.php' => config_path('mailchimp.php'),
        ]);
    }

    public function register()
    {
        $this->app->bind('mailchimp', function () {
            return $this->app->make(Mailchimp::class);
        });
        $this->mergeConfigFrom(__DIR__.'/../config/mailchimp.php', 'mailchimp');
    }
}
