<?php

namespace App\Tests\Integration;

use App\Entity\EmailLog;
use App\Message\SendEmailMessage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessageProcessingTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        // no kernel from a previous test is still running
        self::ensureKernelShutdown();
        // boots the kernel and prepares a BrowserKit client
        self::createClient();

        // Grab the EntityManager
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        // Reset the database fresh for this test
        $tool = new SchemaTool($this->em);
        $meta = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropDatabase();
        if ($meta) {
            $tool->createSchema($meta);
        }
    }

    public function testDispatchProcessesAndWritesEmailLog(): void
    {
        // Resolve the Messenger bus from the container
        $bus = self::getContainer()->get(MessageBusInterface::class);

        // Dispatch a Message
        $bus->dispatch(new SendEmailMessage('msg-1', 'user@example.com', 'Hello', 'World'));

        // The handler has run, we query the DB for the EmailLog with the same messageId
        $repo = $this->em->getRepository(EmailLog::class);
        $log = $repo->findOneBy(['messageId' => 'msg-1']);

        // Make sur the log exists
        // Its marked as sent
        // a sentAt timestamp was set
        // The recipient was the one we dispatched
        $this->assertNotNull($log);
        $this->assertSame('sent', $log->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $log->getSentAt());
        $this->assertSame('user@example.com', $log->getRecipient());
    }
}
