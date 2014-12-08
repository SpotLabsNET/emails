<?php

namespace Emails;

class Email {
  /**
   * Send an email with the given template to the given user or address.
   * The subject of the e-mail is obtained from the first line of the text e-mail template, or the
   * <title> of the HTML template.
   *
   * TODO support html emails
   * TODO support mock mailing
   * TODO support i18n
   * TODO service wrapper to allow emails to be queued
   *
   * @param $to_or_user either an email address, or something with getEmail() and optionally getName()
   * @throws MailerException if the mail could not be immediately sent (e.g. technical error, invalid e-mail address...)
   */
  static function send(\Db\Connection $db, $to_or_user, $template_id, $arguments = array()) {
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

    $template_dir = \Openclerk\Config::get('template_dir_emails', __DIR__ . '/../../../../emails/');

    $template = false;
    if (file_exists($template_dir . $template_id . ".txt")) {
      $template = file_get_contents($template_dir . $template_id . ".txt");
    }

    $html_template = false;
    if (file_exists($template_dir . $template_id . ".html")) {
      $html_template = file_get_contents($template_dir . $template_id . ".html");
      if (file_exists($template_dir . "layout.html")) {
        $html_layout_template = file_get_contents($template_dir . "layout.html");

        $html_template = \Openclerk\Templates::replace($html_layout_template, array('content' => $html_template));
      }
    }

    if (!$template) {
      if ($html_template) {
        // use html2text to generate the text body automatically
        $template = \Html2Text\Html2Text::convert($html_template);
      } else {
        throw new MailerException("Email template '$template_id' did not exist within '$template_dir'");
      }
    }

    // default arguments
    if (!isset($arguments['email'])) {
      $arguments['email'] = $to_email;
    }
    if (!isset($arguments['name'])) {
      $arguments['name'] = $to_name;
    }

    // replace variables
    // $args["site_name"] = \Openclerk\Config::get('site_name');
    // $args["site_url"] = absolute_url("");
    // $args["site_email"] = \Openclerk\Config::get('site_email');
    $template = \Openclerk\Templates::replace($template, $arguments);
    if ($html_template) {
      $html_template = \Openclerk\Templates::replace($html_template, $arguments);

      // strip out the subject from the html
      // (if there is no text template defined, then this template title is going to be used
      // by the text template title)
      $html_template = explode("\n", $html_template, 2);
      $html_template = $html_template[1];
    }

    // strip out the subject from the text
    $template = explode("\n", $template, 2);
    $subject = $template[0];
    $template = $template[1];

    // now send the email
    // may throw MailerException
    Email::phpmailer($to_email, $to_name, $subject, $template, $html_template);

    // insert in database keys
    $q = $db->prepare("INSERT INTO emails SET
      user_id=:user_id,
      to_name=:to_name,
      to_email=:to_email,
      subject=:subject,
      template_id=:template_id,
      arguments=:arguments");
    $q->execute(array(
      "user_id" => $to_id,
      "to_name" => $to_name,
      "to_email" => $to_email,
      "subject" => $subject,
      "template_id" => $template_id,
      "arguments" => serialize($arguments),
    ));

    return true;
  }

  /**
   * TODO support HTML emails
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
  }

}
