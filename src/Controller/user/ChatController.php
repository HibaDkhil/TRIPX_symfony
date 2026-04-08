<?php

namespace App\Controller\user;

use App\Entity\User;
use App\Entity\Preference;
use App\Entity\Destination;
use App\Entity\Activity;
use App\service\GeminiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ChatController extends AbstractController
{
    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(Request $request, GeminiService $gemini, EntityManagerInterface $em): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'You must be logged in'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';
        $image = $data['image'] ?? null;

        if (empty($message) && !$image) {
            return $this->json(['error' => 'No message provided'], 400);
        }

        // Get user preferences
        /** @var Preference|null $preferences */
        $preferences = $em->getRepository(Preference::class)->findOneBy(['userId' => $user->getUserId()]);
        
        // Get destinations from database
        $destinations = $em->getRepository(Destination::class)->findAll();
        $destinationsData = [];
        foreach ($destinations as $dest) {
            $destinationsData[] = [
                'name' => $dest->getName(),
                'country' => $dest->getCountry(),
                'type' => $dest->getType(),
                'estimatedBudget' => $dest->getEstimatedBudget(),
            ];
        }

        // Get activities
        $activities = $em->getRepository(Activity::class)->findAll();
        $activitiesData = [];
        foreach ($activities as $act) {
            $activitiesData[] = [
                'name' => $act->getName(),
                'category' => $act->getCategory(),
            ];
        }

        $context = [
            'user' => [
                'firstName' => $user->getFirstName(),
                'gender' => $user->getGender(),
                'birthYear' => $user->getBirthYear(),
            ],
            'preferences' => $preferences ? [
                'budgetMinPerNight' => $preferences->getBudgetMinPerNight(),
                'budgetMaxPerNight' => $preferences->getBudgetMaxPerNight(),
                'priorities' => $preferences->getPriorities(),
                'preferredClimate' => $preferences->getPreferredClimate(),
                'travelPace' => $preferences->getTravelPace(),
                'groupType' => $preferences->getGroupType(),
                'accommodationTypes' => $preferences->getAccommodationTypes(),
            ] : [],
            'destinations' => $destinationsData,
            'activities' => $activitiesData,
        ];

        try {
            if ($image) {
                $response = $gemini->analyzeImage($image, $message);
            } else {
                $response = $gemini->chat($message, $context);
            }
            
            return $this->json(['response' => $response]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'AI service error: ' . $e->getMessage()], 500);
        }
    }
}