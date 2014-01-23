<?php

// Make sure we are called from index.php
if (!defined('SECURITY'))
  die('Hacking attempt');

class Mail extends Base {
  /**
  * Mail form contact site admin
  * @param senderName string senderName
  * @param senderEmail string senderEmail
  * @param senderSubject string senderSubject
  * @param senderMessage string senderMessage
  * @param email string config Email address
  * @param subject string header subject
  * @return bool
  **/
  public function contactform($senderName, $senderEmail, $senderSubject, $senderMessage) {
    $this->debug->append("STA " . __METHOD__, 4);
    if (preg_match('/[^a-z_\.\!\?\-0-9\\s ]/i', $senderName)) {
      $this->setErrorMessage($this->getErrorMsg('E0024'));
      return false;
    }
    if (empty($senderEmail) || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
      $this->setErrorMessage($this->getErrorMsg('E0023'));
      return false;
    }
    if (preg_match('/[^a-z_\.\!\?\-0-9\\s ]/i', $senderSubject)) {
      $this->setErrorMessage($this->getErrorMsg('E0034'));
      return false;
    }
    if (strlen(strip_tags($senderMessage)) < strlen($senderMessage)) {
      $this->setErrorMessage($this->getErrorMsg('E0024'));
      return false;
    }
    $aData['senderName'] = $senderName;
    $aData['senderEmail'] = $senderEmail;
    $aData['senderSubject'] = $senderSubject;
    $aData['senderMessage'] = $senderMessage;
    $aData['email'] = $this->setting->getValue('website_email');
    $aData['subject'] = 'Contact Form';
      if ($this->sendMail('contactform/body', $aData)) {
        return true;
     } else {
       $this->setErrorMessage( 'Unable to send email' );
       return false;
     }
    return false;
  }

  /**
   * Send a mail with templating via Smarty
   * @param template string Template name within the mail folder, no extension
   * @param aData array Data array with some required fields
   *     SUBJECT : Mail Subject
   *     email   : Destination address
   **/
  public function sendMail($template, $aData) {
    date_default_timezone_set('UTC');
    // Make sure we don't load a cached filed
    $this->smarty->clearCache(BASEPATH . 'templates/mail/' . $template . '.tpl');
    $this->smarty->clearCache(BASEPATH . 'templates/mail/subject.tpl');
    $this->smarty->assign('WEBSITENAME', $this->setting->getValue('website_name'));
    $this->smarty->assign('SUBJECT', $aData['subject']);
    $this->smarty->assign('DATA', $aData);
 
    if ($this->config['smtp']['auth'] === 1) {
      if ($this->authSendEmail($this->setting->getValue('website_email'), $this->setting->getValue('website_name'), $aData['email'], $aData['email'],
          $this->smarty->fetch(BASEPATH . 'templates/mail/subject.tpl'), $this->smarty->fetch(BASEPATH . 'templates/mail/' . $template . '.tpl'),
          $this->config['smtp']['server'], $this->config['smtp']['port'], $this->config['smtp']['timeout'], $this->config['smtp']['user'],
          $this->config['smtp']['pass'], $this->config['smtp']['localhost'], $this->config['smtp']['newline'], $this->config['smtp']['idfrom']))
         return true;
    }
    else {
       $headers = 'From: ' . $this->setting->getValue('website_name') . '<' . $this->setting->getValue('website_email') . ">\n";
       $headers .= "MIME-Version: 1.0\n";
       $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
       if (strlen(@$aData['senderName']) > 0 && @strlen($aData['senderEmail']) > 0 )
         $headers .= 'Reply-To: ' . $aData['senderName'] . ' <' . $aData['senderEmail'] . ">\n";
 
       if (mail($aData['email'], $this->smarty->fetch(BASEPATH . 'templates/mail/subject.tpl'), $this->smarty->fetch(BASEPATH . 'templates/mail/' . $template . '.tpl'), $headers))
         return true;
    }
    $this->setErrorMessage($this->sqlError('E0031'));
    return false;
  }
  
  public function authSendEmail($from, $namefrom, $to, $nameto, $subject, $message, $smtpServer, $port, $timeout, $username, $password, $localhost, $newLine, $idfrom) {  
      $success = false;
      // connect to smtp on port specified
      $smtpConnect = fsockopen($smtpServer, $port, $errno, $errstr, $timeout);
      $smtpResponse = fgets($smtpConnect, 515);
      if(empty($smtpConnect)) {return false;}
  
      // auth login
      fputs($smtpConnect, "AUTH LOGIN" . $newLine);
      $smtpResponse = fgets($smtpConnect, 515);
  
      fputs($smtpConnect, base64_encode($username) . $newLine);
      $smtpResponse = fgets($smtpConnect, 515);
  
      fputs($smtpConnect, base64_encode($password) . $newLine);
      $smtpResponse = fgets($smtpConnect, 515);
 
      fputs($smtpConnect, "HELLO $localhost" . $newLine);
      $smtpResponse = fgets($smtpConnect, 515);
  
      fputs($smtpConnect, "MAIL FROM: $from" . $newLine);
      $smtpResponse = fgets($smtpConnect, 515);
 
      fputs($smtpConnect, "RCPT TO: $to" . $newLine);
      $smtpResponse = fgets($smtpConnect, 515);
 
      fputs($smtpConnect, "DATA" . $newLine);
      $smtpResponse = fgets($smtpConnect, 515);
 
      // send rfc-2822 headers and email
      //$headers = "MIME-Version: 1.0" . $newLine;  
      //$headers .= "Content-Type: text/html;charset=iso-8859-1" . $newLine;  
      //$headers .= "Date: " . date(DateTime::RFC2822) . $newLine;
      //$headers .= "Message-ID: <" . sha1(microtime(true)) . "@$idfrom>" . $newLine;
 
      fputs($smtpConnect, "To: $to\nFrom: $from\nSubject: $subject\n$headers\n\n$message\n.\n");
      $smtpResponse = fgets($smtpConnect, 515);
      if (strstr($smtpResponse, "250", false)) $success = true;
 
      fputs($smtpConnect, "QUIT" . $newLine);
      $smtpResponse = fgets($smtpConnect, 515);
      
      return $success;
   }
}

// Make our class available automatically
$mail = new Mail ();
$mail->setDebug($debug);
$mail->setMysql($mysqli);
$mail->setSmarty($smarty);
$mail->setConfig($config);
$mail->setSetting($setting);
$mail->setErrorCodes($aErrorCodes);
?>
