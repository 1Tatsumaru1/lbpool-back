<?php

namespace LBPool\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


class Utilities {
    
    /**
     * Send a email to specified recipient to complete specified process
     * @param email_address (string) : recipient's email address
     * @param subject (string) : the text that will appear as subject of the email sent to the recipient
     * @param body (string) : the HTML content of the email
     * @param $_ENV['MAIL_HOST'], $_ENV['MAIL_PORT'], $_ENV['MAIL_USER'], $_ENV['MAIL_PASS'] : environment variables
     * @return int : 1 if the email was successfully sent, 0 otherwise
     */
    public static function sendEmail(string $email_address, string $subject, $body) {
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // DEBUG
        $mail->Host = $_ENV['MAIL_HOST'];
        $mail->Port = $_ENV['MAIL_PORT'];
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->Username = $_ENV['MAIL_USER'];
        $mail->Password = $_ENV['MAIL_PASS'];
        $mail->setFrom($_ENV['MAIL_USER'], 'LBPool');
        $mail->addAddress($email_address);
        $mail->Subject = $subject;
        $mail->msgHTML($body);
        $s = DIRECTORY_SEPARATOR;
        $rootPath = strstr(__DIR__, 'Utils', true);
        $path =  $rootPath . 'public' . $s . 'lbpool_logo.png';
        $mail->addEmbeddedImage($path, 'lbpool_logo');
        try{
            $mail->send();
            return 1;
        } catch (Exception $e) {
            return 0;
        }
    }

}