<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Entity\Customer;
use App\Entity\Document;
use App\Enum\DocumentType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

final class DocumentWorkflowTest extends KernelTestCase
{
    private WorkflowInterface $workflow;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->workflow = self::getContainer()->get(
            'state_machine.document'
        );
    }

    public function testHappyPath(): void
    {
        $document = $this->createDocument();

        self::assertSame(
            Document::STATE_DRAFT,
            $document->getState()
        );

        self::assertTrue(
            $this->workflow->can($document, 'validate')
        );

        $this->workflow->apply($document, 'validate');

        self::assertSame(
            Document::STATE_VALIDATED,
            $document->getState()
        );

        self::assertTrue(
            $this->workflow->can($document, 'send')
        );

        $this->workflow->apply($document, 'send');

        self::assertSame(
            Document::STATE_SENT,
            $document->getState()
        );

        self::assertTrue(
            $this->workflow->can($document, 'complete')
        );

        $this->workflow->apply($document, 'complete');

        self::assertSame(
            Document::STATE_COMPLETED,
            $document->getState()
        );
    }

    public function testDraftCannotBeCompletedDirectly(): void
    {
        $document = $this->createDocument();

        self::assertFalse(
            $this->workflow->can($document, 'complete')
        );
    }

    public function testDraftCanBeCancelled(): void
    {
        $document = $this->createDocument();

        self::assertTrue(
            $this->workflow->can($document, 'cancel')
        );

        $this->workflow->apply($document, 'cancel');

        self::assertSame(
            Document::STATE_CANCELLED,
            $document->getState()
        );
    }

    private function createDocument(): Document
    {
        $customer = new Customer(
            'Example s.r.o.',
            'office@example.sk'
        );

        return new Document(
            'DOC-2026-0001',
            'Service contract',
            DocumentType::CONTRACT,
            $customer
        );
    }
}