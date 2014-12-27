<?php

namespace Emails;

/**
 * Test that we can send and capture mock emails.
 */
class MockMailerTest extends \PHPUnit_Framework_TestCase {

  var $oldTemplates = null;

  function setUp() {
    if (\Openclerk\Config::has('emails_templates')) {
      $this->oldTemplates = \Openclerk\Config::get("emails_templates");
    }
    \Openclerk\Config::overwrite(array(
      "emails_templates" => __DIR__ . "/",
    ));
    Email::setMockMailer(array($this, "mockMailer"));
  }

  function tearDown() {
    Email::setMockMailer(null);
    if ($this->oldTemplates) {
      \Openclerk\Config::overwrite(array(
        "emails_templates" => $this->oldTemplates,
      ));
    }
  }

  function testMock() {
    $date = date('r');

    $result = Email::send("example@example.com", "test", array(
      "now" => $date,
    ));

    $this->assertEquals("example@example.com", $this->to_email);
    $this->assertEquals("example@example.com", $this->to_name);
    $this->assertEquals("Test " . $date, $this->subject);
    $this->assertEquals("Our test value is " . $date, $this->template);
    $this->assertEquals("<p>Our test value is " . $date . "</p>", trim($this->html_template));
  }

  function mockMailer($to_email, $to_name, $subject, $template, $html_template) {
    $this->to_email = $to_email;
    $this->to_name = $to_name;
    $this->subject = $subject;
    $this->template = $template;
    $this->html_template = $html_template;
  }

}
