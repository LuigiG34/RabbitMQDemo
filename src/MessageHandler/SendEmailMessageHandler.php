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
        $email = (new Email())
            ->from('no-reply@demo.local')
            ->to($message->getRecipient())
            ->subject($message->getSubject())
            ->text($message->getBody());

        $log = new EmailLog(
            $message->getMessageId(),
            $message->getRecipient(),
            $message->getSubject(),
            $message->getBody()
        );

        try {
            $this->mailer->send($email);
            $log->markSent();
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            $this->em->persist($log);
            $this->em->flush();

            throw $e;
        }

        $this->em->persist($log);
        $this->em->flush();
    }
}
