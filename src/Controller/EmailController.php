<?php

namespace App\Controller;

use App\DTO\SendEmailRequest;
use App\Service\EmailQueue;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/emails')]
class EmailController extends AbstractController
{
    #[Route('/send', name: 'email_send', methods: ['POST'])]
    public function send(
        Request $request,
        ValidatorInterface $validator,
        EmailQueue $emailQueue
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if ($payload === null) {
            return $this->json(['error' => 'Invalid JSON.'], 400);
        }

        $items = (is_array($payload) && array_is_list($payload)) ? $payload : [$payload];

        $results = [];
        foreach ($items as $idx => $data) {
            $dto = SendEmailRequest::fromArray(is_array($data) ? $data : []);
            $errors = $validator->validate($dto);

            if (count($errors) > 0) {
                $violations = [];
                foreach ($errors as $error) {
                    $violations[$error->getPropertyPath()][] = $error->getMessage();
                }
                $results[] = [
                    'index'      => $idx,
                    'ok'         => false,
                    'violations' => $violations,
                ];
                continue;
            }

            $emailQueue->dispatch($dto);

            $results[] = [
                'index'     => $idx,
                'ok'        => true,
                'priority'  => $dto->priority,
                'transport' => $dto->toPriority()->transport(),
            ];
        }

        return $this->json([
            'count'   => count($results),
            'results' => $results,
        ]);
    }
}
