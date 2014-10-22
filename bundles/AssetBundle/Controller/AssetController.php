<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\AssetBundle\Controller;

use Mautic\CoreBundle\Controller\FormController;
use Mautic\CoreBundle\Helper\InputHelper;
use Symfony\Component\HttpFoundation\JsonResponse;

class AssetController extends FormController
{

    /**
     * @param int    $asset
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($asset = 1)
    {

        $model = $this->factory->getModel('asset.asset');

        //set some permissions
        $permissions = $this->factory->getSecurity()->isGranted(array(
            'asset:assets:viewown',
            'asset:assets:viewother',
            'asset:assets:create',
            'asset:assets:editown',
            'asset:assets:editother',
            'asset:assets:deleteown',
            'asset:assets:deleteother',
            'asset:assets:publishown',
            'asset:assets:publishother'
        ), "RETURN_ARRAY");

        if (!$permissions['asset:assets:viewown'] && !$permissions['asset:assets:viewother']) {
            return $this->accessDenied();
        }

        //set limits
        $limit = $this->factory->getSession()->get('mautic.asset.limit', $this->factory->getParameter('default_assetlimit'));
        $start = ($asset === 1) ? 0 : (($asset-1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $this->request->get('search', $this->factory->getSession()->get('mautic.asset.filter', ''));
        $this->factory->getSession()->set('mautic.asset.filter', $search);

        $filter = array('string' => $search, 'force' => array());

        if (!$permissions['asset:assets:viewother']) {
            $filter['force'][] =
                array('column' => 'p.createdBy', 'expr' => 'eq', 'value' => $this->factory->getUser());
        }

        $orderBy     = $this->factory->getSession()->get('mautic.asset.orderby', 'a.title');
        $orderByDir  = $this->factory->getSession()->get('mautic.asset.orderbydir', 'DESC');

        $assets = $model->getEntities(
            array(
                'start'      => $start,
                'limit'      => $limit,
                'filter'     => $filter,
                'orderBy'    => $orderBy,
                'orderByDir' => $orderByDir
            ));

        $count = count($assets);
        if ($count && $count < ($start + 1)) {
            //the number of entities are now less then the current asset so redirect to the last asset
            if ($count === 1) {
                $lastAsset = 1;
            } else {
                $lastAsset = (floor($limit / $count)) ? : 1;
            }
            $this->factory->getSession()->set('mautic.asset.asset', $lastAsset);
            $returnUrl   = $this->generateUrl('mautic_asset_index', array('asset' => $lastAsset));

            return $this->postActionRedirect(array(
                'returnUrl'       => $returnUrl,
                'viewParameters'  => array('asset' => $lastAsset),
                'contentTemplate' => 'MauticAssetBundle:Asset:index',
                'passthroughVars' => array(
                    'activeLink'    => '#mautic_asset_index',
                    'mauticContent' => 'asset'
                )
            ));
        }

        //set what asset currently on so that we can return here after form submission/cancellation
        $this->factory->getSession()->set('mautic.asset.asset', $asset);

        $tmpl = $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index';

        //retrieve a list of categories
        $categories = $this->factory->getModel('asset.asset')->getLookupResults('category', '', 0);

        return $this->delegateView(array(
            'viewParameters'  =>  array(
                'searchValue' => $search,
                'items'       => $assets,
                'categories'  => $categories,
                'asset'       => $asset,
                'limit'       => $limit,
                'permissions' => $permissions,
                'model'       => $model,
                'tmpl'        => $tmpl,
                'security'    => $this->factory->getSecurity()
            ),
            'contentTemplate' => 'MauticAssetBundle:Asset:list.html.php',
            'passthroughVars' => array(
                'activeLink'     => '#mautic_asset_index',
                'mauticContent'  => 'asset',
                'route'          => $this->generateUrl('mautic_asset_index', array('asset' => $asset)),
                'replaceContent' => ($tmpl == 'list') ? 'true' : 'false'
            )
        ));
    }

    /**
     * Loads a specific form into the detailed panel
     *
     * @param $objectId
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($objectId)
    {
        $model      = $this->factory->getModel('asset.asset');
        $security   = $this->factory->getSecurity();
        $activeAsset = $model->getEntity($objectId);
        //set the asset we came from
        $page = $this->factory->getSession()->get('mautic.asset.page', 1);

        if ($activeAsset === null) {
            //set the return URL
            $returnUrl = $this->generateUrl('mautic_asset_index', array('page' => $page));

            return $this->postActionRedirect(array(
                'returnUrl'       => $returnUrl,
                'viewParameters'  => array('page' => $page),
                'contentTemplate' => 'MauticAssetBundle:Asset:index',
                'passthroughVars' => array(
                    'activeLink'    => '#mautic_asset_index',
                    'mauticContent' => 'asset'
                ),
                'flashes'         => array(
                    array(
                        'type'    => 'error',
                        'msg'     => 'mautic.asset.asset.error.notfound',
                        'msgVars' => array('%id%' => $objectId)
                    )
                )
            ));
        } elseif (!$this->factory->getSecurity()->hasEntityAccess(
            'asset:assets:viewown', 'asset:assets:viewother', $activeAsset->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        }

        $properties = array();

        // Download stats per time period
        $timeStats = $this->factory->getEntityManager()->getRepository('MauticAssetBundle:Download')->getDownloads($activeAsset->getId());

        return $this->delegateView(array(
            'returnUrl'       => $this->generateUrl('mautic_asset_action', array(
                    'objectAction' => 'view',
                    'objectId'     => $activeAsset->getId())
            ),
            'viewParameters'  => array(
                'activeAsset'   => $activeAsset,
                'permissions'   => $security->isGranted(array(
                    'asset:assets:viewown',
                    'asset:assets:viewother',
                    'asset:assets:create',
                    'asset:assets:editown',
                    'asset:assets:editother',
                    'asset:assets:deleteown',
                    'asset:assets:deleteother',
                    'asset:assets:publishown',
                    'asset:assets:publishother'
                ), "RETURN_ARRAY"),
                'stats'         => array(
                    'downloads'  => array(
                        'total'  => $activeAsset->getDownloadCount(),
                        'unique' => $activeAsset->getUniqueDownloadCount(),
                        'timeStats' => $timeStats
                    )
                ),
                'security'    => $security,
                'assetUrl'    => $model->generateUrl($activeAsset, true)
            ),
            'contentTemplate' => 'MauticAssetBundle:Asset:details.html.php',
            'passthroughVars' => array(
                'activeLink'    => '#mautic_asset_index',
                'mauticContent' => 'asset'
            )
        ));
    }

    /**
     * Generates new form and processes post data
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction ()
    {
        $model   = $this->factory->getModel('asset.asset');
        $entity  = $model->getEntity();
        $method  = $this->request->getMethod();
        $session = $this->factory->getSession();
        if (!$this->factory->getSecurity()->isGranted('asset:assets:create')) {
            return $this->accessDenied();
        }

        //set the page we came from
        $page   = $session->get('mautic.asset.page', 1);
        $action = $this->generateUrl('mautic_asset_action', array('objectAction' => 'new'));

        //create the form
        $form   = $model->createForm($entity, $this->get('form.factory'), $action);

        ///Check for a submitted form and process it
        if ($method == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {

                    $entity->setUploadDir($this->factory->getParameter('upload_dir'));
                    $entity->preUpload();
                    $entity->upload();

                    //form is valid so process the data
                    $model->saveEntity($entity);

                    $this->request->getSession()->getFlashBag()->add(
                        'notice',
                        $this->get('translator')->trans('mautic.asset.asset.notice.created', array(
                            '%name%' => $entity->getTitle(),
                            '%url%'          => $this->generateUrl('mautic_asset_action', array(
                                'objectAction' => 'edit',
                                'objectId'     => $entity->getId()
                            ))
                        ), 'flashes')
                    );

                    if ($form->get('buttons')->get('save')->isClicked()) {
                        $viewParameters = array(
                            'objectAction' => 'view',
                            'objectId'     => $entity->getId()
                        );
                        $returnUrl      = $this->generateUrl('mautic_asset_action', $viewParameters);
                        $template       = 'MauticAssetBundle:Asset:view';
                    } else {
                        //return edit view so that all the session stuff is loaded
                        return $this->editAction($entity->getId(), true);
                    }
                }
            } else {
                $viewParameters  = array('page' => $page);
                $returnUrl = $this->generateUrl('mautic_asset_index', $viewParameters);
                $template  = 'MauticAssetBundle:Asset:index';
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                return $this->postActionRedirect(array(
                    'returnUrl'       => $returnUrl,
                    'viewParameters'  => $viewParameters,
                    'contentTemplate' => $template,
                    'passthroughVars' => array(
                        'activeLink'    => 'mautic_asset_index',
                        'mauticContent' => 'asset'
                    )
                ));
            }
        }

        return $this->delegateView(array(
            'viewParameters'  =>  array(
                'form'        => $form->createView(),
                'activeAsset'  => $entity
            ),
            'contentTemplate' => 'MauticAssetBundle:Asset:form.html.php',
            'passthroughVars' => array(
                'activeLink'    => '#mautic_asset_index',
                'mauticContent' => 'asset',
                'route'         => $this->generateUrl('mautic_asset_action', array(
                    'objectAction' => 'new'
                ))
            )
        ));
    }

    /**
     * Generates edit form and processes post data
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editAction ($objectId, $ignorePost = false)
    {
        $model      = $this->factory->getModel('asset.asset');
        $entity     = $model->getEntity($objectId);
        $session    = $this->factory->getSession();
        $page       = $this->factory->getSession()->get('mautic.asset.page', 1);

        //set the return URL
        $returnUrl  = $this->generateUrl('mautic_asset_index', array('page' => $page));

        $postActionVars = array(
            'returnUrl'       => $returnUrl,
            'viewParameters'  => array('page' => $page),
            'contentTemplate' => 'MauticAssetBundle:Page:index',
            'passthroughVars' => array(
                'activeLink'    => 'mautic_asset_index',
                'mauticContent' => 'asset'
            )
        );

        //not found
        if ($entity === null) {
            return $this->postActionRedirect(
                array_merge($postActionVars, array(
                    'flashes' => array(
                        array(
                            'type' => 'error',
                            'msg'  => 'mautic.asset.asset.error.notfound',
                            'msgVars' => array('%id%' => $objectId)
                        )
                    )
                ))
            );
        }  elseif (!$this->factory->getSecurity()->hasEntityAccess(
            'asset:assets:viewown', 'asset:assets:viewother', $entity->getCreatedBy()
        )) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            //deny access if the entity is locked
            return $this->isLocked($postActionVars, $entity, 'asset.asset');
        }

        //Create the form
        $action = $this->generateUrl('mautic_asset_action', array('objectAction' => 'edit', 'objectId' => $objectId));
        $form   = $model->createForm($entity, $this->get('form.factory'), $action);

        ///Check for a submitted form and process it
        if (!$ignorePost && $this->request->getMethod() == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {

                    $entity->setUploadDir($this->factory->getParameter('upload_dir'));
                    $entity->preUpload();
                    $entity->upload();

                    //form is valid so process the data
                    $model->saveEntity($entity, $form->get('buttons')->get('save')->isClicked());

                    $this->request->getSession()->getFlashBag()->add(
                        'notice',
                        $this->get('translator')->trans('mautic.asset.asset.notice.updated', array(
                            '%name%' => $entity->getTitle(),
                            '%url%'  => $this->generateUrl('mautic_asset_action', array(
                                'objectAction' => 'edit',
                                'objectId'     => $entity->getId()
                            ))
                        ), 'flashes')
                    );

                    $returnUrl = $this->generateUrl('mautic_asset_action', array(
                        'objectAction' => 'view',
                        'objectId'     => $entity->getId()
                    ));
                    $viewParams = array('objectId' => $entity->getId());
                    $template = 'MauticAssetBundle:Asset:view';
                }
            } else {
                //clear any modified content
                $session->remove('mautic.asestbuilder.'.$objectId.'.content', array());
                //unlock the entity
                $model->unlockEntity($entity);

                $returnUrl = $this->generateUrl('mautic_asset_index', array('page' => $page));
                $viewParams = array('page' => $page);
                $template  = 'MauticAssetBundle:Asset:index';
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                return $this->postActionRedirect(
                    array_merge($postActionVars, array(
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => $viewParams,
                        'contentTemplate' => $template
                    ))
                );
            }
        } else {
            //lock the entity
            $model->lockEntity($entity);

            //set the lookup values
            $category = $entity->getCategory();
            if ($category && isset($form['category_lookup']))
                $form->get('category_lookup')->setData($category->getTitle());
        }

        $formView = $this->setFormTheme($form, 'MauticAssetBundle:Asset:form.html.php', 'MauticAssetBundle:FormVariant');

        // $getBuilderComponents    = $model->getBuilderComponents();
        return $this->delegateView(array(
            'viewParameters'  =>  array(
                'form'        => $formView,
                // 'tokens'      => $builderComponents['assetTokens'],
                'activeAsset'  => $entity
            ),
            'contentTemplate' => 'MauticAssetBundle:Asset:form.html.php',
            'passthroughVars' => array(
                'activeLink'    => '#mautic_asset_index',
                'mauticContent' => 'asset',
                'route'         => $this->generateUrl('mautic_asset_action', array(
                    'objectAction' => 'edit',
                    'objectId'     => $entity->getId()
                ))
            )
        ));
    }

    /**
     * Clone an entity
     *
     * @param $objectId
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function cloneAction ($objectId)
    {
        $model   = $this->factory->getModel('asset.asset');
        $entity  = $model->getEntity($objectId);

        if ($entity != null) {
            if (!$this->factory->getSecurity()->isGranted('asset:assets:create') ||
                !$this->factory->getSecurity()->hasEntityAccess(
                    'asset:assets:viewown', 'asset:assets:viewother', $entity->getCreatedBy()
                )
            ) {
                return $this->accessDenied();
            }

            $clone = clone $entity;
            $clone->setDownloadCounts(0);
            $clone->setUniqueDownloadCounts(0);
            $clone->setRevision(0);
            $clone->setIsPublished(false);
            $model->saveEntity($clone);
            $objectId = $clone->getId();
        }

        return $this->editAction($objectId);
    }

    /**
     * Deletes the entity
     *
     * @param         $objectId
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction($objectId) {
        $page        = $this->factory->getSession()->get('mautic.asset.page', 1);
        $returnUrl   = $this->generateUrl('mautic_asset_index', array('page' => $page));
        $flashes     = array();

        $postActionVars = array(
            'returnUrl'       => $returnUrl,
            'viewParameters'  => array('page' => $page),
            'contentTemplate' => 'MauticAssetBundle:Asset:index',
            'passthroughVars' => array(
                'activeLink'    => 'mautic_asset_index',
                'mauticContent' => 'asset'
            )
        );

        if ($this->request->getMethod() == 'POST') {
            $model  = $this->factory->getModel('asset.asset');
            $entity = $model->getEntity($objectId);

            if ($entity === null) {
                $flashes[] = array(
                    'type'    => 'error',
                    'msg'     => 'mautic.asset.asset.error.notfound',
                    'msgVars' => array('%id%' => $objectId)
                );
            } elseif (!$this->factory->getSecurity()->hasEntityAccess(
                'page:pages:deleteown',
                'page:pages:deleteother',
                $entity->getCreatedBy()
            )) {
                return $this->accessDenied();
            } elseif ($model->isLocked($entity)) {
                return $this->isLocked($postActionVars, $entity, 'asset.asset');
            }

            $entity->removeUpload();
            $model->deleteEntity($entity);

            $flashes[] = array(
                'type' => 'notice',
                'msg'  => 'mautic.asset.asset.notice.deleted',
                'msgVars' => array(
                    '%name%' => $entity->getTitle(),
                    '%id%'   => $objectId
                )
            );
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge($postActionVars, array(
                'flashes' => $flashes
            ))
        );
    }
}