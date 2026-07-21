<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/customers')]
final class CustomerController extends AbstractController
{
    #[Route('', name: 'api_customer_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ManagerRegistry $doctrine,
    ): JsonResponse {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode(
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

        if (!is_array($decoded)) {
            return $this->json(
                ['error' => 'JSON payload must be an object.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $name = trim((string) ($decoded['name'] ?? ''));
        $email = trim((string) ($decoded['email'] ?? ''));

        if ($name === '') {
            return $this->json(
                ['error' => 'Field "name" is required.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(
                ['error' => 'Field "email" must contain a valid email address.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $customerRepository = $doctrine->getRepository(Customer::class);

        if ($customerRepository->findOneBy(['email' => $email]) !== null) {
            return $this->json(
                ['error' => 'Customer with this email already exists.'],
                Response::HTTP_CONFLICT,
            );
        }

        $now = new \DateTimeImmutable();

        $customer = new Customer();
        $customer
            ->setName($name)
            ->setEmail($email)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $entityManager->persist($customer);
        $entityManager->flush();

        return $this->json(
            [
                'id' => $customer->getId(),
                'name' => $customer->getName(),
                'email' => $customer->getEmail(),
                'createdAt' => $customer->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'updatedAt' => $customer->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            ],
            Response::HTTP_CREATED,
        );
    }
}