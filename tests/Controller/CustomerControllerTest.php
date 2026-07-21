<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CustomerControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $this->entityManager = self::getContainer()->get(
            EntityManagerInterface::class
        );
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();

        parent::tearDown();
    }

    public function testCustomerCanBeCreated(): void
    {
        $email = $this->uniqueEmail();

        $this->request([
            'name' => 'Example s.r.o.',
            'email' => $email,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertResponseHeaderSame(
            'content-type',
            'application/json'
        );

        $response = $this->decodeResponse();

        self::assertArrayHasKey('id', $response);
        self::assertSame('Example s.r.o.', $response['name']);
        self::assertSame($email, $response['email']);
        self::assertArrayHasKey('createdAt', $response);
        self::assertArrayHasKey('updatedAt', $response);

        $customer = $this->entityManager
            ->getRepository(Customer::class)
            ->findOneBy(['email' => $email]);

        self::assertInstanceOf(Customer::class, $customer);
        self::assertSame('Example s.r.o.', $customer->getName());

        $this->removeCustomer($customer);
    }

    public function testNameIsRequired(): void
    {
        $this->request([
            'email' => $this->uniqueEmail(),
        ]);

        self::assertResponseStatusCodeSame(
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        self::assertSame(
            ['error' => 'Field "name" is required.'],
            $this->decodeResponse()
        );
    }

    public function testEmailMustBeValid(): void
    {
        $this->request([
            'name' => 'Example s.r.o.',
            'email' => 'invalid-email',
        ]);

        self::assertResponseStatusCodeSame(
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        self::assertSame(
            [
                'error' =>
                    'Field "email" must contain a valid email address.',
            ],
            $this->decodeResponse()
        );
    }

    public function testDuplicateEmailReturnsConflict(): void
    {
        $email = $this->uniqueEmail();
        $now = new \DateTimeImmutable();

        $customer = new Customer();
        $customer
            ->setName('Existing customer')
            ->setEmail($email)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $this->request([
            'name' => 'Another company',
            'email' => $email,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        self::assertSame(
            ['error' => 'Customer with this email already exists.'],
            $this->decodeResponse()
        );

        $this->removeCustomer($customer);
    }

    public function testInvalidJsonReturnsBadRequest(): void
    {
        $this->client->request(
            method: 'POST',
            uri: '/api/customers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: '{"name": "Broken JSON"',
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_BAD_REQUEST
        );

        self::assertSame(
            ['error' => 'Invalid JSON payload.'],
            $this->decodeResponse()
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): void
    {
        $this->client->jsonRequest(
            method: 'POST',
            uri: '/api/customers',
            parameters: $payload,
        );
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

    private function uniqueEmail(): string
    {
        return sprintf(
            'customer-%s@example.sk',
            bin2hex(random_bytes(6))
        );
    }

    private function removeCustomer(Customer $customer): void
    {
        $this->entityManager->remove($customer);
        $this->entityManager->flush();
    }
}