<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Document;
use App\Enum\DocumentType;
use App\Exception\InvalidDocumentTransitionException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

final class DocumentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,

        #[Autowire(service: 'state_machine.document')]
        private readonly WorkflowInterface $documentStateMachine,
    ) {
    }

    public function create(
        string $title,
        DocumentType $type,
        Customer $customer,
    ): Document {
        $now = new \DateTimeImmutable();

        $document = new Document();
        $document
            ->setNumber($this->generateNumber())
            ->setTitle($title)
            ->setType($type)
            ->setCustomer($customer)
            ->setState(Document::STATE_DRAFT)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    public function validate(Document $document): void
    {
        $this->applyTransition($document, 'validate');
    }

    public function send(Document $document): void
    {
        $this->applyTransition($document, 'send');
    }

    public function complete(Document $document): void
    {
        $this->applyTransition($document, 'complete');
    }

    public function cancel(Document $document): void
    {
        $this->applyTransition($document, 'cancel');
    }

    private function applyTransition(
        Document $document,
        string $transition,
    ): void {
        if (!$this->documentStateMachine->can($document, $transition)) {
            throw InvalidDocumentTransitionException::create(
                $document,
                $transition,
            );
        }

        $this->documentStateMachine->apply($document, $transition);

        $document->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    private function generateNumber(): string
    {
        return sprintf(
            'DOC-%s-%s',
            (new \DateTimeImmutable())->format('Y'),
            strtoupper(bin2hex(random_bytes(3))),
        );
    }
}