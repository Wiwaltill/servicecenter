<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Problem;
use App\Entity\ProblemFilter;
use App\Form\CommentType;
use App\Form\ProblemFilterType;
use App\Form\StatusType;
use App\Helper\Problems\BulkActionManager;
use App\Repository\ProblemFilterRepositoryInterface;
use App\Repository\ProblemRepository;
use App\Repository\ProblemRepositoryInterface;
use App\Security\Voter\CommentVoter;
use App\Security\Voter\ProblemVoter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;

class ProblemsAdminController extends Controller {
    const FILTER_CSRF_TOKEN_ID = 'problems_filter_token';
    const CONTACTPERSON_CSRF_TOKEN_ID = 'problem_contactperson';
    const BULK_CSRF_TOKEN_ID = 'problem_bulk';

    private $problemRepository;

    public function __construct(ProblemRepositoryInterface $problemRepository) {
        $this->problemRepository = $problemRepository;
    }

    /**
     * @Route("/problems", name="problems")
     */
    public function index(Request $request, ProblemFilterRepositoryInterface $problemFilterRepository) {
        $em = $this->getDoctrine()->getManager();

        $q = $request->query->get('q', null);
        $page = $request->query->get('page', 1);

        if(empty($q)) {
            $q = null;
        }

        if($page < 1 || !is_numeric($page)) {
            $page = 1;
        }

        /** @var ProblemFilter $filter */
        $filter = $problemFilterRepository->findOneByUser($this->getUser());

        if($filter === null) {
            $filter = (new ProblemFilter())
                ->setUser($this->getUser());
        }

        $form = $this->createForm(ProblemFilterType::class, $filter, [ ]);
        $form->handleRequest($request);

        if($request->getMethod() === Request::METHOD_POST && $request->request->get('reset', null) !== null) {
            if($this->get('security.csrf.token_manager')->isTokenValid(new CsrfToken(static::FILTER_CSRF_TOKEN_ID, $request->request->get('reset')))) {
                $this->getDoctrine()->getManager()
                    ->createQueryBuilder()
                    ->delete(ProblemFilter::class, 'f')
                    ->where('f.user = :user')
                    ->setParameter('user', $this->getUser())
                    ->getQuery()
                    ->execute();

                $this->addFlash('success', 'problems.filter.reset.success');

                return $this->redirectToRoute('problems');
            } else {
                $this->addFlash('error', 'problems.filter.reset.csrf');
            }
        }

        if($form->isSubmitted() && $form->isValid()) {
            $em->persist($filter);
            $em->flush();

            return $this->redirectToRoute('problems', [
                'q' => $q
            ]);
        }

        $problems = $this->problemRepository->getProblems($filter, $page, $q);
        $problemCount = $this->problemRepository->countProblems($filter, $q);

        if($problemCount == 0) {
            $pages = 1;
        } else {
            $pages = ceil((double)$problemCount / $filter->getNumItems());
        }

        $csrfTokenResetFilter = $this->get('security.csrf.token_manager')->getToken(static::FILTER_CSRF_TOKEN_ID);
        $csrfTokenBulk = $this->get('security.csrf.token_manager')->getToken(static::BULK_CSRF_TOKEN_ID);

        return $this->render('problems/admin/index.html.twig', [
            'problems' => $problems,
            'q' => $q,
            'page' => $page,
            'pages' => $pages,
            'isFilterActive' => $filter->isDefaultFilter() !== true,
            'filterForm' => $form->createView(),
            'csrfTokenResetFilter' => $csrfTokenResetFilter,
            'csrfTokenBulk' => $csrfTokenBulk
        ]);
    }

    /**
     * @Route("/problems/{id}", name="admin_show_problem", requirements={"id": "\d+"})
     */
    public function show(Request $request, Problem $problem) {
        $em = $this->getDoctrine()->getManager();

        /*
         * STATUS FORM
         */

        $formStatus = $this->createForm(StatusType::class, $problem, [ ]);
        $formStatus->handleRequest($request);

        if($formStatus->isSubmitted() && $formStatus->isValid()) {
            $this->denyAccessUnlessGranted(ProblemVoter::STATUS, $problem);

            $em->persist($problem);
            $em->flush();

            $this->addFlash('success', 'problems.status.success');
            return $this->redirectToRoute('admin_show_problem', [
                'id' => $problem->getId()
            ]);
        }

        /*
         * ADD COMMENT FORM
         */
        $comment = (new Comment())
            ->setCreatedBy($this->getUser())
            ->setProblem($problem);

        $formComment = $this->createForm(CommentType::class, $comment, [ ]);
        $formComment->handleRequest($request);

        if($formComment->isSubmitted() && $formComment->isValid()) {
            $em->persist($comment);
            $em->flush();

            $this->addFlash('success', 'problems.comments.add.success');
            return $this->redirectToRoute('admin_show_problem', [
                'id' => $problem->getId()
            ]);
        }

        /*
         * EDIT COMMENT FORM
         */


        $csrfTokenContactperson = $this->get('security.csrf.token_manager')->getToken(static::CONTACTPERSON_CSRF_TOKEN_ID);

        return $this->render('problems/admin/show.html.twig', [
            'problem' => $problem,
            'formStatus' => $formStatus->createView(),
            'formComment' => $formComment->createView(),
            'csrfTokenContactperson' => $csrfTokenContactperson
        ]);
    }

    /**
     * @Route("/problems/{id}/contactperson", name="change_contactperson", methods={"POST"})
     */
    public function changeContactPerson(Request $request, Problem $problem) {
        $em = $this->getDoctrine()->getManager();

        $this->denyAccessUnlessGranted(ProblemVoter::CONTACTPERSON, $problem);

        if($this->get('security.csrf.token_manager')->isTokenValid(new CsrfToken(static::CONTACTPERSON_CSRF_TOKEN_ID, $request->request->get('_csrf_token'))) !== true) {
            $this->addFlash('error', 'problems.contactperson.csrf');

            return $this->redirectToRoute('admin_show_problem', [
                'id' => $problem->getId()
            ]);
        }

        if($problem->getContactPerson() === null) {
            $problem->setContactPerson($this->getUser());
        } else {
            $problem->setContactPerson(null);
        }

        $em->persist($problem);
        $em->flush();

        $this->addFlash('success', 'problems.contactperson.success');

        return $this->redirectToRoute('admin_show_problem', [
            'id' => $problem->getId()
        ]);
    }

    /**
     * @Route("/problems/{id}/comments/{commentId}/edit", name="edit_comment")
     * @ParamConverter("comment", options={"id": "commentId"})
     */
    public function editComment(Request $request, Comment $comment) {
        $em = $this->getDoctrine()->getManager();
        $this->denyAccessUnlessGranted(CommentVoter::EDIT, $comment);

        $form = $this->createForm(CommentType::class, $comment, [ ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em->persist($comment);
            $em->flush();

            $this->addFlash('success', 'problems.comments.edit.success');
            return $this->redirectToRoute('admin_show_problem', [
                'id' => $comment->getProblem()->getId()
            ]);
        }

        return $this->render('problems/admin/comments/edit.html.twig', [
            'form' => $form->createView(),
            'problem' => $comment->getProblem()
        ]);
    }

    /**
     * @Route("/problems/{id}/comments/{commentId}/remove", name="remove_comment")
     * @ParamConverter("comment", options={"id": "commentId"})
     */
    public function removeComment(Request $request, Comment $comment) {
        $em = $this->getDoctrine()->getManager();
        $this->denyAccessUnlessGranted(CommentVoter::DELETE, $comment);

        $form = $this->createForm(ConfirmType::class, null, [
            'message' => 'problems.comment.remove.confirm'
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em->remove($comment);
            $em->flush();

            $this->addFlash('success', 'problems.comments.remove.success');
            return $this->redirectToRoute('admin_show_problem', [
                'id' => $comment->getProblem()->getId()
            ]);
        }

        return $this->render('problems/admin/comments/delete.html.twig', [
            'form' => $form->createView(),
            'problem' => $comment->getProblem()
        ]);
    }

    /**
     * @Route("/problems/{id}/remove", name="remove_problem")
     */
    public function delete(Request $request) {

    }

    /**
     * @Route("/problems/bulk", name="admin_problems_bulk", methods={"POST"})
     */
    public function bulk(Request $request, BulkActionManager $bulkActionManager) {
        if($this->get('security.csrf.token_manager')->isTokenValid(new CsrfToken(static::BULK_CSRF_TOKEN_ID, $request->request->get('_csrf_token'))) !== true) {
            $this->addFlash('error', 'problems.bulk.csrf');

            return $this->redirectToRoute('problems');
        }

        $action = $request->request->get('action');

        if($bulkActionManager->canRunAction($action) !== true) {
            throw new BadRequestHttpException();
        }

        $ids = explode(',', $request->request->get('ids'));

        if(count($ids) == 0) {
            return $this->redirectToRoute('problems');
        }

        /** @var Problem[] $problems */
        $problems = $this->problemRepository
            ->findByIds($ids);

        $count = $bulkActionManager->run($problems, $action);

        $this->addFlash('success', $this->get('translator')->transChoice('problems.bulk.success', $count, ['%num%' => $count ]));
        return $this->redirectToRoute('problems');
    }
}