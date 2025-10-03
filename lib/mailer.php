<?php
// Lightweight mail helper that prefers PHPMailer (Gmail SMTP) with a mail() fallback

require_once __DIR__ . '/../config/config.php';

// Try to include PHPMailer if available via Composer or manually placed
$hasPHPMailer = false;
if (!$hasPHPMailer && file_exists(__DIR__ . '/../vendor/autoload.php')) {
	require_once __DIR__ . '/../vendor/autoload.php';
	$hasPHPMailer = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
}
if (!$hasPHPMailer) {
	$phpMailerPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
	$phpSMTPPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
	$phpExceptionPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
	if (file_exists($phpMailerPath) && file_exists($phpSMTPPath) && file_exists($phpExceptionPath)) {
		require_once $phpExceptionPath;
		require_once $phpMailerPath;
		require_once $phpSMTPPath;
		$hasPHPMailer = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
	}
}

function send_email(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
	global $hasPHPMailer;

	if ($hasPHPMailer) {
		try {
			$mail = new PHPMailer\PHPMailer\PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = SMTP_HOST;
			$mail->SMTPAuth = true;
			$mail->Username = SMTP_USERNAME;
			$mail->Password = SMTP_PASSWORD;
			$mail->SMTPSecure = SMTP_SECURE;
			$mail->Port = SMTP_PORT;
			$mail->CharSet = 'UTF-8';
			$mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
			$mail->addAddress($toEmail, $toName ?: $toEmail);
			$mail->Subject = $subject;
			$mail->isHTML(true);
			$mail->Body = $htmlBody;
			$mail->AltBody = $textBody ?: strip_tags($htmlBody);
			$mail->send();
			return true;
		} catch (Throwable $e) {
			// fall through to mail() fallback
		}
	}

	// Fallback to PHP mail()
	$headers = [];
	$headers[] = 'MIME-Version: 1.0';
	$headers[] = 'Content-type: text/html; charset=UTF-8';
	$headers[] = 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>';
	return @mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));
}

?>


