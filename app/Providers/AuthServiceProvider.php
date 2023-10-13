<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
         'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        //Passport::routes();
        //Passport::tokensExpireIn(now()->addMinutes(30));
        //Passport::refreshTokensExpireIn(now()->addMinutes(30));
        //Passport::personalAccessTokensExpireIn(now()->addMinutes(30));

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return $this->app->request->headers->get('origin').'?token='.$token.'&email='.$user->authentication_email;
        });

        // VerifyEmail::toMailUsing(function ($notifiable, $url) {
        //     return (new MailMessage)
        //         ->subject('Verify Email Addressaaaaaaaaaaaaa')
        //         ->line('Click the button below to verify your email address.')
        //         ->action('Verify Email Address', $url);
        // });

        // VerifyEmail::createUrlUsing(function ($notifiable) {
        //     $frontendUrl = 'http://cool-app.com/auth/email/verify';
    
        //     $verifyUrl = \URL::temporarySignedRoute(
        //         'verification.verify',
        //         Carbon::now()->addMinutes(\Config::get('auth.verification.expire', 60)),
        //         [
        //             'id' => $notifiable->getKey(),
        //             'hash' => sha1($notifiable->getEmailForVerification()),
        //         ]
        //     );
    
        //     return $frontendUrl . '?verify_url=' . urlencode($verifyUrl);
        // });
    }
}
