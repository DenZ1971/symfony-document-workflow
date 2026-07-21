<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Customer;
use App\Entity\Document;
use App\Enum\DocumentType;
use App\Exception\InvalidDocumentTransitionException;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\WorkflowInterface;

final class DocumentServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private WorkflowInterface&MockObject $workflow;
    private DocumentService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(
            EntityManagerInterface::class
        );

        $this->workflow = $this->createMock(
            WorkflowInterface::class
        );

        $this->service = new DocumentService(
            entityManager: $this->entityManager,
            documentStateMachine: $this->workflow,
        );
    }

    public function testCreatePersistsDocument(): void
    {
        $customer = $this->createCustomer();

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with(self::callback(
                static function (object $entity) use ($customer): bool {
                    self::assertInstanceOf(Document::class, $entity);
                    self::assertSame('Service contract', $entity->getTitle());
                    self::assertSame(DocumentType::CONTRACT, $entity->getType());
                    self::assertSame($customer, $entity->getCustomer());
                    self::assertSame(Document::STATE_DRAFT, $entity->getState());

                    self::assertMatchesRegularExpression(
                        '/^DOC-\d{4}-[A-F0-9]{6}$/',
                        (string) $entity->getNumber()
                    );

                    self::assertNotNull($entity->getCreatedAt());
                    self::assertNotNull($entity->getUpdatedAt());

                    return true;
                }
            ));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $document = $this->service->create(
            title: 'Service contract',
            type: DocumentType::CONTRACT,
            customer: $customer,
        );

        self::assertSame('Service contract', $document->getTitle());
        self::assertSame(DocumentType::CONTRACT, $document->getType());
        self::assertSame($customer, $document->getCustomer());
        self::assertSame(Document::STATE_DRAFT, $document->getState());
    }

    public function testValidateAppliesWorkflowTransition(): void
    {
        $document = $this->createDocument();

        $this->workflow
            ->expects($this->once())
            ->method('can')
            ->with($document, 'validate')
            ->willReturn(true);

        $this->workflow
            ->expects($this->once())
            ->method('apply')
            ->with($document, 'validate')
            ->willReturnCallback(
                static function (Document $document): Marking {
                    $document->setState(Document::STATE_VALIDATED);

                    return new Marking([
                        Document::STATE_VALIDATED => 1,
                    ]);
                }
            );

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->validate($document);

        self::assertSame(
            Document::STATE_VALIDATED,
            $document->getState()
        );
    }

    public function testSendAppliesWorkflowTransition(): void
    {
        $document = $this->createDocument();
        $document->setState(Document::STATE_VALIDATED);

        $this->workflow
            ->expects($this->once())
            ->method('can')
            ->with($document, 'send')
            ->willReturn(true);

        $this->workflow
            ->expects($this->once())
            ->method('apply')
            ->with($document, 'send')
            ->willReturnCallback(
                static function (Document $document): Marking {
                    $document->setState(Document::STATE_SENT);

                    return new Marking([
                        Document::STATE_SENT => 1,
                    ]);
                }
            );

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->send($document);

        self::assertSame(
            Document::STATE_SENT,
            $document->getState()
        );
    }

    public function testInvalidTransitionThrowsException(): void
    {
        $document = $this->createDocument();

        $this->workflow
            ->expects($this->once())
            ->method('can')
            ->with($document, 'complete')
            ->willReturn(false);

        $this->workflow
            ->expects($this->never())
            ->method('apply');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->expectException(
            InvalidDocumentTransitionException::class
        );

        $this->expectExceptionMessage(
            'Transition "complete" cannot be applied'
        );

        $this->service->complete($document);
    }

    public function testCancelAppliesWorkflowTransition(): void
    {
        $document = $this->createDocument();

        $this->workflow
            ->expects($this->once())
            ->method('can')
            ->with($document, 'cancel')
            ->willReturn(true);

        $this->workflow
            ->expects($this->once())
            ->method('apply')
            ->with($document, 'cancel')
            ->willReturnCallback(
                static function (Document $document): Marking {
                    $document->setState(Document::STATE_CANCELLED);

                    return new Marking([
                        Document::STATE_CANCELLED => 1,
                    ]);
                }
            );

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->cancel($document);

        self::assertSame(
            Document::STATE_CANCELLED,
            $document->getState()
        );
    }

    private function createCustomer(): Customer
    {
        $now = new \DateTimeImmutable();

        $customer = new Customer();
        $customer
            ->setName('Example s.r.o.')
            ->setEmail('office@example.sk')
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        return $customer;
    }

    private function createDocument(): Document
    {
        $now = new \DateTimeImmutable();

        $document = new Document();
        $document
            ->setNumber('DOC-2026-000001')
            ->setTitle('Service contract')
            ->setType(DocumentType::CONTRACT)
            ->setCustomer($this->createCustomer())
            ->setState(Document::STATE_DRAFT)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        return $document;
    }
}