<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  src/helpers/MailerClient.php
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Dunne wrapper rond PHPMailer voor het versturen van e-mails via SMTP.
 * ============================================================================
 */


namespace CrmExt\Helpers;

use CrmExt\Models\EmailMessage;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * MailerClient
 *
 * Dunne wrapper rond PHPMailer voor het versturen van e-mails via SMTP.
 * De rest van de applicatie hoeft niets te weten van PHPMailer; alleen
 * deze klasse praat ermee.
 */
final class MailerClient
{
    /** @param array{host:string,port:int,username:string,password:string,encryption:string,from_addr:string,from_name:string} $config */
    public function __construct(private array $config) {}

    public function send(EmailMessage $message): bool
    {
        $mailer = new PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host       = $this->config['host'];
            $mailer->Port       = $this->config['port'];
            $mailer->SMTPAuth   = true;
            $mailer->Username   = $this->config['username'];
            $mailer->Password   = $this->config['password'];
            $mailer->SMTPSecure = $this->config['encryption'];
            $mailer->CharSet    = 'UTF-8';

            $mailer->setFrom($this->config['from_addr'], $this->config['from_name']);
            $mailer->addAddress($message->toAddress);
            $mailer->Subject = $message->subject;
            $mailer->Body    = $message->body;

            return $mailer->send();
        } catch (MailException $e) {
            error_log('SMTP-fout: ' . $mailer->ErrorInfo);
            return false;
        }
    }
}
