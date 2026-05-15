<?php
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

$dsn = 'smtp://svitlamarina%40gmail.com:tkkbvajcpnpqnogh@smtp.gmail.com:587';
$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport);

$email = (new Email())
    ->from('svitlamarina@gmail.com')
    ->to('svitlamarina@gmail.com')
    ->subject('Test Email from Shopware')
    ->text('If you see this, SMTP is working!')
    ->html('<h1>Test</h1><p>If you see this, SMTP is working!</p>');

try {
    $mailer->send($email);
    echo "Email sent successfully!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
