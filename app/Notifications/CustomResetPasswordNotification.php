<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

class CustomResetPasswordNotification extends Notification
{
    use Queueable;

    public $token;
    public $email;

    public function __construct($token, $email = null)
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $this->email ?? $notifiable->getEmailForPasswordReset(),
        ], false));

        $nombreCompleto = $notifiable->nombre_completo ?? $notifiable->email ?? 'Usuario';

        return (new MailMessage)
            ->subject('Recuperación de Contraseña - RIC')
            ->view('mails.custom-reset-password', [  
                'reset_url' => $url,
                'nombre_completo' => $nombreCompleto,
                'email' => $this->email ?? $notifiable->email,
            ]);
    }
}