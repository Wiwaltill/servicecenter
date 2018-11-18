<?php

namespace App\Controller;

use App\Entity\Device;
use App\Entity\Problem;
use App\Entity\ProblemType as ProblemTypeEntity;
use App\Event\ProblemEvent;
use App\Event\ProblemEvents;
use App\Form\ProblemType;
use App\Repository\DeviceRepositoryInterface;
use App\Repository\ProblemRepository;
use App\Repository\ProblemRepositoryInterface;
use App\Security\Voter\ProblemVoter;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProblemsController extends Controller {
    const SORT_COLUMNS = [ 'priority', 'status', 'updatedAt' ];
    const SORT_ORDER = [ 'asc', 'desc' ];

    private $problemRepository;

    public function __construct(ProblemRepositoryInterface $problemRepository) {
        $this->problemRepository = $problemRepository;
    }

    /**
     * @Route("/myproblems", name="my_problems")
     */
    public function index(Request $request) {
        $sort = $request->query->get('sort', null);
        $order = $request->query->get('order', 'asc');

        if(!in_array($sort, static::SORT_COLUMNS) || !in_array($order, static::SORT_ORDER)) {
            $sort = null;
            $order = 'asc';
        }

        $problems = $this->problemRepository->findByUser($this->getUser(), $sort, $order);
        $myproblems = $this->problemRepository->findByContactPerson($this->getUser(), $sort, $order);

        return $this->render('problems/index.html.twig', [
            'problems' => $problems,
            'myproblems' => $myproblems
        ]);
    }

    /**
     * @Route("/myproblems/add", name="new_problem")
     */
    public function add(Request $request, EventDispatcherInterface $eventDispatcher) {
        $problem = new Problem();

        $form = $this->createForm(ProblemType::class, $problem, [ 'show_options' => $this->isGranted('ROLE_AG_USER') ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($problem);
            $em->flush();

            $eventDispatcher
                ->dispatch(ProblemEvents::NEW_PROBLEM, new ProblemEvent($problem));

            $this->addFlash('success', 'problems.add.success');
            return $this->redirectToRoute('my_problems');
        }

        return $this->render('problems/add.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/myproblems/{id}/edit", name="edit_problem")
     */
    public function edit(Request $request, Problem $problem) {
        $em = $this->getDoctrine()->getManager();

        $this->denyAccessUnlessGranted(ProblemVoter::EDIT, $problem);

        $form = $this->createForm(ProblemType::class, $problem, [ 'show_options' => $this->isGranted('ROLE_AG_USER') ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em->persist($problem);
            $em->flush();

            $this->addFlash('success', 'problems.edit.success');
            return $this->redirectToRoute('show_problem', [ 'id' => $problem->getId() ]);
        }

        return $this->render('problems/edit.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/myproblems/{id}", name="show_problem")
     */
    public function show(Request $request, Problem $problem) {
        $em = $this->getDoctrine()->getManager();

        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);

        if($this->isGranted('ROLE_AG_USER')) {
            return $this->redirectToRoute('admin_show_problem', [
                'id' => $problem->getId()
            ]);
        }

        return $this->render('problems/show.html.twig', [
            'problem' => $problem
        ]);
    }

    /**
     * @Route("/myproblems/add/ajax", name="problem_ajax")
     */
    public function ajax(Request $request, DeviceRepositoryInterface $deviceRepository) {
        $deviceId = $request->query->get('device', null);

        if($deviceId === null) {
            return new JsonResponse([ ]);
        }

        /** @var Device $device */
        $device = $deviceRepository
            ->findOneById($deviceId);

        if($device === null) {
            throw new NotFoundHttpException();
        }

        /** @var ProblemTypeEntity[] $types */
        $types = $device->getType()->getProblemTypes();

        $result = [ ];
        foreach($types as $type) {
            $result[] = [
                'id' => $type->getId(),
                'name' => $type->getName()
            ];
        }

        return new JsonResponse($result);
    }
}