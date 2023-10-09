<?php

namespace Allegro\Controller;

use Allegro\Entity\AllegroAccount;
use Allegro\Form\AllegroAccountType;
use Allegro\Singleton\AllegroSingleton;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;

class AdminAllegroAccountsController extends FrameworkBundleAdminController
{

    function actionSaveAllegroToken(){
        AllegroSingleton::getInstance();
        try{
            AllegroSingleton::authorize($_GET['code']);
            $this->addFlash(
                'success',
                'Konto allegro zostało poprawnie zautoryzowane'
            );
        }
        catch (\Throwable $e){
            $this->addFlash(
                'error',
                $e->getMessage()
            );
        }

        return $this->redirectToRoute('allegro_account_list');
    }

    function createAction(Request $request)
    {
        $form = $this->createForm(AllegroAccountType::class);
        $form->handleRequest($request);

        if(
            $form->isSubmitted()
            && $form->isValid()
        ){
            $em = $this->getDoctrine()->getManager();

            $allegroAccount = new AllegroAccount();
            $allegroAccount->setName($form->get('name')->getData());
            $allegroAccount->setAuthorized(0);

            $em->persist($allegroAccount);
            $em->flush();
            $this->addFlash(
                'success',
                'Zmiany zostały zapisane'
            );
            return $this->redirectToRoute('allegro_account_list');
        }

        return $this->render('@Modules/allegro/templates/admin/create.html.twig',[
            'form' => $form->createView()
        ]);
    }

    function listAction()
    {
        $em = $this->getDoctrine()->getManager();
        $data = $em->getRepository(AllegroAccount::class)->findAll();

        $allegroAccountUrls = [];
        foreach ($data as $allegroAccount) {
            $allegroAccountId = $allegroAccount->getId();
            $allegroAccountUrls[$allegroAccountId] = (string) AllegroSingleton::getInstance()->oauth()->getAuthorizationUri(true);
        }

        return $this->render('@Modules/allegro/templates/admin/list.html.twig',[
            'data' => $data,
            'allegroAccountUrls' => $allegroAccountUrls
        ]);
    }

    function updateAction(int $id, Request $request)
    {
        if($id == null){
            return null;
        }

        $em = $this->getDoctrine()->getManager();
        $allegroAccountForUpdate = $em->getRepository(AllegroAccount::class)->find($id);

        $form = $this->createForm(AllegroAccountType::class, $allegroAccountForUpdate);
        $form->handleRequest($request);

        if(
            $form->isSubmitted()
            && $form->isValid()
        ){
            $allegroAccountForUpdate->setName($form->get('name')->getData());
            $allegroAccountForUpdate->setAuthorized(0);
            $em->flush();

            $this->addFlash(
                'success',
                'Zmiany zostały zapisane'
            );
            return $this->redirectToRoute('allegro_account_list');
        }

        return $this->render('@Modules/allegro/templates/admin/create.html.twig',[
            'form' => $form->createView()
        ]);
    }

    function deleteAction(int $id)
    {
        $em = $this->getDoctrine()->getManager();
        $allegroAccount = $em->getRepository(AllegroAccount::class)->findOneBy(['id' => $id]);
        if($allegroAccount){
            $em->remove($allegroAccount);
            $em->flush();

            $this->addFlash(
                'success',
                'Zmiany zostały zapisane'
            );
        }

        return $this->redirectToRoute('allegro_account_list');
    }
}
