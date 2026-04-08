<?php

namespace App\Controller\user;

use App\service\UserProfileService;
use App\service\BookingService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Form\BookingFrontType;

class FrontController extends AbstractController
{
    private $profileService;
    private $destinationService;
    private $activityService;
    private $bookingService;

    public function __construct(UserProfileService $profileService, \App\service\DestinationService $destinationService, \App\service\ActivityService $activityService, BookingService $bookingService)
    {
        $this->profileService = $profileService;
        $this->destinationService = $destinationService;
        $this->activityService = $activityService;
        $this->bookingService = $bookingService;
    }

    #[Route('/home', name: 'index')]
    public function index(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        return $this->render('front/index.html.twig');
    }

    #[Route('/destinations', name: 'destinations')]
    public function destinations(): Response
    {
        $destinations = $this->destinationService->getAll();
        return $this->render('front/destinations.html.twig', [
            'destinations' => $destinations
        ]);
    }

    #[Route('/destination/{id}', name: 'destination_detail')]
    public function destinationDetail(string $id): Response
    {
        $destination = $this->destinationService->find($id);
        if (!$destination) {
            $this->addFlash('error', 'Destination not found.');
            return $this->redirectToRoute('destinations');
        }

        // Get activities for this destination
        $allActivities = $this->activityService->getAll();
        $destActivities = array_filter($allActivities, function($a) use ($id) {
            return $a->getDestinationId() == $id;
        });

        return $this->render('front/destination-detail.html.twig', [
            'destination' => $destination,
            'activities' => $destActivities
        ]);
    }

    #[Route('/activities', name: 'activities')]
    public function activities(): Response
    {
        $activities = $this->activityService->getAll();

        // Build destination names map for each activity
        $destNames = [];
        foreach ($activities as $act) {
            $did = $act->getDestinationId();
            if ($did && !isset($destNames[$did])) {
                $d = $this->destinationService->find($did);
                $destNames[$did] = $d ? $d->getName() : 'Unknown';
            }
        }

        return $this->render('front/activities.html.twig', [
            'activities' => $activities,
            'destNames' => $destNames
        ]);
    }

    #[Route('/activity/{id}', name: 'activity_detail')]
    public function activityDetail(int $id): Response
    {
        $activity = $this->activityService->find($id);
        if (!$activity) {
            $this->addFlash('error', 'Activity not found.');
            return $this->redirectToRoute('activities');
        }

        $destName = 'Unknown';
        $destination = null;
        if ($activity->getDestinationId()) {
            $destination = $this->destinationService->find($activity->getDestinationId());
            if ($destination) $destName = $destination->getName();
        }

        return $this->render('front/activity_detail.html.twig', [
            'activity' => $activity,
            'destination' => $destination,
            'destName' => $destName
        ]);
    }

    #[Route('/accommodations', name: 'accommodations')]
    public function accommodations(): Response
    {
        return $this->render('front/accommodations.html.twig');
    }

    #[Route('/transport', name: 'transport')]
    public function transport(): Response
    {
        return $this->render('front/transport.html.twig');
    }

    #[Route('/offers', name: 'offers')]
    public function offers(): Response
    {
        return $this->render('front/offers.html.twig');
    }

    #[Route('/blog', name: 'blog')]
    public function blog(): Response
    {
        return $this->render('front/blog.html.twig');
    }

    #[Route('/users', name: 'users')]
    public function users(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $data = $this->profileService->getProfileData($user);
        return $this->render('front/users.html.twig', $data);
    }

    #[Route('/profile/update', name: 'profile_update', methods: ['POST'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) return new JsonResponse(['success' => false], 401);

        $data = json_decode($request->getContent(), true);
        if ($data) {
            $this->profileService->updateProfile($user, $data);
            return new JsonResponse(['success' => true]);
        }
        return new JsonResponse(['success' => false], 400);
    }

    #[Route('/profile/password', name: 'profile_password', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) return new JsonResponse(['success' => false], 401);

        $data = json_decode($request->getContent(), true);
        if (!empty($data['password'])) {
            $this->profileService->changePassword($user, $data['password']);
            return new JsonResponse(['success' => true]);
        }
        return new JsonResponse(['success' => false], 400);
    }

    #[Route('/search', name: 'search')]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '');
        return $this->render('front/search_results.html.twig', [
            'query' => $query
        ]);
    }

    // ═══════════════════ BOOKINGS ═══════════════════

    #[Route('/booking/{destinationId}', name: 'booking_form')]
    public function bookingForm(string $destinationId, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $destination = $this->destinationService->find($destinationId);
        if (!$destination) {
            $this->addFlash('error', 'Destination not found.');
            return $this->redirectToRoute('destinations');
        }

        $booking = new \App\Entity\Booking();
        $booking->setUserId($user->getUserId());
        $booking->setDestinationId($destinationId);
        $booking->setUserEmail($user->getEmail());
        $booking->setCurrency('USD');

        $form = $this->createForm(BookingFrontType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculate total: estimatedBudget * numGuests * days
            $start = $booking->getStartAt();
            $end = $booking->getEndAt();
            $days = max(1, $start->diff($end)->days);
            
            $userBudget = $request->request->get('userBudget');
            if ($userBudget !== null && (float) $userBudget > 0) {
                $budgetPerPerson = (float) $userBudget;
            } else {
                $estimatedBudget = $destination->getEstimatedBudget();
                $budgetPerPerson = $estimatedBudget !== null ? (float) $estimatedBudget : 100.0;
            }
            
            $totalAmount = $budgetPerPerson * $booking->getNumGuests();

            $booking->setTotalAmount(number_format($totalAmount, 2, '.', ''));

            $this->bookingService->save($booking);
            $this->addFlash('success', 'Booking confirmed! Reference: ' . $booking->getBookingReference());
            return $this->redirectToRoute('my_bookings');
        }

        return $this->render('front/booking_form.html.twig', [
            'destination' => $destination,
            'form' => $form->createView()
        ]);
    }

    #[Route('/my-bookings', name: 'my_bookings')]
    public function myBookings(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $bookings = $this->bookingService->getByUser($user->getUserId());

        // Fetch destination names
        $destNames = [];
        foreach ($bookings as $b) {
            $did = $b->getDestinationId();
            if ($did && !isset($destNames[$did])) {
                $d = $this->destinationService->find($did);
                $destNames[$did] = $d ? $d->getName() : 'Unknown';
            }
        }

        return $this->render('front/my_bookings.html.twig', [
            'bookings' => $bookings,
            'destNames' => $destNames
        ]);
    }

    #[Route('/my-bookings/cancel/{id}', name: 'booking_cancel')]
    public function cancelBooking(int $id): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $booking = $this->bookingService->find($id);
        if ($booking && $booking->getUserId() === $user->getUserId() && $booking->getStatus() === 'pending') {
            $booking->setStatus('cancelled');
            $this->bookingService->save($booking);
            $this->addFlash('success', 'Booking cancelled.');
        } else {
            $this->addFlash('error', 'Cannot cancel this booking.');
        }

        return $this->redirectToRoute('my_bookings');
    }
}