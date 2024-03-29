<?php

namespace App\Controller;

use App\Entity\Prospect;
use App\Form\ProspectType;
use App\Repository\ProspectRepository;
use App\Service\MailerService;
use App\Service\Pagination;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/prospect")
 */
class ProspectController extends AbstractController
{
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * @Route("/{page<\d+>?1}", name="prospect_index", methods={"GET"},)
     * @IsGranted("ROLE_USER")
     */
    public function index(Pagination $pagination, $page): Response
    {
        $pagination->setEntityClass(Prospect::class)->setPage($page);
        $prospects = $pagination->getData();

        return $this->render('prospect/index.html.twig', [
            'prospects' => $prospects,
            'pagination' => $pagination

        ]);
    }

    /**
     * @Route("/new", name="prospect_new", methods={"GET","POST"})
     */
    public function new(Request $request, MailerService $mailerService): Response
    {
        if (!$this->session->has('value')) {
            $prospect = new Prospect();
        } else {
            $prospect = $this->session->get('value');
        }

        $form = $this->createForm(ProspectType::class, $prospect);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($prospect);
            $entityManager->flush();
            try {
                $mailerService->postMail($prospect);
                $this->addFlash('success', 'Votre demande ' . $prospect->getSubject() . ' a bien été envoyé, vous allez recevoir un email de confirmation sur ' . $prospect->getEmail());
            } catch (\Exception $e) {
                throw new \Exception('warning', 'Une erreur est survenue lors de l\'envoi de l\'email,merci de refaire votre demande');
            }
            return $this->redirectToRoute('home');
        }
        return $this->render('prospect/new.html.twig', [
            'prospect' => $prospect,
            'form' => $form->createView(),
        ]);
    }


    /**
     * @IsGranted("ROLE_USER")
     * @Route("/{slug}", name="prospect_show", methods={"GET"})
     */
    public function show(prospect $prospect): Response
    {
        return $this->render('prospect/show.html.twig', [
            'prospect' => $prospect,
        ]);
    }

    /**
     * @Route("/{slug}/edit", name="prospect_edit", methods={"GET","POST"})
     * @IsGranted("ROLE_USER")
     */
    public function edit(Request $request, prospect $prospect): Response
    {
        $form = $this->createForm(ProspectType::class, $prospect);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('prospect_index');
        }

        return $this->render('prospect/edit.html.twig', [
            'prospect' => $prospect,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{slug}", name="prospect_delete", methods={"DELETE"})
     * @IsGranted("ROLE_USER")
     */
    public function delete(Request $request, prospect $prospect): Response
    {
        if ($this->isCsrfTokenValid('delete' . $prospect->getSlug(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($prospect);
            $entityManager->flush();
        }

        return $this->redirectToRoute('prospect_index');
    }
    /**
     * Verification of the current session
     *
     * @return Response
     */
    public function verifSession(): Response
    {
        // Verification of the current session otherwise error
        if (!$this->session->has('value')) {
            $this->addFlash('danger', "An error has occurred, thank you to renew your request !");
            return $this->redirectToRoute('home_index');
        }
        $valueSession = $this->session->get('value');
        return $valueSession;
    }
}