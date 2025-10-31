<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-email',
    description: 'Test email sending via MailHog/Mailpit',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $email = (new Email())
                ->from('test@example.com')
                ->to('admin@example.com')
                ->subject('Test Email from Symfony')
                ->text('This is a test email to verify MailHog/Mailpit is working correctly.')
                ->html('<p>This is a <strong>test email</strong> to verify MailHog/Mailpit is working correctly.</p>');

            $this->mailer->send($email);

            $io->success('Test email sent successfully!');
            $io->note('Check MailHog/Mailpit UI at http://localhost:8025 to view the email.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to send test email: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
