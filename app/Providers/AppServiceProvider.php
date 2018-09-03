<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use LibreNMS\Config;
use LibreNMS\Exceptions\DatabaseConnectException;

include_once __DIR__ . '/../../includes/dbFacile.php';

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     * @throws DatabaseConnectException caught by App\Exceptions\Handler and displayed to the user
     */
    public function boot()
    {
        \LibreNMS\DB\Eloquent::initLegacyListeners(); // Install legacy dbFacile fetch mode listener

        Config::load();

        $this->setUpLogging();

        $this->copyLegacyMailConfig();

        $this->setUpCustomBladeDirectives();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerDevelopmentServiceProviders();
    }

    private function setUpLogging()
    {
        // direct log output to librenms.log
        Log::getMonolog()->popHandler(); // remove existing errorlog logger
        Log::useFiles(Config::get('log_file', base_path('logs/librenms.log')), 'error');
    }

    private function copyLegacyMailConfig()
    {
        // copy mail mta config to laravel
        $config = config('mail');
        $config['driver'] = strtolower(trim(Config::get('email_backend', $config['driver'])));
        $config['sendmail'] = Config::get('email_sendmail_path', $config['sendmail']);
        $config['host'] = Config::get('email_smtp_host', $config['host']);
        $config['username'] = Config::get('email_smtp_username', $config['username']);
        $config['password'] = Config::get('email_smtp_password', $config['password']);
        $config['port'] = Config::get('email_smtp_port', $config['port']);
        $config['encryption'] = Config::get('email_smtp_secure', $config['encryption']);

        // set default from
        foreach (\LibreNMS\Util\Mail::parseEmails(Config::get('email_from')) as $mail) {
            $config['from'] = [
                'address' => $mail['email'],
                'name' => $mail['name']
            ];
        }

        \Config::set('mail', $config);
    }

    private function setUpCustomBladeDirectives()
    {
        // Blade directives (Yucky because of < L5.5)
        Blade::directive('config', function ($key) {
            return "<?php if (\LibreNMS\Config::get(($key))): ?>";
        });
        Blade::directive('notconfig', function ($key) {
            return "<?php if (!\LibreNMS\Config::get(($key))): ?>";
        });
        Blade::directive('endconfig', function () {
            return "<?php endif; ?>";
        });
        Blade::directive('admin', function () {
            return "<?php if (auth()->check() && auth()->user()->isAdmin()): ?>";
        });
        Blade::directive('endadmin', function () {
            return "<?php endif; ?>";
        });
    }

    private function registerDevelopmentServiceProviders()
    {
        if ($this->app->environment() === 'production') {
            return;
        }

        if (class_exists(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class)) {
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }

        if (config('app.debug') && class_exists(\Barryvdh\Debugbar\ServiceProvider::class)) {
            $this->app->register(\Barryvdh\Debugbar\ServiceProvider::class);
        }
    }
}
