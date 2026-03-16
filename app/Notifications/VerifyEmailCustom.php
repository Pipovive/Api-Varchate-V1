<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;

class VerifyEmailCustom extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifica tu correo - Varchate')
            ->greeting('¡Hola '.$notifiable->nombre.'! 👋')
            ->line('Gracias por registrarte en Varchate.')
            ->line('Por favor verifica tu correo electrónico para activar tu cuenta.')
            ->action('Verificar correo', $url)
            ->line('Este enlace expirará en 60 minutos.')
            ->line('Si no creaste esta cuenta, ignora este mensaje.')
            ->salutation('— Equipo Varchate 🚀');
    }
}
