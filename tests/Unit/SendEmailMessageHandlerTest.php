<?php

namespace App\Tests\Unit;

use App\Entity\EmailLog;
use App\Message\SendEmailMessage;
use App\MessageHandler\SendEmailMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SendEmailMessageHandlerTest extends TestCase
{
    /**
     * Ensure that when no log exists :
     * - a new EmailLog is created
     * - the email is sent once
     * - the log is persisted and flushed as "sent"
     * @return void
     */
    public function testCreatesLogSendsEmailAndMarksSent(): void
    {
        // Mock the mailer
        // Expect 1 call to send, with an Email object
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->with($this->isInstanceOf(Email::class));

        // Mock the repo
        // When asked for messageId = "id-1" return null (no log in DB)
        $repo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repo->method('findOneBy')->with(['messageId' => 'id-1'])->willReturn(null);

        // Mock the EntityManager
        // getRepository returns our repo
        // Expect 1 persist() EmailLog
        // Expect 1 flush()
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(EmailLog::class));
        $em->expects($this->once())->method('flush');

        // Create our handler with mocks and invoke it with a new Message
        $handler = new SendEmailMessageHandler($mailer, $em);
        $handler(new SendEmailMessage('id-1', 'user@example.com', 'Subj', 'Body'));
    }

    /**
     * Ensures idempotency :
     * If an EmailLog already exists with status "sent"
     * the handler should skip sending the and make no DB changes
     * @return void
     */
    public function testIdempotentWhenAlreadySent(): void
    {
        // Create EmailLog entity and mark it as "sent"
        $log = new EmailLog('id-2', 'user@example.com', 'Subj', 'Body');
        $log->markSent();

        // Mock repository, return the existing log when asked
        $repo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repo->method('findOneBy')->with(['messageId' => 'id-2'])->willReturn($log);

        // Mailer must not send the message since it was already processed
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        // EntityManager must not flush
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->expects($this->never())->method('flush');

        // Call the handler, nothing should happen
        $handler = new SendEmailMessageHandler($mailer, $em);
        $handler(new SendEmailMessage('id-2', 'user@example.com', 'Subj', 'Body'));

        // Simply to avoid PHPUnit classifying this as a risky test
        $this->assertTrue(true);
    }

    /**
     * Ensures error handling : 
     * When sending fails, the handler should :
     * - mark an attempt for the log
     * - store the error message 
     * - flush changes
     * - rethrow the exception
     * @return void
     */
    public function testMarksAttemptAndBubblesExceptionOnSendFailure(): void
    {
        // New EmailLog
        $log = new EmailLog('id-3', 'user@example.com', 'Subj', 'Body');

        // Mock the repo to return that log
        $repo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repo->method('findOneBy')->with(['messageId' => 'id-3'])->willReturn($log);

        // Mock mailer to thorw and exception when we call send
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new \RuntimeException('SMTP down'));

        // Mock EntityManager expect 1 flush()
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->expects($this->once())->method('flush');

        // Instantiate handler
        $handler = new SendEmailMessageHandler($mailer, $em);

        // Expect it to throw
        // Log has 1 attempt
        // lastError = 'SMTP down', status still = "queued"
        try {
            $handler(new SendEmailMessage('id-3', 'user@example.com', 'Subj', 'Body'));
            $this->fail('Exception should have been thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('SMTP down', $e->getMessage());
            $this->assertSame(1, $log->getAttempts());
            $this->assertSame('SMTP down', $log->getLastError());
            $this->assertSame('queued', $log->getStatus());
        }
    }
}
