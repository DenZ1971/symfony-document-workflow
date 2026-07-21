<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Document;
use App\Enum\DocumentType;
use App\Exception\InvalidDocumentTransitionException;
use App\Repository\CustomerRepository;
use App\Repository\DocumentRepository;
use App\Service\DocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/documents')]
final class DocumentController extends AbstractController
{
    #[Route('', name: 'api_document_create', methods: ['POST'])]
    public function create(
        Request $request,
        CustomerRepository $customerRepository,
        DocumentService $documentService,
    ): JsonResponse {
        try {
            /** @var mixed $payload */
            $payload = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            return $this->json(
                ['error' => 'Invalid JSON payload.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (!is_array($payload)) {
            return $this->json(
                ['error' => 'JSON payload must be an object.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $typeValue = trim((string) ($payload['type'] ?? ''));
        $customerId = filter_var(
            $payload['customerId'] ?? null,
            FILTER_VALIDATE_INT,
        );

        if ($title === '') {
            return $this->json(
                ['error' => 'Field "title" is required.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($typeValue === '') {
            return $this->json(
                ['error' => 'Field "type" is required.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $type = DocumentType::tryFrom($typeValue);

        if ($type === null) {
            return $this->json(
                [
                    'error' => sprintf(
                        'Unsupported document type "%s".',
                        $typeValue,
                    ),
                    'allowedTypes' => array_map(
                        static fn (DocumentType $type): string => $type->value,
                        DocumentType::cases(),
                    ),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($customerId === false) {
            return $this->json(
                ['error' => 'Field "customerId" must be a valid integer.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $customer = $customerRepository->find($customerId);

        if (!$customer instanceof Customer) {
            return $this->json(
                ['error' => 'Customer was not found.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        $document = $documentService->create(
            title: $title,
            type: $type,
            customer: $customer,
        );

        return $this->json(
            $this->normalizeDocument($document),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_document_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        DocumentRepository $documentRepository,
    ): JsonResponse {
        $document = $documentRepository->find($id);

        if (!$document instanceof Document) {
            return $this->json(
                ['error' => 'Document was not found.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json(
            $this->normalizeDocument($document),
        );
    }

    #[Route('/{id}/validate', name: 'api_document_validate', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function validateDocument(
        int $id,
        DocumentRepository $documentRepository,
        DocumentService $documentService,
    ): JsonResponse {
        return $this->applyTransition(
            id: $id,
            documentRepository: $documentRepository,
            transition: static fn (Document $document) => $documentService->validate($document),
        );
    }

    #[Route('/{id}/send', name: 'api_document_send', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function send(
        int $id,
        DocumentRepository $documentRepository,
        DocumentService $documentService,
    ): JsonResponse {
        return $this->applyTransition(
            id: $id,
            documentRepository: $documentRepository,
            transition: static fn (Document $document) => $documentService->send($document),
        );
    }

    #[Route('/{id}/complete', name: 'api_document_complete', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function complete(
        int $id,
        DocumentRepository $documentRepository,
        DocumentService $documentService,
    ): JsonResponse {
        return $this->applyTransition(
            id: $id,
            documentRepository: $documentRepository,
            transition: static fn (Document $document) => $documentService->complete($document),
        );
    }

    #[Route('/{id}/cancel', name: 'api_document_cancel', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function cancel(
        int $id,
        DocumentRepository $documentRepository,
        DocumentService $documentService,
    ): JsonResponse {
        return $this->applyTransition(
            id: $id,
            documentRepository: $documentRepository,
            transition: static fn (Document $document) => $documentService->cancel($document),
        );
    }

    /**
     * @param callable(Document): void $transition
     */
    private function applyTransition(
        int $id,
        DocumentRepository $documentRepository,
        callable $transition,
    ): JsonResponse {
        $document = $documentRepository->find($id);

        if (!$document instanceof Document) {
            return $this->json(
                ['error' => 'Document was not found.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        try {
            $transition($document);
        } catch (InvalidDocumentTransitionException $exception) {
            return $this->json(
                ['error' => $exception->getMessage()],
                Response::HTTP_CONFLICT,
            );
        }

        return $this->json(
            $this->normalizeDocument($document),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDocument(Document $document): array
    {
        return [
            'id' => $document->getId(),
            'number' => $document->getNumber(),
            'title' => $document->getTitle(),
            'type' => $document->getType()?->value,
            'state' => $document->getState(),
            'customer' => [
                'id' => $document->getCustomer()?->getId(),
                'name' => $document->getCustomer()?->getName(),
                'email' => $document->getCustomer()?->getEmail(),
            ],
            'createdAt' => $document
                ->getCreatedAt()
                ?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $document
                ->getUpdatedAt()
                ?->format(\DateTimeInterface::ATOM),
        ];
    }
}