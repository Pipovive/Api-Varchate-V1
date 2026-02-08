<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordCustom extends ResetPassword
{
    public function toMail($notifiable)
    {
        $url = url(config('app.frontend_url') . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->email));

        return (new MailMessage)
            ->subject('Restablece tu contraseña - Varchate')
            ->greeting('¡Hola ' . $notifiable->nombre . '! 👋')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->line('Para continuar, haz clic en el botón de abajo:')
            ->action('Restablecer contraseña', $url)
            ->line('Este enlace expirará en ' . config('auth.passwords.'.config('auth.defaults.passwords').'.expire') . ' minutos.')
            ->line('Si no solicitaste restablecer tu contraseña, ignora este mensaje.')
            ->line('Por seguridad, nunca compartas este enlace con nadie.')
            ->salutation('— Equipo Varchate 🔒');
    }
}
