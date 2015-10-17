<?php
namespace NZTim\Mailchimp;

use App;
use Illuminate\Support\ServiceProvider;

class MailchimpServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $this->app->bind(Mailchimp::class, function() {
            return new Mailchimp(new DrewMMailchimp(env('MC_KEY')));
        });
    }

    public function register()
    {
        $this->app->bind('mailchimp', function() {
            return $this->app->make(Mailchimp::class);
        });
    }
}
