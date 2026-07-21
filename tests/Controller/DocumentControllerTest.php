<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Customer;
use App\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class DocumentControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    /**
     * @var list<Document>
     */
    private array $documentsToRemove = [];

    /**
     * @var list<Customer>
     */
    private array $customersToRemove = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $this->entityManager = self::getContainer()->get(
            EntityManagerInterface::class
        );
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->documentsToRemove) as $document) {
            if ($this->entityManager->contains($document)) {
                $this->entityManager->remove($document);
            }
        }

        foreach (array_reverse($this->customersToRemove) as $customer) {
            if ($this->entityManager->contains($customer)) {
                $this->entityManager->remove($customer);
            }
        }

        $this->entityManager->flush();
        $this->entityManager->close();

        parent::tearDown();
    }

    public function testDocumentLifeCycle(): void
    {
        $customerId = $this->createCustomer();
        $documentId = $this->createDocument($customerId);

        $document = $this->getDocument($documentId);

        self::assertSame(Document::STATE_DRAFT, $document['state']);
        self::assertSame('Service contract', $document['title']);
        self::assertSame('contract', $document['type']);
        self::assertSame($customerId, $document['customer']['id']);

        $validatedDocument = $this->applyTransition(
            $documentId,
            'validate'
        );

        self::assertSame(
            Document::STATE_VALIDATED,
            $validatedDocument['state']
        );

        $sentDocument = $this->applyTransition(
            $documentId,
            'send'
        );

        self::assertSame(
            Document::STATE_SENT,
            $sentDocument['state']
        );

        $completedDocument = $this->applyTransition(
            $documentId,
            'complete'
        );

        self::assertSame(
            Document::STATE_COMPLETED,
            $completedDocument['state']
        );

        $storedDocument = $this->getDocument($documentId);

        self::assertSame(
            Document::STATE_COMPLETED,
            $storedDocument['state']
        );
    }

    public function testDocumentCanBeCancelled(): void
    {
        $customerId = $this->createCustomer();
        $documentId = $this->createDocument($customerId);

        $document = $this->applyTransition(
            $documentId,
            'cancel'
        );

        self::assertSame(
            Document::STATE_CANCELLED,
            $document['state']
        );

        $storedDocument = $this->getDocument($documentId);

        self::assertSame(
            Document::STATE_CANCELLED,
            $storedDocument['state']
        );
    }

    public function testDocumentCannotBeCompletedFromDraft(): void
    {
        $customerId = $this->createCustomer();
        $documentId = $this->createDocument($customerId);

        $this->client->request(
            method: 'PATCH',
            uri: sprintf(
                '/api/documents/%d/complete',
                $documentId
            ),
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_CONFLICT
        );

        $response = $this->decodeResponse();

        self::assertArrayHasKey('error', $response);
        self::assertStringContainsString(
            'Transition "complete" cannot be applied',
            (string) $response['error']
        );

        $document = $this->getDocument($documentId);

        self::assertSame(
            Document::STATE_DRAFT,
            $document['state']
        );
    }

    public function testCreateReturnsNotFoundForUnknownCustomer(): void
    {
        $this->client->jsonRequest(
            method: 'POST',
            uri: '/api/documents',
            parameters: [
                'title' => 'Service contract',
                'type' => 'contract',
                'customerId' => 999999999,
            ],
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_NOT_FOUND
        );

        self::assertSame(
            ['error' => 'Customer was not found.'],
            $this->decodeResponse()
        );
    }

    public function testCreateReturnsUnprocessableEntityForInvalidType(): void
    {
        $customerId = $this->createCustomer();

        $this->client->jsonRequest(
            method: 'POST',
            uri: '/api/documents',
            parameters: [
                'title' => 'Unsupported document',
                'type' => 'unsupported_type',
                'customerId' => $customerId,
            ],
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $response = $this->decodeResponse();

        self::assertSame(
            'Unsupported document type "unsupported_type".',
            $response['error']
        );

        self::assertArrayHasKey('allowedTypes', $response);
        self::assertContains('contract', $response['allowedTypes']);
    }

    public function testDocumentReturnsNotFound(): void
    {
        $this->client->request(
            method: 'GET',
            uri: '/api/documents/999999999',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_NOT_FOUND
        );

        self::assertSame(
            ['error' => 'Document was not found.'],
            $this->decodeResponse()
        );
    }

    public function testCreateReturnsBadRequestForInvalidJson(): void
    {
        $this->client->request(
            method: 'POST',
            uri: '/api/documents',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: '{"title": "Broken JSON"',
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_BAD_REQUEST
        );

        self::assertSame(
            ['error' => 'Invalid JSON payload.'],
            $this->decodeResponse()
        );
    }

    private function createCustomer(): int
    {
        $email = sprintf(
            'document-test-%s@example.sk',
            bin2hex(random_bytes(6))
        );

        $this->client->jsonRequest(
            method: 'POST',
            uri: '/api/customers',
            parameters: [
                'name' => 'Document Test s.r.o.',
                'email' => $email,
            ],
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_CREATED
        );

        $response = $this->decodeResponse();

        self::assertArrayHasKey('id', $response);
        self::assertIsInt($response['id']);

        $customer = $this->entityManager
            ->getRepository(Customer::class)
            ->find($response['id']);

        self::assertInstanceOf(Customer::class, $customer);

        $this->customersToRemove[] = $customer;

        return $response['id'];
    }

    private function createDocument(int $customerId): int
    {
        $this->client->jsonRequest(
            method: 'POST',
            uri: '/api/documents',
            parameters: [
                'title' => 'Service contract',
                'type' => 'contract',
                'customerId' => $customerId,
            ],
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_CREATED
        );

        $response = $this->decodeResponse();

        self::assertArrayHasKey('id', $response);
        self::assertIsInt($response['id']);
        self::assertSame(Document::STATE_DRAFT, $response['state']);

        $document = $this->entityManager
            ->getRepository(Document::class)
            ->find($response['id']);

        self::assertInstanceOf(Document::class, $document);

        $this->documentsToRemove[] = $document;

        return $response['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDocument(int $documentId): array
    {
        $this->client->request(
            method: 'GET',
            uri: sprintf('/api/documents/%d', $documentId),
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();

        return $this->decodeResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function applyTransition(
        int $documentId,
        string $transition,
    ): array {
        $this->client->request(
            method: 'PATCH',
            uri: sprintf(
                '/api/documents/%d/%s',
                $documentId,
                $transition
            ),
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();

        return $this->decodeResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(): array
    {
        $content = $this->client
            ->getResponse()
            ->getContent();

        self::assertIsString($content);

        /** @var array<string, mixed> $response */
        $response = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return $response;
    }
}