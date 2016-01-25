<?php
namespace NZTim\Mailchimp;

use App;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class MailchimpServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $this->app->bind(Mailchimp::class, function() {
            return new Mailchimp(env('MC_KEY'), App::make(LoggerInterface::class));
        });
    }

    public function register()
    {
        $this->app->bind('mailchimp', function() {
            return $this->app->make(Mailchimp::class);
        });
    }
}
