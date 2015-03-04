<?php

namespace Emails;

class Email {

  static $global_arguments = array();

  /**
   * This allows clients to add global variables to all emails sent.
   * @param $callback callback function that returns an argument value,
   *    given the original $arguments as the first parameter
   */
  static function registerGlobalArgument($name, $callback) {
    self::$global_arguments[$name] = $callback;
  }

  /**
   * Send an email with the given template to the given user or address.
   * The subject of the e-mail is obtained from the first line of the text e-mail template, or the
   * <title> of the HTML template.
   *
   * TODO support mock mailing
   * TODO support i18n
   * TODO service wrapper to allow emails to be queued
   *
   * @param $to_or_user either an email address, or something with getEmail() and optionally getName()
   * @throws MailerException if the mail could not be immediately sent (e.g. technical error, invalid e-mail address...)
   * @return the email result object, which is also sent to the `email_sent` event trigger
   */
  static function send($to_or_user, $template_id, $arguments = array()) {
    // add any global arguments
    foreach (static::$global_arguments as $name => $callback) {
      if (!isset($arguments[$name])) {
        $arguments[$name] = call_user_func($callback, $arguments);
      }
    }

    $to_name = false;
    $to_id = null;
    if (is_object($to_or_user)) {
      $to_email = $to_or_user->getEmail();
      if (method_exists($to_or_user, 'getName')) {
        $to_name = $to_or_user->getName();
      }
      if (method_exists($to_or_user, 'getId')) {
        $to_id = $to_or_user->getId();
      }
    } else if (is_string($to_or_user)) {
      $to_email = $to_or_user;
    } else {
      throw new MailerException("Unknown 'to' type " . gettype($to_or_user));
    }
    if (!$to_name) {
      $to_name = $to_email;
    }

    // default arguments
    if (!isset($arguments['email'])) {
      $arguments['email'] = $to_email;
    }
    if (!isset($arguments['name'])) {
      $arguments['name'] = $to_name;
    }

    $compiled = self::compile($template_id, $arguments);
    $template = $compiled['text'];
    $html_template = $compiled['html'];
    $subject = $compiled['subject'];

    // now send the email
    // may throw MailerException
    if (self::$mock_mailer) {
      $message_id = call_user_func(self::$mock_mailer, $to_email, $to_name, $subject, $template, $html_template);
    } else {
      $message_id = Email::phpmailer($to_email, $to_name, $subject, $template, $html_template);
    }

    // allow others to capture this event
    $result = array(
      "user_id" => $to_id,
      "to_name" => $to_name,
      "to_email" => $to_email,
      "subject" => $subject,
      "template_id" => $template_id,
      "arguments" => $arguments,
      "message_id" => $message_id,
    );
    \Openclerk\Events::trigger('email_sent', $result);

    return $result;
  }

  static function compile($template_id, $arguments = array()) {

    $template = false;
    $subject = false;
    $html_template = false;

    $template_dir = \Openclerk\Config::get('emails_templates', __DIR__ . '/../../../../emails');

    if (file_exists($template_dir . "/" . $template_id . ".txt")) {
      $template = file_get_contents($template_dir . "/" . $template_id . ".txt");

      // strip out the subject from the text
      $template = explode("\n", $template, 2);
      $subject = $template[0];
      $template = $template[1];
    }

    if (file_exists($template_dir . "/" . $template_id . ".html")) {
      $html_template = file_get_contents($template_dir . "/" . $template_id . ".html");
      if (file_exists($template_dir . "/" . "layout.html")) {
        $html_layout_template = file_get_contents($template_dir . "/" . "layout.html");

        $html_template = \Openclerk\Templates::replace($html_layout_template, array('content' => $html_template));
      }

      // strip out the title from the html
      $matches = false;
      if (preg_match("#<title>(.+)</title>#im", $html_template, $matches)) {
        $subject = $matches[1];
      }
      $html_template = preg_replace("#<title>.+</title>#im", "", $html_template);
    }

    if (!$template) {
      if ($html_template) {
        // use html2text to generate the text body automatically
        $template = \Html2Text\Html2Text::convert($html_template);
      } else {
        throw new MailerException("Email template '$template_id' did not exist within '$template_dir'");
      }
    }

    // replace variables
    $template = \Openclerk\Templates::replace($template, $arguments);
    $subject = \Openclerk\Templates::replace($subject, $arguments);
    if ($html_template) {
      $html_template = \Openclerk\Templates::replace($html_template, $arguments);
    }

    // inline CSS?
    if (file_exists($template_dir . "/layout.css")) {
      $css = file_get_contents($template_dir . "/layout.css");

      // custom CSS?
      if (\Openclerk\Config::get("emails_additional_css", false)) {
       $css .= file_get_contents(\Openclerk\Config::get("emails_additional_css", false));
      }

      $emogrifier = new \Pelago\Emogrifier();
      $emogrifier->setHtml($html_template);
      $emogrifier->setCss($css);
      $html_template = $emogrifier->emogrify();
    }

    return array(
      'subject' => $subject,
      'html' => $html_template,
      'text' => $template,
    );
  }

  static $mock_mailer = null;

  /**
   * @param $mock a callback with arguments ($to_email, $to_name, $subject, $template, $html_template),
   *            or {@code null} to remove the mock mailer
   */
  static function setMockMailer($mock) {
    self::$mock_mailer = $mock;
  }

  /**
   * TODO support HTML emails
   * @return the sent message ID
   * @throws MailerException if the mail could not be immediately sent
   */
  static function phpmailer($to, $to_name, $subject, $message, $html_message = false) {
    $mail = new \PHPMailer();

    $mail->IsSMTP();                                      // set mailer to use SMTP
    $mail->Host = \Openclerk\Config::get('phpmailer_host');  // specify main and backup server
    $mail->SMTPAuth = true;     // turn on SMTP authentication
    $mail->Username = \Openclerk\Config::get('phpmailer_username');  // SMTP username
    $mail->Password = \Openclerk\Config::get('phpmailer_password'); // SMTP password

    $mail->From = \Openclerk\Config::get('phpmailer_from');
    $mail->FromName = \Openclerk\Config::get('phpmailer_from_name');
    $mail->Sender = \Openclerk\Config::get('phpmailer_from');
    $mail->AddAddress($to, $to_name);
    if (\Openclerk\Config::get('phpmailer_reply_to')) {
      $mail->AddReplyTo(\Openclerk\Config::get('phpmailer_reply_to'));
    }

    if (\Openclerk\Config::get('phpmailer_bcc', false)) {
      $mail->AddBCC(\Openclerk\Config::get('phpmailer_bcc'));
    }

    $mail->Subject = $subject;
    if ($html_message) {
      $mail->Body = $html_message;
      $mail->AltBody = $message;
      $mail->IsHTML(true);
    } else {
      $mail->Body    = $message;
    }

    if(!$mail->Send()) {
      throw new MailerException("Message could not be sent: " . $mail->ErrorInfo);
    }

    return $mail->GetLastMessageID();
  }

}
