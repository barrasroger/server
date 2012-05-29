<?php

class UserController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }

    public function indexAction()
    {
        $request = $this->getRequest();
		$page = $this->_getParam('page', 1);
		$pageSize = $this->_getParam('pageSize', 10);
		$client = Infra_ClientHelper::getClient();
		
		// reset form url
		$action = $this->view->url(array('controller' => $request->getParam('controller'), 'action' => $request->getParam('action')), null, true);
		$form = new Form_UserFilter();
		$form->setAction($action);
		
		$userRoles = $client->userRole->listAction();
		
		$config = Zend_Registry::get('config');
		
		// init filter
		$userFilter = $this->getUserFilterFromRequest($request);
		$userFilter->partnerIdEqual = $config->settings->partnerId;
		$userFilter->orderBy = Kaltura_Client_Enum_UserOrderBy::CREATED_AT_DESC;
		
		$paginatorAdapter = new Infra_FilterPaginator($client->user, "listAction", null, $userFilter);
		$paginator = new Infra_Paginator($paginatorAdapter);
		$paginator->setCurrentPageNumber($page);
		$paginator->setItemCountPerPage($pageSize);
		
		$this->view->myEmail = Infra_AuthHelper::getAuthInstance()->getIdentity()->getUser()->email;
		$this->view->paginator = $paginator;

							
		// populate the form
		$form->populate($request->getParams());
		
		// set view
		$this->view->form = $form;
    }

    public function loginAction ()
    {
        $loginForm = new Form_Login();
		$resetForm = new Form_ResetPassword();
		$request = $this->getRequest();
		
		if ($request->isPost())
		{
			$adapter = new Infra_AuthAdapter($request->getPost('email'), $request->getPost('password'));
			$auth = Infra_AuthHelper::getAuthInstance();
			$result = $auth->authenticate($adapter);

			if ($result->isValid())
			{
				if ($request->getPost('remember_me'))
					Zend_Session::rememberMe(60*60*24*7); // 1 week
					
				$nextUri = $this->_getParam('next_uri');
				KalturaLog::debug("next uri $nextUri");
				if ($nextUri && strlen($nextUri) > 1)
					$this->_helper->redirector->gotoUrl($nextUri);
				else
					$this->_helper->redirector('list-by-user', 'partner');
			}
			else
			{
				$loginForm->setDescription('login error');
			}
		}
		
		$loginForm->setDefault('next_uri', $this->_getParam('next_uri')); // set in Infra_AuthPlugin
		
		$this->view->loginForm = $loginForm;
		$this->view->resetForm = $resetForm;
		$this->render('login');
    }
    
    public function logoutAction()
	{
		Zend_Session::forgetMe();
		$client = Infra_ClientHelper::getClient();
		$client->session->end();
		Infra_AuthHelper::getAuthInstance()->clearIdentity();
		$this->_helper->redirector('index', 'index');
	}
	
    public function resetPasswordAction()
	{
		$request = $this->getRequest();
		$form = new Form_ResetPassword();
		
		if ($request->isPost())
		{
			$client = Infra_ClientHelper::getClient();
			$userEmail = $request->getPost('email');

			$client->user->resetPassword($userEmail); // ask to reset password
			//TODO: check for exceptions!
			
			$tranlsate = $this->getFrontController()->getParam('bootstrap')->getResource('translate'); // TODO: add translate action helper
			$form->setDescription(sprintf($tranlsate->_('password instructions emailed to %s'), $request->getPost('email')));
			$form->hideForm();
			
		}
		
		$this->view->form = $form;
	}
	
	public function resetPasswordLinkAction()
	{
		$request = $this->getRequest();
		$form = new Form_ResetPasswordLink();
		$token = $request->get('token');
		
		if ($request->isPost())
		{
			$this->proccessResetPasswordLinkForm($form, $token);
		}
		
		$this->view->form = $form;		 
	}
	
	public function resetPasswordOkAction()
	{
		$this->view->invalidToken = $this->getRequest()->get('invalid-token');
	}

}

