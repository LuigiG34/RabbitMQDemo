<?php

namespace App\Tests\Functional;

use App\Entity\EmailLog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EmailControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $tool = new SchemaTool($this->em);
        $meta = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropDatabase();
        if ($meta) {
            $tool->createSchema($meta);
        }
    }

    /**
     * Sends invalid JSON to /emails/send and expects:
     * - HTTP 400
     * - JSON body: {"error":"Invalid JSON."}
     * @return void
     */
    public function testInvalidJson(): void
    {
        // Sends a POST with Content-Type: application/json but the body is invalid JSON.
        $this->client->request('POST', '/emails/send', server: ['CONTENT_TYPE' => 'application/json'], content: 'not-json');
        // The controller returns 400 and a small JSON error.
        $this->assertResponseStatusCodeSame(400);
        // assertJsonStringEqualsJsonString compares JSON semantically (order-insensitive).
        $this->assertJsonStringEqualsJsonString('{"error":"Invalid JSON."}', $this->client->getResponse()->getContent());
    }

    /**
     * Posts a single invalid DTO and expects:
     * - 200 OK (batch-style envelope)
     * - results[0].ok = false
     * - violations reported for recipient, subject, and body.
     * @return void
     */
    public function testValidationErrors(): void
    {
        // Sends a JSON payload with just an invalid recipient, missing subject & body.
        $payload = json_encode(['recipient' => 'not-an-email']);
        $this->client->request('POST', '/emails/send', server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        $this->assertResponseIsSuccessful(); // Code 200
        $json = json_decode($this->client->getResponse()->getContent(), true);

        // The batch contains 1 item (count: 1)
        // That item is not ok
        // Violations include subject, body, and recipient.
        $this->assertSame(1, $json['count']);
        $this->assertFalse($json['results'][0]['ok']);
        $this->assertArrayHasKey('subject', $json['results'][0]['violations']);
        $this->assertArrayHasKey('body',    $json['results'][0]['violations']);
        $this->assertArrayHasKey('recipient',$json['results'][0]['violations']);
    }

    /**
     * Posts one valid email and expects:
     * - 200 OK with results[0].ok = true, priority = 3, transport = async_high
     * - Handler runs synchronously in test, so DB has one EmailLog marked "sent".
     * @return void
     */
    public function testSingleValidEmailDispatchesAndLogs(): void
    {
        // Send a valid single email
        $payload = json_encode([
            'recipient' => 'user@example.com',
            'subject'   => 'Hello',
            'body'      => 'World',
            'priority'  => 3,
        ]);

        $this->client->request('POST', '/emails/send', server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        // The controller validates, enqueues the message with HIGH priority mapped to "async_high", and returns success int the vatch response
        $this->assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame(1, $json['count']);
        $this->assertTrue($json['results'][0]['ok']);
        $this->assertSame(3, $json['results'][0]['priority']);
        $this->assertSame('async_high', $json['results'][0]['transport']);

        // SendEmailMessageHandler ran immediatly : it persisted an EmailLog and marked it "sent"
        $repo = $this->em->getRepository(EmailLog::class);
        $logs = $repo->findAll();

        // Assert that the DB reflects the comment above
        $this->assertCount(1, $logs);
        $this->assertSame('sent', $logs[0]->getStatus());
        $this->assertSame('user@example.com', $logs[0]->getRecipient());
    }

    /**
     * Posts a batch with 2 valid + 1 invalid items and expects:
     * - 200 OK with 3 results (ok, not ok, ok)
     * - Because transports are sync in test, two EmailLog rows are persisted.
     * @return void
     */
    public function testBatchMixedValidInvalid(): void
    {
        // Send an array of 3 emails 2 valid, 1 invalid
        $payload = json_encode([
            [ 'recipient' => 'ok1@example.com', 'subject' => 'a', 'body' => 'x', 'priority' => 1 ],
            [ 'recipient' => 'bad', 'subject' => '', 'body' => '' ],
            [ 'recipient' => 'ok2@example.com', 'subject' => 'b',  'body' => 'y', 'priority' => 2 ],
        ]);

        $this->client->request('POST', '/emails/send', server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        $this->assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);

        // 2 emails valid and 1 invalid
        $this->assertSame(3, $json['count']);
        $this->assertTrue($json['results'][0]['ok']);
        $this->assertFalse($json['results'][1]['ok']);
        $this->assertTrue($json['results'][2]['ok']);

        // Assert 2 logs exist in our DB
        $repo = $this->em->getRepository(EmailLog::class);
        $this->assertCount(2, $repo->findAll());
    }
}
