<?php

namespace App\Controller;

use App\Entity\Food;
use App\Entity\User;
use App\OpenFoodFacts\OpenFoodFactsClient;
use App\Repository\FoodRepository;
use App\Service\FoodMaterializer;
use App\Service\FoodPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/foods')]
final class FoodController extends AbstractController
{
    public function __construct(
        private readonly FoodRepository $foodRepository,
        private readonly OpenFoodFactsClient $offClient,
        private readonly FoodPresenter $presenter,
        private readonly FoodMaterializer $materializer,
    ) {
    }

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));

        if ('' === $query) {
            return $this->json(['results' => []]);
        }

        $localFoods = $this->foodRepository->searchLocal($query, $user);
        $localOffIds = array_filter(array_map(static fn (Food $f) => $f->getOffId(), $localFoods));

        $results = array_map(fn (Food $f) => $this->presenter->present($f), $localFoods);

        foreach ($this->offClient->search($query, $page) as $offProduct) {
            if (in_array($offProduct['offId'], $localOffIds, true)) {
                continue;
            }
            $results[] = $this->presenter->presentOffProduct($offProduct);
        }

        return $this->json(['results' => $results]);
    }

    #[Route('/barcode/{barcode}', methods: ['GET'])]
    public function barcode(string $barcode): JsonResponse
    {
        $local = $this->foodRepository->findOneByBarcode($barcode);
        if (null !== $local) {
            return $this->json($this->presenter->present($local));
        }

        $offProduct = $this->offClient->getByBarcode($barcode);
        if (null === $offProduct) {
            return $this->json(['error' => 'product not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->presenter->presentOffProduct($offProduct));
    }

    #[Route('/from-off/{code}', methods: ['POST'])]
    public function materializeFromOff(string $code): JsonResponse
    {
        $food = $this->materializer->materialize($code);
        if (null === $food) {
            return $this->json(['error' => 'product not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->presenter->present($food), Response::HTTP_CREATED);
    }
}
