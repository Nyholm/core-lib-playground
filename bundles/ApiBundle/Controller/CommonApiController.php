<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ApiBundle\Controller;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\Pagination\Paginator;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Util\Codes;
use JMS\Serializer\Exclusion\ExclusionStrategyInterface;
use JMS\Serializer\SerializationContext;
use Mautic\ApiBundle\Serializer\Exclusion\ParentChildrenExclusionStrategy;
use Mautic\ApiBundle\Serializer\Exclusion\PublishDetailsExclusionStrategy;
use Mautic\CoreBundle\Controller\MauticController;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\UserBundle\Entity\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class CommonApiController.
 */
class CommonApiController extends FOSRestController implements MauticController
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var MauticFactory
     */
    protected $factory;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var CoreParametersHelper
     */
    protected $coreParametersHelper;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var \Mautic\CoreBundle\Security\Permissions\CorePermissions
     */
    protected $security;

    /**
     * Model object for processing the entity.
     *
     * @var \Mautic\CoreBundle\Model\AbstractCommonModel
     */
    protected $model;

    /**
     * Key to return for a single entity.
     *
     * @var string
     */
    protected $entityNameOne;

    /**
     * Key to return for entity lists.
     *
     * @var string
     */
    protected $entityNameMulti;

    /**
     * Class for the entity.
     *
     * @var string
     */
    protected $entityClass;

    /**
     * Permission base for the entity such as page:pages.
     *
     * @var string
     */
    protected $permissionBase;

    /**
     * Used to set default filters for entity lists such as restricting to owning user.
     *
     * @var array
     */
    protected $listFilters = [];

    /**
     * Pass to the model's getEntities() method.
     *
     * @var array
     */
    protected $extraGetEntitiesArguments = [];

    /**
     * @var array
     */
    protected $serializerGroups = [];

    /**
     * @var array
     */
    protected $routeParams = [];

    /**
     * The level parent/children should stop loading if applicable.
     *
     * @var int
     */
    protected $parentChildrenLevelDepth = 3;

    /**
     * Custom JMS strategies to add to the view's context.
     *
     * @var array
     */
    protected $exclusionStrategies = [];

    /**
     * @var bool
     */
    protected $inBatchMode = false;

    /**
     * Initialize some variables.
     *
     * @param FilterControllerEvent $event
     */
    public function initialize(FilterControllerEvent $event)
    {
        $this->security = $this->get('mautic.security');

        if ($this->model && !$this->permissionBase && method_exists($this->model, 'getPermissionBase')) {
            $this->permissionBase = $this->model->getPermissionBase();
        }
    }

    /**
     * @param Request $request
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param User $user
     */
    public function setUser(User $user)
    {
        $this->user = $user;
    }

    /**
     * @param CoreParametersHelper $coreParametersHelper
     */
    public function setCoreParametersHelper(CoreParametersHelper $coreParametersHelper)
    {
        $this->coreParametersHelper = $coreParametersHelper;
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param MauticFactory $factory
     */
    public function setFactory(MauticFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Obtains a list of entities as defined by the API URL.
     *
     * @return Response
     */
    public function getEntitiesAction()
    {
        $repo          = $this->model->getRepository();
        $tableAlias    = $repo->getTableAlias();
        $publishedOnly = $this->request->get('published', 0);
        $minimal       = $this->request->get('minimal', 0);

        if (!$this->security->isGranted($this->permissionBase.':view')) {
            return $this->accessDenied();
        }

        if ($this->security->checkPermissionExists($this->permissionBase.':viewother')
            && !$this->security->isGranted($this->permissionBase.':viewother')
        ) {
            $this->listFilters = [
                'column' => $tableAlias.'.createdBy',
                'expr'   => 'eq',
                'value'  => $this->user->getId(),
            ];
        }

        if ($publishedOnly) {
            $this->listFilters[] = [
                'column' => $tableAlias.'.isPublished',
                'expr'   => 'eq',
                'value'  => true,
            ];
        }

        if ($minimal) {
            if (isset($this->serializerGroups[0])) {
                $this->serializerGroups[0] = str_replace('Details', 'List', $this->serializerGroups[0]);
            }
        }

        $args = array_merge(
            [
                'start'  => $this->request->query->get('start', 0),
                'limit'  => $this->request->query->get('limit', $this->coreParametersHelper->getParameter('default_pagelimit')),
                'filter' => [
                    'string' => $this->request->query->get('search', ''),
                    'force'  => $this->listFilters,
                ],
                'orderBy'        => $this->request->query->get('orderBy', ''),
                'orderByDir'     => $this->request->query->get('orderByDir', 'ASC'),
                'withTotalCount' => true, //for repositories that break free of Paginator
            ],
            $this->extraGetEntitiesArguments
        );
        $results = $this->model->getEntities($args);

        list($entities, $totalCount) = $this->prepareEntitiesForView($results);

        $view = $this->view(
            [
                'total'                => $totalCount,
                $this->entityNameMulti => $entities,
            ],
            Codes::HTTP_OK
        );
        $this->setSerializationContext($view);

        return $this->handleView($view);
    }

    /**
     * Obtains a specific entity as defined by the API URL.
     *
     * @param int $id Entity ID
     *
     * @return Response
     */
    public function getEntityAction($id)
    {
        $entity = $this->model->getEntity($id);
        if (!$entity instanceof $this->entityClass) {
            return $this->notFound();
        }

        if (!$this->checkEntityAccess($entity, 'view')) {
            return $this->accessDenied();
        }

        $this->preSerializeEntity($entity);
        $view = $this->view([$this->entityNameOne => $entity], Codes::HTTP_OK);
        $this->setSerializationContext($view);

        return $this->handleView($view);
    }

    /**
     * Creates a new entity.
     *
     * @return Response
     */
    public function newEntityAction()
    {
        $entity = $this->model->getEntity();

        if (!$this->checkEntityAccess($entity, 'create')) {
            return $this->accessDenied();
        }

        $parameters = $this->request->request->all();

        return $this->processForm($entity, $parameters, 'POST');
    }

    /**
     * Create a batch of new entities.
     *
     * @return array|Response
     */
    public function newEntitiesAction()
    {
        $entity = $this->model->getEntity();

        if (!$this->checkEntityAccess($entity, 'create')) {
            return $this->accessDenied();
        }

        $parameters = $this->request->request->all();

        if (count($parameters) > 200) {
            return $this->returnError($this->get('translator')->trans('mautic.api.call.batch_exception'));
        }

        $this->inBatchMode = true;
        $entities          = [];
        $errors            = [];
        foreach ($parameters as $key => $params) {
            $entity = $this->model->getEntity();
            $this->processBatchForm($key, $entity, $params, 'POST', $errors, $entities);
        }

        $payload = [$this->entityNameMulti => $entities];
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        $view = $this->view($payload, Codes::HTTP_CREATED);
        $this->setSerializationContext($view);

        return $this->handleView($view);
    }

    /**
     * Edits an existing entity or creates one on PUT if it doesn't exist.
     *
     * @param int $id Entity ID
     *
     * @return Response
     */
    public function editEntityAction($id)
    {
        $entity     = $this->model->getEntity($id);
        $parameters = $this->request->request->all();
        $method     = $this->request->getMethod();

        if ($entity === null || !$entity->getId()) {
            if ($method === 'PATCH') {
                //PATCH requires that an entity exists
                return $this->notFound();
            }

            //PUT can create a new entity if it doesn't exist
            $entity = $this->model->getEntity();
            if (!$this->checkEntityAccess($entity, 'create')) {
                return $this->accessDenied();
            }
        }

        if (!$this->checkEntityAccess($entity, 'edit')) {
            return $this->accessDenied();
        }

        return $this->processForm($entity, $parameters, $method);
    }

    /**
     * Edit a batch of entities.
     *
     * @return array|Response
     */
    public function editEntitiesAction()
    {
        $parameters = $this->request->request->all();

        if (count($parameters) > 200) {
            return $this->returnError($this->get('translator')->trans('mautic.api.call.batch_exception'));
        }

        $errors   = [];
        $entities = $this->getBatchEntities($parameters, $errors);

        foreach ($parameters as $key => $params) {
            $method = $this->request->getMethod();
            $entity = (isset($entities[$key])) ? $entities[$key] : null;

            if ($entity === null || !$entity->getId()) {
                if ($method === 'PATCH') {
                    //PATCH requires that an entity exists
                    $this->setBatchError($key, 'mautic.core.error.notfound', Codes::HTTP_NOT_FOUND, $errors, $entities, $entity);

                    continue;
                }

                //PUT can create a new entity if it doesn't exist
                $entity = $this->model->getEntity();
                if (!$this->checkEntityAccess($entity, 'create')) {
                    $this->setBatchError($key, 'mautic.core.error.accessdenied', Codes::HTTP_FORBIDDEN, $errors, $entities, $entity);

                    continue;
                }
            }

            if (!$this->checkEntityAccess($entity, 'edit')) {
                $this->setBatchError($key, 'mautic.core.error.accessdenied', Codes::HTTP_FORBIDDEN, $errors, $entities, $entity);

                continue;
            }

            $this->processBatchForm($key, $entity, $params, $method, $errors, $entities);
        }

        $payload = [$this->entityNameMulti => $entities];
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        $view = $this->view($payload, Codes::HTTP_OK);
        $this->setSerializationContext($view);

        return $this->handleView($view);
    }

    /**
     * Deletes an entity.
     *
     * @param int $id Entity ID
     *
     * @return Response
     */
    public function deleteEntityAction($id)
    {
        $entity = $this->model->getEntity($id);
        if (null !== $entity) {
            if (!$this->checkEntityAccess($entity, 'delete')) {
                return $this->accessDenied();
            }

            $this->model->deleteEntity($entity);

            $this->preSerializeEntity($entity);
            $view = $this->view([$this->entityNameOne => $entity], Codes::HTTP_OK);
            $this->setSerializationContext($view);

            return $this->handleView($view);
        }

        return $this->notFound();
    }

    /**
     * Delete a batch of entities.
     *
     * @return array|Response
     */
    public function deleteEntitiesAction()
    {
        $parameters = $this->request->query->all();

        if (count($parameters) > 200) {
            return $this->returnError($this->get('translator')->trans('mautic.api.call.batch_exception'));
        }

        $errors            = [];
        $entities          = $this->getBatchEntities($parameters, $errors, true);
        $this->inBatchMode = true;

        // Generate the view before deleting so that the IDs are still populated before Doctrine removes them
        $payload = [$this->entityNameMulti => $entities];
        $view    = $this->view($payload, Codes::HTTP_OK);
        $this->setSerializationContext($view);
        $response = $this->handleView($view);

        foreach ($entities as $key => $entity) {
            if ($entity === null || !$entity->getId()) {
                $this->setBatchError($key, 'mautic.core.error.notfound', Codes::HTTP_NOT_FOUND, $errors, $entities, $entity);

                continue;
            }

            if (!$this->checkEntityAccess($entity, 'delete')) {
                $this->setBatchError($key, 'mautic.core.error.accessdenied', Codes::HTTP_FORBIDDEN, $errors, $entities, $entity);

                continue;
            }

            $this->model->deleteEntity($entity);
            $this->getDoctrine()->getManager()->detach($entity);
        }

        if (!empty($errors)) {
            $content           = json_decode($response->getContent(), true);
            $content['errors'] = $errors;
            $response->setContent(json_encode($content));
        }

        return $response;
    }

    /**
     * Processes API Form.
     *
     * @param        $entity
     * @param null   $parameters
     * @param string $method
     *
     * @return mixed
     */
    protected function processForm($entity, $parameters = null, $method = 'PUT')
    {
        if ($parameters === null) {
            //get from request
            $parameters = $this->request->request->all();
        }

        //unset the ID in the parameters if set as this will cause the form to fail
        if (isset($parameters['id'])) {
            unset($parameters['id']);
        }

        //is an entity being updated or created?
        if ($entity->getId()) {
            $statusCode = Codes::HTTP_OK;
            $action     = 'edit';
        } else {
            $statusCode = Codes::HTTP_CREATED;
            $action     = 'new';

            // All the properties have to be defined in order for validation to work
            // Bug reported https://github.com/symfony/symfony/issues/19788
            $defaultProperties = $this->getEntityDefaultProperties($entity);
            $parameters        = array_merge($defaultProperties, $parameters);
        }

        // Check if user has access to publish
        if (
            (
                array_key_exists('isPublished', $parameters) ||
                array_key_exists('publishUp', $parameters) ||
                array_key_exists('publishDown', $parameters)
            ) &&
            $this->security->checkPermissionExists($this->permissionBase.':publish')) {
            if ($this->security->checkPermissionExists($this->permissionBase.':publishown')) {
                if (!$this->checkEntityAccess($entity, 'publish')) {
                    if ('new' === $action) {
                        $parameters['isPublished'] = 0;
                    } else {
                        unset($parameters['isPublished'], $parameters['publishUp'], $parameters['publishDown']);
                    }
                }
            }
        }

        $form         = $this->createEntityForm($entity);
        $submitParams = $this->prepareParametersForBinding($parameters, $entity, $action);

        if ($submitParams instanceof Response) {
            return $submitParams;
        }

        // Special handling of some fields
        foreach ($form as $name => $child) {
            if (isset($submitParams[$name])) {
                switch ($child->getConfig()->getType()->getName()) {
                    case 'yesno_button_group':
                        // Symfony fails to recognize true values on PATCH and add support for all boolean types (on, off, true, false, 1, 0)
                        $setter = 'set'.ucfirst($name);
                        $data   = filter_var($submitParams[$name], FILTER_VALIDATE_BOOLEAN);

                        try {
                            $entity->$setter((int) $data);

                            // Manually handled so remove from form processing
                            unset($form[$name], $submitParams[$name]);
                        } catch (\InvalidArgumentException $exception) {
                            // Setter not found
                        }
                        break;
                    case 'choice':
                        if ($child->getConfig()->getOption('multiple')) {
                            // Ensure the value is an array
                            if (!is_array($submitParams[$name])) {
                                $submitParams[$name] = [$submitParams[$name]];
                            }
                        }
                        break;
                }
            }
        }

        $form->submit($submitParams, 'PATCH' !== $method);

        if ($form->isValid()) {
            $preSaveError = $this->preSaveEntity($entity, $form, $submitParams, $action);

            if ($preSaveError instanceof Response) {
                return $preSaveError;
            }

            $this->model->saveEntity($entity);
            $headers = [];
            //return the newly created entities location if applicable
            if (Codes::HTTP_CREATED === $statusCode) {
                $route = ($this->get('router')->getRouteCollection()->get('mautic_api_'.$this->entityNameMulti.'_getone') !== null)
                    ? 'mautic_api_'.$this->entityNameMulti.'_getone' : 'mautic_api_get'.$this->entityNameOne;
                $headers['Location'] = $this->generateUrl(
                    $route,
                    array_merge(['id' => $entity->getId()], $this->routeParams),
                    true
                );
            }

            $this->preSerializeEntity($entity, $action);

            if ($this->inBatchMode) {
                return $entity;
            } else {
                $view = $this->view([$this->entityNameOne => $entity], $statusCode, $headers);
            }

            $this->setSerializationContext($view);
        } else {
            $formErrors = $this->getFormErrorMessages($form);
            $msg        = $this->getFormErrorMessage($formErrors);

            if (!$msg) {
                $msg = 'Request data are not valid';
            }

            return $this->returnError($msg, Codes::HTTP_BAD_REQUEST, $formErrors);
        }

        return $this->handleView($view);
    }

    /**
     * @param $key
     * @param $entity
     * @param $params
     * @param $method
     * @param $errors
     * @param $entities
     */
    protected function processBatchForm($key, $entity, $params, $method, &$errors, &$entities)
    {
        $this->inBatchMode = true;
        $formResponse      = $this->processForm($entity, $params, $method);
        if ($formResponse instanceof Response) {
            if (!$formResponse instanceof RedirectResponse) {
                // Assume an error
                $this->setBatchError(
                    $key,
                    InputHelper::string($formResponse->getContent()),
                    $formResponse->getStatusCode(),
                    $errors,
                    $entities,
                    $entity
                );
            }
        } elseif ($formResponse === $entity) {
            // Success
            $entities[$key] = $formResponse;
        } elseif (is_array($formResponse) && isset($formResponse['code'], $formResponse['message'])) {
            // There was an error
            $errors[$key] = $formResponse;
        }

        $this->getDoctrine()->getManager()->detach($entity);

        $this->inBatchMode = false;
    }

    /**
     * Get the default properties of an entity and parents.
     *
     * @param $entity
     *
     * @return array
     */
    protected function getEntityDefaultProperties($entity)
    {
        $class         = get_class($entity);
        $chain         = array_reverse(class_parents($entity), true) + [$class => $class];
        $defaultValues = [];

        $classMetdata = new ClassMetadata($class);
        foreach ($chain as $class) {
            if (method_exists($class, 'loadMetadata')) {
                $class::loadMetadata($classMetdata);
            }
            $defaultValues += (new \ReflectionClass($class))->getDefaultProperties();
        }

        // These are the mapped columns
        $fields = $classMetdata->getFieldNames();

        // Merge values in with $fields
        $properties = [];
        foreach ($fields as $field) {
            $properties[$field] = $defaultValues[$field];
        }

        return $properties;
    }

    /**
     * @param array $formErrors
     *
     * @return string
     */
    public function getFormErrorMessage(array $formErrors)
    {
        $msg = '';

        if ($formErrors) {
            foreach ($formErrors as $key => $error) {
                if (!$error) {
                    continue;
                }

                if ($msg) {
                    $msg .= ', ';
                }

                if (is_string($key)) {
                    $msg .= $key.': ';
                }

                if (is_array($error)) {
                    $msg .= $this->getFormErrorMessage($error);
                } else {
                    $msg .= $error;
                }
            }
        }

        return $msg;
    }

    /**
     * @param Form $form
     *
     * @return array
     */
    public function getFormErrorMessages(\Symfony\Component\Form\Form $form)
    {
        $errors = [];

        foreach ($form->getErrors(true) as $error) {
            if (isset($errors[$error->getOrigin()->getName()])) {
                $errors[$error->getOrigin()->getName()] = [$error->getMessage()];
            } else {
                $errors[$error->getOrigin()->getName()][] = $error->getMessage();
            }
        }

        return $errors;
    }

    /**
     * Checks if user has permission to access retrieved entity.
     *
     * @param mixed  $entity
     * @param string $action view|create|edit|publish|delete
     *
     * @return bool
     */
    protected function checkEntityAccess($entity, $action = 'view')
    {
        if ($action != 'create' && method_exists($entity, 'getCreatedBy')) {
            $ownPerm   = "{$this->permissionBase}:{$action}own";
            $otherPerm = "{$this->permissionBase}:{$action}other";

            $owner = (method_exists($entity, 'getPermissionUser')) ? $entity->getPermissionUser() : $entity->getCreatedBy();

            return $this->security->hasEntityAccess($ownPerm, $otherPerm, $owner);
        }

        return $this->security->isGranted("{$this->permissionBase}:{$action}");
    }

    /**
     * Creates the form instance.
     *
     * @param $entity
     *
     * @return Form
     */
    protected function createEntityForm($entity)
    {
        return $this->model->createForm(
            $entity,
            $this->get('form.factory'),
            null,
            array_merge(
                [
                    'csrf_protection'    => false,
                    'allow_extra_fields' => true,
                ],
                $this->getEntityFormOptions()
            )
        );
    }

    /**
     * Append options to the form.
     *
     * @return array
     */
    protected function getEntityFormOptions()
    {
        return [];
    }

    /**
     * Set serialization groups and exclusion strategies.
     *
     * @param \FOS\RestBundle\View\View $view
     */
    protected function setSerializationContext(&$view)
    {
        $context = SerializationContext::create();
        if (!empty($this->serializerGroups)) {
            $context->setGroups($this->serializerGroups);
        }

        // Only include FormEntity properties for the top level entity and not the associated entities
        $context->addExclusionStrategy(
            new PublishDetailsExclusionStrategy()
        );

        // Only include first level of children/parents
        if ($this->parentChildrenLevelDepth) {
            $context->addExclusionStrategy(
                new ParentChildrenExclusionStrategy($this->parentChildrenLevelDepth)
            );
        }

        // Add custom exclusion strategies
        foreach ($this->exclusionStrategies as $strategy) {
            $context->addExclusionStrategy($strategy);
        }

        //include null values
        $context->setSerializeNull(true);

        $view->setSerializationContext($context);
    }

    /**
     * Convert posted parameters into what the form needs in order to successfully bind.
     *
     * @param $parameters
     * @param $entity
     * @param $action
     *
     * @return mixed
     */
    protected function prepareParametersForBinding($parameters, $entity, $action)
    {
        return $parameters;
    }

    /**
     * Give the controller an opportunity to process the entity before persisting.
     *
     * @param $entity
     * @param $form
     * @param $parameters
     * @param $action
     *
     * @return mixed
     */
    protected function preSaveEntity(&$entity, $form, $parameters, $action = 'edit')
    {
    }

    /**
     * Gives child controllers opportunity to analyze and do whatever to an entity before going through serializer.
     *
     * @param        $entity
     * @param string $action
     *
     * @return mixed
     */
    protected function preSerializeEntity(&$entity, $action = 'view')
    {
    }

    /**
     * Gives child controllers opportunity to analyze and do whatever to an entity before populating the form.
     *
     * @param        $entity
     * @param        $parameters
     * @param string $action
     *
     * @return mixed
     */
    protected function prePopulateForm(&$entity, $parameters, $action = 'edit')
    {
    }

    /**
     * Returns an error.
     *
     * @param string $msg
     * @param int    $code
     * @param array  $details
     *
     * @return Response|array
     */
    protected function returnError($msg, $code, $details = [])
    {
        $error = [
            'code'    => $code,
            'message' => $this->get('translator')->trans($msg, [], 'flashes'),
            'details' => $details,
            'type'    => null,
        ];

        if ($this->inBatchMode) {
            return $error;
        }

        $view = $this->view(
            [
                'errors' => [
                    $error,
                ],
                // @deprecated 2.6.0 to be removed in 3.0
                'error' => [
                    'message' => $this->get('translator')->trans($msg, [], 'flashes')
                        .' (`error` is deprecated as of 2.6.0 and will be removed in 3.0. Use the `errors` array instead.)',
                    'code'    => $code,
                    'details' => $details,
                ],
            ]
        );

        return $this->handleView($view);
    }

    /**
     * Returns a 403 Access Denied.
     *
     * @param string $msg
     *
     * @return Response
     */
    protected function accessDenied($msg = 'mautic.core.error.accessdenied')
    {
        return $this->returnError($msg, Codes::HTTP_FORBIDDEN);
    }

    /**
     * Returns a 404 Not Found.
     *
     * @param string $msg
     *
     * @return Response
     */
    protected function notFound($msg = 'mautic.core.error.notfound')
    {
        return $this->returnError($msg, Codes::HTTP_NOT_FOUND);
    }

    /**
     * Returns a 400 Bad Request.
     *
     * @param string $msg
     *
     * @return Response
     */
    protected function badRequest($msg = 'mautic.core.error.badrequest')
    {
        return $this->returnError($msg, Codes::HTTP_BAD_REQUEST);
    }

    /**
     * {@inheritdoc}
     *
     * @param null  $data
     * @param null  $statusCode
     * @param array $headers
     */
    protected function view($data = null, $statusCode = null, array $headers = [])
    {
        if ($data instanceof Paginator) {
            // Get iterator out of Paginator class so that the entities are properly serialized by the serializer
            $data = $data->getIterator()->getArrayCopy();
        }

        $headers['Mautic-Version'] = $this->get('kernel')->getVersion();

        return parent::view($data, $statusCode, $headers);
    }

    /**
     * Prepares entities returned from repository getEntities().
     *
     * @param $results
     *
     * @return array($entities, $totalCount)
     */
    protected function prepareEntitiesForView($results)
    {
        return $this->prepareEntityResultsToArray(
            $results,
            function ($entity) {
                $this->preSerializeEntity($entity);
            }
        );
    }

    /**
     * @param      $results
     * @param null $callback
     *
     * @return array($entities, $totalCount)
     */
    protected function prepareEntityResultsToArray($results, $callback = null)
    {
        if ($results instanceof Paginator) {
            $totalCount = count($results);
        } elseif (isset($results['count'])) {
            $totalCount = $results['count'];
            $results    = $results['results'];
        } else {
            $totalCount = count($results);
        }

        //we have to convert them from paginated proxy functions to entities in order for them to be
        //returned by the serializer/rest bundle
        $entities = [];
        foreach ($results as $key => $r) {
            if (is_array($r) && isset($r[0])) {
                //entity has some extra something something tacked onto the entities
                if (is_object($r[0])) {
                    foreach ($r as $k => $v) {
                        if ($k === 0) {
                            continue;
                        }

                        $r[0]->$k = $v;
                    }
                    $entities[$key] = $r[0];
                } elseif (is_array($r[0])) {
                    foreach ($r[0] as $k => $v) {
                        $r[$k] = $v;
                    }
                    unset($r[0]);
                    $entities[$key] = $r;
                }
            } else {
                $entities[$key] = $r;
            }

            if (is_callable($callback)) {
                $callback($entities[$key]);
            }
        }

        return [$entities, $totalCount];
    }

    /**
     * Get a model instance from the service container.
     *
     * @param $modelNameKey
     *
     * @return AbstractCommonModel
     */
    protected function getModel($modelNameKey)
    {
        // Shortcut for models with the same name as the bundle
        if (strpos($modelNameKey, '.') === false) {
            $modelNameKey = "$modelNameKey.$modelNameKey";
        }

        $parts = explode('.', $modelNameKey);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException($modelNameKey.' is not a valid model key.');
        }

        list($bundle, $name) = $parts;

        $containerKey = str_replace(['%bundle%', '%name%'], [$bundle, $name], 'mautic.%bundle%.model.%name%');

        if ($this->container->has($containerKey)) {
            return $this->container->get($containerKey);
        }

        throw new \InvalidArgumentException($containerKey.' is not a registered container key.');
    }

    /**
     * @param ExclusionStrategyInterface $strategy
     */
    protected function addExclusionStrategy(ExclusionStrategyInterface $strategy)
    {
        $this->exclusionStrategies[] = $strategy;
    }

    /**
     * @param       $key
     * @param       $msg
     * @param       $code
     * @param       $errors
     * @param array $entities
     * @param null  $entity
     */
    protected function setBatchError($key, $msg, $code, &$errors, &$entities = [], $entity = null)
    {
        unset($entities[$key]);
        if ($entity) {
            $this->getDoctrine()->getManager()->detach($entity);
        }

        $errors[$key] = [
            'message' => $this->get('translator')->hasId($msg, 'flashes') ? $this->get('translator')->trans($msg, [], 'flashes') : $msg,
            'code'    => $code,
            'type'    => 'api',
        ];
    }

    /**
     * @param      $parameters
     * @param      $errors
     * @param bool $prepareForSerialization
     *
     * @return array
     */
    protected function getBatchEntities($parameters, &$errors, $prepareForSerialization = false)
    {
        $ids = [];
        if (isset($parameters['ids'])) {
            foreach ($parameters['ids'] as $key => $id) {
                $ids[(int) $id] = $key;
            }
        } else {
            foreach ($parameters as $key => $params) {
                if (is_array($params) && !isset($params['id'])) {
                    $this->setBatchError($key, 'mautic.api.call.id_missing', Codes::HTTP_BAD_REQUEST, $errors);
                    continue;
                }

                $id       = (is_array($params)) ? (int) $params['id'] : (int) $params;
                $ids[$id] = $key;
            }
        }
        $return = [];
        if (!empty($ids)) {
            $entities = $this->model->getEntities(
                [
                    'filter' => [
                        'force' => [
                            [
                                'column' => $this->model->getRepository()->getTableAlias().'.id',
                                'expr'   => 'in',
                                'value'  => array_keys($ids),
                            ],
                        ],
                    ],
                    'ignore_paginator' => true,
                ]
            );

            list($entities, $total) = $prepareForSerialization
                ?
                $this->prepareEntitiesForView($entities)
                :
                $this->prepareEntityResultsToArray($entities);

            // Ensure same keys as params
            foreach ($entities as $entity) {
                $return[$ids[$entity->getId()]] = $entity;
            }
        }

        return $return;
    }
}
