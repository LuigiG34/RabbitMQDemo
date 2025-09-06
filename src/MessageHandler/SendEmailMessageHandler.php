<?php

namespace App\MessageHandler;

use App\Entity\EmailLog;
use App\Message\SendEmailMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendEmailMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em
    ) {}

    public function __invoke(SendEmailMessage $message): void
    {
        $repo = $this->em->getRepository(EmailLog::class);

        // Load existing log by messageId (idempotency)
        $log = $repo->findOneBy(['messageId' => $message->getMessageId()]);

        if (!$log) {
            $log = new EmailLog(
                $message->getMessageId(),
                $message->getRecipient(),
                $message->getSubject(),
                $message->getBody()
            );
            $this->em->persist($log);
        } else {
            // If we already marked it sent, do nothing (avoid duplicate sends)
            if ($log->getStatus() === 'sent') {
                return;
            }
        }

        $email = (new Email())
            ->from('no-reply@demo.local')
            ->to($message->getRecipient())
            ->subject($message->getSubject())
            ->text($message->getBody());

        try {
            $this->mailer->send($email);
            $log->markSent();
            $this->em->flush();
        } catch (\Throwable $e) {
            // Record the attempt & error, then let Messenger retry
            $log->markAttempt($e->getMessage());
            $this->em->flush();
            throw $e;
        }
    }
}
