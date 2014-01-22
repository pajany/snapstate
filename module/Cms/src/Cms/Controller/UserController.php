<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Cms\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

//	Session
use Zend\Session\Container;

//	Auth
use Zend\Authentication,
	Zend\Authentication\Result,
	Zend\Authentication\AuthenticationService;

//	Forms
use Cms\Form\CreateGroupForm;
use Cms\Form\CreateUserForm;
use Cms\Form\CreateRoleForm;
use Cms\Form\ChangePasswordForm;
use Cms\Form\FilterForm;

//	Models
use Cms\Model\Group;
use Cms\Model\Users;
use Cms\Model\MyAuthenticationAdapter;
//	SMTP Mailer
use PHPMailer;

class UserController extends AbstractActionController
{
	/************************************
	 *	Method: connect     	         
	 *  Purpose: To connect with MongoDB 
	 ***********************************/
	
	public function connect()
	{
		//$conn = new \Mongo(HOST, array("username" => USERNAME, "password" => PASSWORD, "db" => DATABASE));
		$conn = new \Mongo(HOST);
		return $conn;
	}
	/************************************
	 *	Method: selectUser     	         
	 *  Purpose: To select user with auth
	 ***********************************/
	
	public function selectUser($conn, $username, $password)
	{
		$collection	= $conn->snapstate->users;
		$document	= array('user_email' => new \MongoRegex('/^' . preg_quote(trim($username)) . '$/i'), 'user_password' => $password, 'group_id' => '1');
		$cursor		= $collection->find($document);
		return $cursor;
	}
	/************************************************
	 *	Method: checkGroupname     	                 
	 *  Purpose: To validate the group name existence
	 ***********************************************/
	
	public function checkGroupname($formData, $opt) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->groups;
		$userSession = new Container('user');
		if($opt == 1) {
			$results	= $collection->find(array('group_name' => trim($formData['group_name'])));
		} else if($opt == 2) {
			$mongoID	= new \MongoID(trim($formData['_id']));
			$document	= array('_id'	=> array('$ne' => $mongoID), 'group_name' => trim($formData['group_name']));
			$results	= $collection->find($document);
		}
		while($results->hasNext())
		{
			$resultArray	= $results->getNext();
		}
		if(isset($resultArray) && is_array($resultArray)) {
			return 1;
		} else {
			return 0;
		}
	}
	/***********************************
	 *	Method: saveGroup     	    	
	 *  Purpose: To insert a group		
	 **********************************/
	
	public function saveGroup($formData) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->groups;
		$results	= $collection->insert(array('group_name' => trim($formData['group_name']), 'group_role' => $formData['group_role'], 'group_status' => $formData['group_status']));
		if($results) {
			return 1;
		} else {
			return 0;
		}
	}
	/***********************************
	 *	Method: updateGroup     	    
	 *  Purpose: To update group by _id 
	 **********************************/
	
	public function updateGroup($formData) {
		$document	= array('$set' => array('group_status' => $formData['group_status'], 'group_role' => $formData['group_role'], 'group_name' => trim($formData['group_name'])));
		$mongoID	= new \MongoID(trim($formData['_id']));
		$query		= array('_id' => $mongoID);
		$conn		= $this->connect();
		$collection	= $conn->snapstate->groups;
		$results	= $collection->update($query, $document);
		if($results) {
			return 1;
		} else {
			return 0;
		}
	}
	/***********************************
	 *	Method: selectGroupbyId     	
	 *  Purpose: To select group by _id 
	 **********************************/
	
	public function selectGroupbyId($id) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->groups;
		$results	= $collection->find(array('_id' => new \MongoId($id)));
		while($results->hasNext())
		{
			$resultArray	= $results->getNext();
		}
		if(isset($resultArray)) {
			return $resultArray;
		} else {
			return 0;
		}
	}
	/*******************************
	 *	Method: listUserGroup       
	 *  Purpose: To list user groups
	 ******************************/
	
	public function listUserGroup($page, $limit) {
		$conn			= $this->connect();
		$collection		= $conn->snapstate->groups;
		$skip			= ($page - 1) * $limit;
		$next			= ($page + 1);
		$prev			= ($page - 1);
		$userSession	= new Container('user');
		$listingSession = new Container('listing');
		
		$query	= array();
		if($listingSession->offsetExists('status') && $listingSession->status != '') {
			$query['group_status']	= $listingSession->status;
		}
		if($listingSession->offsetExists('keyword') && $listingSession->keyword != '') {
			$query['group_name']	= 	new \MongoRegex('/' . preg_quote(trim($listingSession->keyword)) . '/i');
		}
		if($listingSession->offsetExists('role') && $listingSession->role != '') {
			$query['group_role']	= 	$listingSession->role;
		}
		if($listingSession->offsetExists('sortBy') && $listingSession->offsetExists('sortType') && $listingSession->sortType == 1) {
			$sort	= array($listingSession->sortBy => 1);
		} else if($listingSession->offsetExists('sortBy') && $listingSession->offsetExists('sortType') && $listingSession->sortType == 0) {
			$sort	= array($listingSession->sortBy => -1);
		} else {
			$sort	= array('group_name' => 1);
		}
		$cursor		= $collection->find($query)->skip($skip)->limit($limit)->sort($sort);
		return $cursor;
	}
	/*******************************
	 *	Method: deleteGroup         
	 *  Module: To Delete User Group
	 ******************************/
	
	public function deleteGroup($id) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->groups;
		$collection->remove(array('_id' => new \MongoId($id)));
	}
	/***********************************
	 *	Method: deleteUsersByGroup      
	 *  Module: To Delete Users by Group
	 **********************************/
	
	public function deleteUsersByGroup($id) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->users;
		$collection->remove(array('user_group' => $id));
	}
	/*******************************
	 *	Method: listGroup           
	 *  Module: To List User Group  
	 ******************************/
	
	public function listGroup() {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->groups;
		$query		= array('group_status' => '1');
		$sort		= array('group_name' => 1);
		$cursor		= $collection->find($query)->sort($sort);
		$resultArray= array();
		
		while($cursor->hasNext())
		{
			$resultArray[]	= $cursor->getNext();
		}
		return $resultArray;
	}
	/*************************************************
	 *	Method: checkEmail                            
	 *  Purpose: To validate the email existence      
	 ************************************************/
	
	public function checkEmail($formData, $opt) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->users;
		$userSession = new Container('user');
		if($opt == 1) {
			$results	= $collection->find(array('user_email' => new \MongoRegex('/^' . preg_quote(trim($formData['user_email'])) . '$/i')));
		} else if($opt == 2) {
			$mongoID	= new \MongoID(trim($formData['_id']));
			$document	= array('_id'	=> array('$ne' => $mongoID), 'user_email' => new \MongoRegex('/^' . preg_quote(trim($formData['user_email'])) . '$/i'));
			$results	= $collection->find($document);
		}
		while($results->hasNext())
		{
			$resultArray	= $results->getNext();
		}
		if(isset($resultArray) && is_array($resultArray)) {
			return 1;
		} else {
			return 0;
		}
	}
	/***********************************
	 *	Method: saveUser     	    	
	 *  Purpose: To insert users		
	 **********************************/
	
	public function saveUser($formData) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->users;
		$userId		= new \MongoId();
		$query		= array('_id'				=> $userId,
							'user_group'		=> $formData['user_group'],
							'user_firstname'	=> trim($formData['user_firstname']),
							'user_lastname'		=> trim($formData['user_lastname']),
							'user_email'		=> trim($formData['user_email']),
							'user_fbuid'		=> trim($formData['user_fbuid']),
							'user_password'		=> md5(trim($formData['user_password'])),
							'user_dob'			=> $formData['user_dob'],
							'user_gender'		=> $formData['user_gender'],
							'user_status'		=> $formData['user_status'],
							'date_created'		=> date('m/d/Y H:i:s'),
							'date_modified'		=> date('m/d/Y H:i:s'));
		$results	= $collection->insert($query);
		if($results) {
			return $userId;
		} else {
			return 0;
		}
	}
	/***********************************
	 *	Method: selectUserbyId      	
	 *  Purpose: To select user by _id  
	 **********************************/
	
	public function selectUserbyId($id) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->users;
		$results	= $collection->find(array('_id' => new \MongoId($id)));
		while($results->hasNext())
		{
			$resultArray	= $results->getNext();
		}
		if(isset($resultArray)) {
			return $resultArray;
		} else {
			return 0;
		}
	}
	/***********************************
	 *	Method: updateUser      	    
	 *  Purpose: To update user by _id  
	 **********************************/
	
	public function updateUser($formData) {
		$updateArray	= array('user_group'		=> $formData['user_group'],
								'user_firstname'	=> trim($formData['user_firstname']),
								'user_lastname'		=> trim($formData['user_lastname']),
								'user_email'		=> trim($formData['user_email']),
								'user_fbuid'		=> trim($formData['user_fbuid']),
								'user_dob'			=> $formData['user_dob'],
								'user_gender'		=> $formData['user_gender'],
								'user_status'		=> $formData['user_status'],
								'date_modified'		=> date('m/d/Y H:i:s'));
		if(trim($formData['user_password']) != '') {
			$updateArray['user_password'] = md5(trim($formData['user_password']));
		}
		
		$document	= array('$set' => $updateArray);
		$mongoID	= new \MongoID(trim($formData['_id']));
		$query		= array('_id' => $mongoID);
		$conn		= $this->connect();
		$collection	= $conn->snapstate->users;
		$results	= $collection->update($query, $document);
		if($results) {
			return 1;
		} else {
			return 0;
		}
	}
	/*******************************
	 *	Method: listUser            
	 *  Purpose: To list user       
	 ******************************/
	
	public function listUser($page, $limit) {
		$conn			= $this->connect();
		$collection		= $conn->snapstate->users;
		$skip			= ($page - 1) * $limit;
		$next			= ($page + 1);
		$prev			= ($page - 1);
		$userSession	= new Container('user');
		$listingSession = new Container('listing');
		
		$groupCollection	= $conn->snapstate->groups;
		$query				= array('group_status' => '1');
		$cursor				= $groupCollection->find($query);
		$groupArray			= array();
		
		while($cursor->hasNext())
		{
			$groupArray[]	= $cursor->getNext();
		}
		
		$query	= array();
		$query	= array('group_id'	=> array('$ne' => '1'));
		if(count($groupArray) > 0) {
			foreach($groupArray as $key => $value) {
				$groupIdArray[]	= (string)$value['_id'];
			}
			$query	= array('user_group' => array('$in' => $groupIdArray));
		}
		if($listingSession->offsetExists('keyword') && $listingSession->keyword != '') {
			$query[$listingSession->field]	= 	new \MongoRegex('/' . preg_quote(trim($listingSession->keyword)) . '/i');
		}
		if($listingSession->offsetExists('status') && $listingSession->status != '') {
			$query['user_status']	= $listingSession->status;
		}
		if($listingSession->offsetExists('gender') && $listingSession->gender != '') {
			$query['user_gender']	= $listingSession->gender;
		}
		if($listingSession->offsetExists('group') && $listingSession->group != '') {
			$query['user_group']	= $listingSession->group;
		}
		if($listingSession->offsetExists('sortBy') && $listingSession->offsetExists('sortType') && $listingSession->sortType == 1) {
			$sort	= array($listingSession->sortBy => 1);
		} else if($listingSession->offsetExists('sortBy') && $listingSession->offsetExists('sortType') && $listingSession->sortType == 0) {
			$sort	= array($listingSession->sortBy => -1);
		} else {
			$sort	= array('user_firstname' => 1);
		}
		$cursor		= $collection->find($query)->skip($skip)->limit($limit)->sort($sort);
		return $cursor;
	}
	/*******************************
	 *	Method: deleteUser          
	 *  Module: To Delete User      
	 ******************************/
	
	public function deleteUser($id) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->users;
		$collection->remove(array('_id' => new \MongoId($id)));
	}
	/************************************************
	 *	Method: checkRole       	                 
	 *  Purpose: To validate the role name existence 
	 ***********************************************/
	
	public function checkRole($formData, $opt) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->roles;
		$userSession = new Container('user');
		if($opt == 1) {
			$results	= $collection->find(array('role_name' => trim($formData['role_name'])));
		} else if($opt == 2) {
			$mongoID	= new \MongoID(trim($formData['_id']));
			$document	= array('_id'	=> array('$ne' => $mongoID), 'role_name' => trim($formData['role_name']));
			$results	= $collection->find($document);
		}
		while($results->hasNext())
		{
			$resultArray	= $results->getNext();
		}
		if(isset($resultArray) && is_array($resultArray)) {
			return 1;
		} else {
			return 0;
		}
	}
	/***********************************
	 *	Method: saveRole     	    	
	 *  Purpose: To insert roles		
	 **********************************/
	
	public function saveRole($formData) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->roles;
		$query		= array('role_name'			=> trim($formData['role_name']),
							'role_activity_1'	=> $formData['role_activity_1'],
							'role_activity_2'	=> $formData['role_activity_2'],
							'role_activity_3'	=> $formData['role_activity_3'],
							'role_activity_4'	=> $formData['role_activity_4'],
							'role_activity_5'	=> $formData['role_activity_5'],
							'role_activity_6'	=> $formData['role_activity_6'],
							'role_activity_7'	=> $formData['role_activity_7'],
							'role_activity_8'	=> $formData['role_activity_8'],
							'role_activity_9'	=> $formData['role_activity_9'],
							'role_status'		=> $formData['role_status']);
		$results	= $collection->insert($query);
		if($results) {
			return 1;
		} else {
			return 0;
		}
	}
	/***********************************
	 *	Method: selectRolebyId      	
	 *  Purpose: To select role by _id  
	 **********************************/
	
	public function selectRolebyId($id) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->roles;
		$results	= $collection->find(array('_id' => new \MongoId($id)));
		while($results->hasNext())
		{
			$resultArray	= $results->getNext();
		}
		if(isset($resultArray)) {
			return $resultArray;
		} else {
			return 0;
		}
	}
	/***********************************
	 *	Method: updateRole      	    
	 *  Purpose: To update role by _id  
	 **********************************/
	
	public function updateRole($formData) {
		
		$updateArray	= array('role_name'		=> trim($formData['role_name']),
							'role_activity_1'	=> $formData['role_activity_1'],
							'role_activity_2'	=> $formData['role_activity_2'],
							'role_activity_3'	=> $formData['role_activity_3'],
							'role_activity_4'	=> $formData['role_activity_4'],
							'role_activity_5'	=> $formData['role_activity_5'],
							'role_activity_6'	=> $formData['role_activity_6'],
							'role_activity_7'	=> $formData['role_activity_7'],
							'role_activity_8'	=> $formData['role_activity_8'],
							'role_activity_9'	=> $formData['role_activity_9'],
							'role_status'		=> $formData['role_status']);
		$document	= array('$set' => $updateArray);
		$mongoID	= new \MongoID(trim($formData['_id']));
		$query		= array('_id' => $mongoID);
		$conn		= $this->connect();
		$collection	= $conn->snapstate->roles;
		$results	= $collection->update($query, $document);
		if($results) {
			return 1;
		} else {
			return 0;
		}
	}
	/*******************************
	 *	Method: listRole            
	 *  Purpose: To list role       
	 ******************************/
	
	public function listRole($page, $limit) {
		$conn			= $this->connect();
		$collection		= $conn->snapstate->roles;
		$skip			= ($page - 1) * $limit;
		$next			= ($page + 1);
		$prev			= ($page - 1);
		$userSession	= new Container('user');
		$listingSession = new Container('listing');
		
		$query	= array();
		if($listingSession->offsetExists('keyword') && $listingSession->keyword != '') {
			$query['role_name']	= 	new \MongoRegex('/' . preg_quote(trim($listingSession->keyword)) . '/i');
		}
		if($listingSession->offsetExists('status') && $listingSession->status != '') {
			$query['role_status']	= $listingSession->status;
		}
		if($listingSession->offsetExists('sortBy') && $listingSession->offsetExists('sortType') && $listingSession->sortType == 1) {
			$sort	= array($listingSession->sortBy => 1);
		} else if($listingSession->offsetExists('sortBy') && $listingSession->offsetExists('sortType') && $listingSession->sortType == 0) {
			$sort	= array($listingSession->sortBy => -1);
		} else {
			$sort	= array('role_name' => 1);
		}
		$cursor		= $collection->find($query)->skip($skip)->limit($limit)->sort($sort);
		return $cursor;
	}
	/*******************************
	 *	Method: deleteRole          
	 *  Module: To Delete Role      
	 ******************************/
	
	public function deleteRole($id) {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->roles;
		$collection->remove(array('_id' => new \MongoId($id)));
	}
	/*******************************
	 *	Method: listRoles           
	 *  Module: To List Roles       
	 ******************************/
	
	public function listRoles() {
		$conn		= $this->connect();
		$collection	= $conn->snapstate->roles;
		$query		= array('role_status' => '1');
		$sort		= array('role_name' => 1);
		$cursor		= $collection->find($query)->sort($sort);
		$resultArray= array();
		
		while($cursor->hasNext())
		{
			$resultArray[]	= $cursor->getNext();
		}
		return $resultArray;
	}
	/************************************************
	 *	Method: checkRoleFlag     	                 
	 *  Purpose: To validate the role                
	 ***********************************************/
	
	public function checkRoleFlag($id) {
		$conn			= $this->connect();
		$collection		= $conn->snapstate->groups;
		$userSession	= new Container('user');
		$results		= $collection->find(array('group_role' => trim($id)));
		while($results->hasNext())
		{
			$resultArray	= $results->getNext();
		}
		if(isset($resultArray) && is_array($resultArray)) {
			return 1;
		} else {
			return 0;
		}
	}
	/************************************************
	 *	Method: getActiveRoles     	                 
	 *  Purpose: To get the active roles             
	 ***********************************************/
	
	public function getActiveRoles() {
		$conn			= $this->connect();
		$collection		= $conn->snapstate->groups;
		$userSession	= new Container('user');
		$results		= $collection->find();
		$resultArray	= array();
		while($results->hasNext())
		{
			$resultArray[]	= $results->getNext();
		}
		return $resultArray;
	}
	/*******************************
	 *	Action: create-group        
	 *  Module: Manage User Group   
	 ******************************/
	
	public function createGroupAction()
    {
		//	Validate Authentication
		$userSession = new Container('user');
		if (!isset($userSession->userSession['_id']) || trim($userSession->userSession['_id']) == '') {
			return $this->redirect()->toRoute('cms', array('controller' => 'index', 'action' => 'index'));
		}
		
		$createGroupForm	= new CreateGroupForm();
		$createGroupForm->get('group_status')->setLabelAttributes(array('class' => 'radio inline'));
		
		$request 		= $this->getRequest();
		$message		= '';
		$errorMessage	= '';
		
		//	Group creation goes here
		if ($request->isPost()) {
			$formPostData	= $request->getPost();
			$access_pages	= '';
			
			//Check whether the group already exists in the database or not
			$results	= $this->checkGroupname($formPostData, 1);
			
			if($results == 1) {	// Group Name already exist
				$message	= 'Group Name already exist.';
				$errorMessage	= '1';
			} else {
				$results	= $this->saveGroup($formPostData);
				if($results) {
					return $this->redirect()->toRoute('cms', array('controller' => 'user', 'action' => 'list-group', 'id' => 1));
				}
			}
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
		if(trim($id) == 1)
			$message	= 'User Group added successfully.';
		else if($message == '')
			$message	= '';
		
		$rolesArray	= $this->listRoles();
		
		return new ViewModel(array(
			'userObject'	=> $userSession->userSession,
			'createGroupForm'=> $createGroupForm,
			'message'		=> $message,
			'rolesArray'	=> $rolesArray,
			'errorMessage'	=> $errorMessage,
		));
    }
	/*******************************
	 *	Action: edit-group          
	 *  Module: Manage User Group   
	 ******************************/
	
	public function editGroupAction()
    {
		//	Validate Authentication
		$userSession = new Container('user');
		if (!isset($userSession->userSession['_id']) || trim($userSession->userSession['_id']) == '') {
			return $this->redirect()->toRoute('cms', array('controller' => 'index', 'action' => 'index'));
		}
		//	Check whether the group id exist or not
		$id = $this->params()->fromRoute('id', 0);
        $groupid	= $id;
		if (!$id) {
            return $this->redirect()->toRoute('cms', array('controller' => 'user', 'action' => 'create-group'));
        }
		
		$createGroupForm	= new CreateGroupForm();
		$createGroupForm->get('group_status')->setLabelAttributes(array('class' => 'radio inline'));
		$group = $this->selectGroupbyId($id);
		
		if(isset($group['_id'])) {
			$createGroupForm->get('_id')->setAttribute('value', $group['_id']);
			$createGroupForm->get('group_name')->setAttribute('value', $group['group_name']);
			$createGroupForm->get('group_role')->setAttribute('value', $group['group_role']);
			$createGroupForm->get('group_status')->setAttribute('value', $group['group_status']);
		}
		$createGroupForm->get('submit')->setAttribute('value', 'Save Changes');
		
		$request 		= $this->getRequest();
		$message		= '';
		$errorMessage	= '';
		
		//	Group creation goes here
		if ($request->isPost()) {
			$formPostData	= $request->getPost();
			$access_pages	= '';
			//Check whether the group already exists in the database or not
			$results	= $this->checkGroupname($formPostData, 2);
			
			if($results == 1) {	// Group Name already exist
				$message	= 'Group Name already exist.';
				$errorMessage	= '1';
			} else {
				$results	= $this->updateGroup($formPostData);
				if($results == 1) {
					return $this->redirect()->toRoute('cms', array('controller' => 'user', 'action' => 'list-group', 'id' => 1));
				}
			}
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
		if(trim($id) == 1)
			$message	= 'User Group updated successfully.';
		else if($message == '')
			$message	= '';
		
		$rolesArray	= $this->listRoles();
		
		return new ViewModel(array(
			'userObject'	=> $userSession->userSession,
			'createGroupForm'=> $createGroupForm,
			'message'		=> $message,
			'errorMessage'	=> $errorMessage,
			'rolesArray'	=> $rolesArray,
			'groupid'		=> $groupid,
		));
    }
	/*******************************
	 *	Action: list-group          
	 *  Module: Manage User Group   
	 ******************************/
	
	public function listGroupAction()
	{
		//	Validate Authentication
		$userSession = new Container('user');
		if (!isset($userSession->userSession['_id']) || trim($userSession->userSession['_id']) == '') {
			return $this->redirect()->toRoute('cms', array('controller' => 'index', 'action' => 'index'));
		}
		//	Destroy listing Session Vars
		$listingSession = new Container('listing');
		$sessionArray	= array();
		foreach($listingSession->getIterator() as $key => $value) {
			$sessionArray[]	= $key;
		}
		foreach($sessionArray as $key => $value) {
			$listingSession->offsetUnset($value);
		}
		//	For Filter form
		$filterForm	= new FilterForm();
		$request = $this->getRequest();
		
		if ($request->isPost()) {
			$filterForm->setData($request->getPost());
			$formData	= $request->getPost();
			
			if(isset($formData['selectStatus']) && $formData['selectStatus'] != 2)
				$listingSession->status	= $formData['selectStatus'];
			else
				$listingSession->status	= '';
			
			if(isset($formData['keyword']) && $formData['keyword'] != '')
				$listingSession->keyword	= $formData['keyword'];
			else
				$listingSession->keyword	= '';
			
			if(isset($formData['selectUserOption']) && $formData['selectUserOption'] != '')
				$listingSession->role	= $formData['selectUserOption'];
			else
				$listingSession->role	= '';
		}
		
		$message	= '';
		$id = (int) $this->params()->fromRoute('id', 0);
		if(trim($id) == 1)
			$message	= 'User Group updated successfully.';
		else if(trim($id) == 2)
			$message	= 'User Group added successfully.';
		else if($message == '')
			$message	= '';
		
		$rolesArray	= $this->listRoles();
		return new ViewModel(array(
			'userObject'	=> $userSession->userSession,
			'filterForm'	=> $filterForm,
			'rolesArray'	=> $rolesArray,
			'message'		=> $message,
		));
	}
	/*******************************
	 *	Action: view-group          
	 *  Module: To list user groups 
	 *	Note:	AJAX call with view 
	 ******************************/
	
	public function viewGroupAction()
    {
		$result = new ViewModel();
	    $result->setTerminal(true);
		
		$matches	= $this->getEvent()->getRouteMatch();
		$page		= $matches->getParam('id', 1);
		$sortBy		= $matches->getParam('sortBy', '');
		$sortType	= $matches->getParam('sortType', '');
		$perPage	= $matches->getParam('perPage', '');
		
		//	Session for listing
		$listingSession = new Container('listing');
		$columnFlag	= 0;
		if($sortBy != '') {
			if($listingSession->sortBy == $sortBy)
				$columnFlag	= 1;
			$listingSession->sortBy	= $sortBy;
		} else if($listingSession->offsetExists('sortBy')) {
			$sortBy	= $listingSession->sortBy;
		}
		if($sortType != '') {
			if($listingSession->sortType == $sortType && $columnFlag == 1)
				$listingSession->sortType	= ($sortType == 1) ? 0 : 1;
			else
				$listingSession->sortType	= $sortType;
		} else if($listingSession->offsetExists('sortType')) {
			$sortType	= $listingSession->sortType;
		}
		if($perPage != '') {
			$listingSession->perPage	= $perPage;
		} else if($listingSession->offsetExists('perPage')) {
			$perPage	= $listingSession->perPage;
		} else {
			$perPage	= 10;
		}
		
		$message		= '';
		$recordsArray	= $this->listUserGroup($page, $perPage);
		$totalRecords	= $recordsArray->count();
		$resultArray	= array();
		
		while($recordsArray->hasNext())
		{
			$resultArray[]	= $recordsArray->getNext();
		}
		$rolesArray	= $this->listRoles();
		$result->setVariables(array('records'		=> $resultArray,
									'message'		=> $message,
									'page'			=> $page,
									'sortBy'		=> $sortBy,
									'perPage'		=> $perPage,
									'totalRecords'	=> $totalRecords,
									'rolesArray'	=> $rolesArray,
									'controller'	=> $this->params('controller')));
		return $result;
    }
	/********************************
	 *	Action: delete-group         
	 *  Module: To delete user groups
	 *	Note:	AJAX call with view  
	 *******************************/
	
	public function deleteGroupAction()
    {
		$id = $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('cms', array('controller' => 'user', 'action' => 'list-group'));
        }
        $this->deleteGroup($id);
		$this->deleteUsersByGroup($id);
        return $this->getResponse();
    }
	/**********************************
	 *	Action: change-password        
	 *  Module: To change user password
	 *	Note:	AJAX call with view    
	 *********************************/
	
	public function changePasswordAction()
	{
		$userSession = new Container('user');
		if (!isset($userSession->userSession['_id']) || trim($userSession->userSession['_id']) == '') {
			return $this->redirect()->toRoute('cms', array('controller' => 'index', 'action' => 'index'));
		}
		
		$changePasswordForm	= new ChangePasswordForm();
		$request		= $this->getRequest();
		$errorMessage	= '';
		$message		= '';
		
		if ($request->isPost()) {
            $changePasswordForm->setInputFilter($this->getUsersModel()->getInputFilterChangePassword());
			$changePasswordForm->setData($request->getPost());
			$formData	= $request->getPost();
			
			if ($changePasswordForm->isValid()) {
				if(md5($formData['password']) == $userSession->userSession['user_password']) {
					$document	= array('$set' => array('user_password' => md5($formData['newpassword'])));
					$query		= array('_id' => $userSession->userSession['_id']);
					$conn		= $this->connect();
					$collection	= $conn->snapstate->users;
					$collection->update($query, $document);
					$userSession->userSession['user_password']	= md5($formData['newpassword']);
					
					$conn		= $this->connect();
					$results	= $this->selectUser($conn, $userSession->userSession['user_email'], md5($formData['newpassword']));
					while($results->hasNext())
					{
						$resultArray	= $results->getNext();
					}
					//	Session for accessibility
					$userSession = new Container('user');
					$userSession->userSession	= $resultArray;
					$message	= 'Password has been updated successfully.';
					
					$changePasswordForm->get('password')->setAttribute('value', '');
					$changePasswordForm->get('newpassword')->setAttribute('value', '');
					$changePasswordForm->get('confirmpassword')->setAttribute('value', '');
				} else {
					$errorMessage	= 1;
					$message		= 'Old Password does not match. Please enter the valid Old Password.';
				}
			} else {
				$errorMessage	= 1;
				$message	= 'Please enter the valid data.';
			}
		}
		
		return new ViewModel(array(
			'userObject'	=> $userSession->userSession,
			'changePasswordForm'=> $changePasswordForm,
			'message'		=> $message,
			'errorMessage'	=> $errorMessage,
		));
	}
	/*******************************
	 *	Action: create-user         
	 *  Module: Manage User         
	 ******************************/
	
	public function createUserAction()
    {
		//	Validate Authentication
		$userSession = new Container('user');
		if (!isset($userSession->userSession['_id']) || trim($userSession->userSession['_id']) == '') {
			return $this->redirect()->toRoute('cms', array('controller' => 'index', 'action' => 'index'));
		}
		
		$createUserForm	= new CreateUserForm();
		$createUserForm->get('user_gender')->setLabelAttributes(array('class' => 'radio inline'));
		$createUserForm->get('user_status')->setLabelAttributes(array('class' => 'radio inline'));
		
		$request 		= $this->getRequest();
		$message		= '';
		$errorMessage	= '';
		
		//	Group creation goes here
		if ($request->isPost()) {
			$createUserForm->setData($request->getPost());
			$formPostData	= $request->getPost();
			$access_pages	= '';
			//Check whether the email already exists in the database or not
			$results	= $this->checkEmail($formPostData, 1);
			
			if($results == 1) {	// Username already exist
				$message	= 'Email already exist.';
				$errorMessage	= '1';
			} else {
				$results	= $this->saveUser($formPostData);
				if($results != 0) {
					if(isset($formPostData['user_status']) && $formPostData['user_status'] == 1) {
						$link		= ACTIVATION_URL.base64_encode($results);
						$subject	= 'Snapstate - Confirmation Mail';
						$message	= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
										<html xmlns="http://www.w3.org/1999/xhtml">
										<head>
										<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
										<title>Congratulations</title>
										</head>
										
										<body>
										<table width="650" border="0" cellspacing="0" cellpadding="0" style="margin:40px auto; background:#fff;">
										
										  <tr>
										    <td><table width="100%" border="0" cellspacing="0" cellpadding="0" style="border:1px solid #DEDEDE;">
										      <tr>
										        <td><table width="100%" border="0" cellspacing="0" cellpadding="0">
										          <tr>
										            <td style="padding:10px;"><img src="'.DOMAINPATH.'/Front/img/mail/logo.png" width="136" height="36" /></td>
										            <td align="right" style="padding-right:10px;" class="txt1"><a href="#">'.ADMIN_EMAIL.'</a></td>
										          </tr>
										          <tr>
										            <td colspan="2" style="background:#DEDEDE;font-size:12px; height:25px;"><table width="100%" border="0" cellspacing="0" cellpadding="0">
										  <tr>
										    <td  style="padding-left:10px; color:#535353">'.date('F, Y').'</td>  
										  </tr>
										</table>
										</td>
										            </tr>
										        </table></td>
										      </tr>
										      <tr>
										        <td style="padding:10px;"><img src="'.DOMAINPATH.'/Front/img/mail/banner.png" width="634" height="215" /></td>
										      </tr>
										      <tr>
										        <td></td>
										      </tr>
										      <tr>
										        <td><table width="100%" border="0" cellspacing="0" cellpadding="0" style="padding:0px 20px;">
										  <tr>
										    <td style="text-align: justify; line-height:18px;color:#1868AE;">Welcome '.ucwords($formPostData['user_firstname']).' '.ucwords($formPostData['user_lastname']).', </td>
										 </tr>
										 <tr>
										    <td style="text-align: center; line-height:28px; padding-bottom:10px; padding-top: 10px; font-size:20px; color:#1868AE"><span class="quotes">“</span> Congratulations! Thank you very much for registering with Snapstate.com. <span class="quotes">”</span></td>
										 </tr>
										   <tr>
										    <td style="color: #147EC2;font-size: 14px;font-weight: normal;padding: 10px 0;">Please click the link below to activate your account:</td>
										  </tr>
										   <tr>
										    <td style="text-align: justify; line-height:18px; padding-bottom:10px"><a href="'.$link.'" title="Click here to activate your account">'.$link.'</a></td>
										 </tr>
										 <tr>
										    <td style="color: #147EC2;text-align: justify; line-height:18px;padding-bottom:10px">Please find the login information below:</td>
										 </tr>
										  <tr>
										    <td style="color: #147EC2;text-align: justify; line-height:18px; padding-bottom:10px">Email Address: ' . $formPostData['user_email'] . '</td>
										 </tr>
										 <tr>
										    <td style="color: #147EC2;text-align: justify; line-height:18px; padding-bottom:10px">Passsword: '.$formPostData['user_password'].'</td>
										 </tr>
										 <tr>
										    <td style="text-align: justify; line-height:18px; padding-bottom:10px;padding-top:10px;">Thanks,</td>
										 </tr>
										 <tr>
										    <td style="text-align: justify; line-height:18px; padding-bottom:10px;">The Snapstate Team.</td>
										 </tr>
										  
										</table>
										</td>
										      </tr>
										    </table></td>
										  </tr>
										  <tr>
										    <td class="txt2" style="padding:10px 0;border:1px solid #DEDEDE; text-align:center;font-size: 11px; background:url('.DOMAINPATH.'/Front/img/mail/footer-bg.png) no-repeat; color:#fff;">© Copyright '.date('Y').' SnapState.com. All rights reserved. </td>
										  </tr>
										</table>
										
										</body>
										</html>';
						$headers  = 'MIME-Version: 1.0' . "\r\n";
						$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
						$headers .= 'From: Snapstate.com <admin@snapstate.com>' . "\r\n";
						$to		= 'To: ' . $formPostData['user_email'] . "\r\n";
						$headersMessage	= $headers . $to;
						
						if(MAILER) {
							//mail('', $subject, $message, $headersMessage);	
							//	SMTP Settings
							$mail = new PHPMailer();
							$mail->IsSMTP();
							$mail->SMTPDebug	= 1;
							$mail->SMTPAuth		= true;
							$mail->SMTPSecure	= 'ssl';
							$mail->Host			= "smtp.gmail.com";
							$mail->Port			= 465;
							$mail->IsHTML(true);
							$mail->Username		= SMTP_USERNAME_DEMO;
							$mail->Password		= SMTP_PASSWORD_DEMO;
							
							$mail->SetFrom("admin@snapstate.com");
							$mail->Subject	= $subject;
							$mail->Body		= $message;
							$mail->AddAddress($formPostData['user_email']);
							$mail->Send();
						}
					}
					return $this->redirect()->toRoute('cms', array('controller' => 'user', 'action' => 'list-user', 'id' => 1));
				} else {
					$message	= 'Please try again';
				}
			}
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
		if(trim($id) == 1)
			$message	= 'User added successfully.';
		else if($message == '')
			$message	= '';
		
		$groupArray	= $this->listGroup();
		
		return new ViewModel(array(
			'userObject'	=> $userSession->userSession,
			'createUserForm'=> $createUserForm,
			'message'		=> $message,
			'groupArray'	=> $groupArray,
			'errorMessage'	=> $errorMessage,
		));
    }
	/*******************************
	 *	Action: edit-user           
	 *  Module: Manage User Group   
	 ******************************/
	
	public function editUserAction()
    {
		//	Validate Authentication
		$userSession = new Container('user');
		if (!isset($userSession->userSession['_id']) || trim($userSession->userSession['_id']) == '') {
			return $this->redirect()->toRoute('cms', array('controller' => 'index', 'action' => 'index'));
		}
		//	Check whether the group id exist or not
		$id = $this->params()->fromRoute('id', 0);
        $userid	= $id;
		if (!$id) {
            return $this->redirect()->toRoute('cms', array('controller' => 'user', 'action' => 'create-user'));
        }
		$createUserForm	= new CreateUserForm();
		$createUserForm->get('user_gender')->setLabelAttributes(array('class' => 'radio inline'));
		$createUserForm->get('user_status')->setLabelAttributes(array('class' => 'radio inline'));
		
		$user = $this->selectUserbyId($id);
		if(isset($user['_id'])) {
			$createUserForm->get('_id')->setAttribute('value', $user['_id']);
			$createUserForm->get('user_group')->setAttribute('value', $user['user_group']);
			$createUserForm->get('user_firstname')->setAttribute('value', $user['user_firstname']);
			$createUserForm->get('user_lastname')->setAttribute('value', $user['user_lastname']);
			$createUserForm->get('user_email')->setAttribute('value', $user['user_email']);
			$createUserForm->get('user_dob')->setAttribute('value', $user['user_dob']);
			$createUserForm->get('user_fbuid')->setAttribute('value', $user['user_fbuid']);
			$createUserForm->get('user_gender')->setAttribute('value', $user['user_gender']);
			$createUserForm->get('user_status')->setAttribute('value', $user['user_status']);
		}
		$createUserForm->get('submit')->setAttribute('value', 'Save Changes');
		$request 		= $this->getRequest();
		$message		= '';
		$errorMessage	= '';
		
		//	Group creation goes here
		if ($request->isPost()) {
			$createUserForm->setData($request->getPost());
			$formPostData	= $request->getPost();
			$access_pages	= '';
			//Check whether the Email already exists in the database or not
			$results	= $this->checkEmail($formPostData, 2);
			if($results == 1) {	// Email already exist
				$message	= 'Email already exist.';
				$errorMessage	= '1';
			} else {
				$results	= $this->updateUser($formPostData);
				if($results == 1) {
					if(isset($formPostData['user_password']) && trim($formPostData['user_password']) != '') {
						
						$username	= $formPostData['user_firstname'].' '.$formPostData['user_lastname'];
						$email		= $formPostData['user_email'];
						$password	= $formPostData['user_password'];
						//	Forgot Password Mailer
						
						$subject	= 'Snapstate - Change Password';
						$message	= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
										<html xmlns="http://www.w3.org/1999/xhtml">
										<head>
										<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
										<title>Change Password</title>
										</head>
										
										<body>
										<table width="650" border="0" cellspacing="0" cellpadding="0" style="margin:40px auto; background:#fff;">
										
										  <tr>
										    <td><table width="100%" border="0" cellspacing="0" cellpadding="0" style="border:1px solid #DEDEDE;">
										      <tr>
										        <td><table width="100%" border="0" cellspacing="0" cellpadding="0">
										          <tr>
										            <td style="padding:10px;"><img src="'.DOMAINPATH.'/Front/img/mail/logo.png" width="136" height="36" /></td>
										            <td align="right" style="padding-right:10px;" class="txt1"><a href="#">'.ADMIN_EMAIL.'</a></td>
										          </tr>
										          <tr>
										            <td colspan="2" style="background:#DEDEDE;font-size:12px; height:25px;"><table width="100%" border="0" cellspacing="0" cellpadding="0">
										  <tr>
										    <td  style="padding-left:10px; color:#535353">'.date('F, Y').'</td>  
										  </tr>
										</table>
										</td>
										            </tr>
										        </table></td>
										      </tr>
										      <tr>
										        <td style="padding:10px;"><img src="'.DOMAINPATH.'/Front/img/mail/banner.png" width="634" height="215" /></td>
										      </tr>
										      <tr>
										        <td></td>
										      </tr>
										      <tr>
										        <td><table width="100%" border="0" cellspacing="0" cellpadding="0" style="padding:0px 20px;">
										  <tr>
										    <td style="text-align: justify; line-height:18px;color:#1868AE;">Welcome '.ucwords($username).', </td>
										 </tr>
										 <tr>
										    <td style="color: #147EC2;text-align: justify; line-height:18px;padding-bottom:10px; padding-top:10px;">Your existing password has been changed. Please find the login information below:</td>
										 </tr>
										  <tr>
										    <td style="color: #147EC2;text-align: justify; line-height:18px; padding-bottom:10px">Email Address: ' . $email . '</td>
										 </tr>
										 <tr>
										    <td style="color: #147EC2;text-align: justify; line-height:18px; padding-bottom:10px">Passsword: '.$password.'</td>
										 </tr>
										 <tr>
										    <td style="text-align: justify; line-height:18px; padding-bottom:10px;padding-top:10px;">Thanks,</td>
										 </tr>
										 <tr>
										    <td style="text-align: justify; line-height:18px; padding-bottom:10px;">The Snapstate Team.</td>
										 </tr>
										  
										</table>
										</td>
										      </tr>
										    </table></td>
										  </tr>
										  <tr>
										    <td class="txt2" style="padding:10px 0;border:1px solid #DEDEDE; text-align:center;font-size: 11px; background:url('.DOMAINPATH.'/Front/img/mail/footer-bg.png) no-repeat; color:#fff;">© Copyright 2013 SnapState.com. All rights reserved. </td>
										  </tr>
										</table>
										
										</body>
										</html>';
						$headers  = 'MIME-Version: 1.0' . "\r\n";
						$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
						$headers .= 'From: Snapstate.com <admin@snapstate.com>' . "\r\n";
						$to		= 'To: ' . $email . "\r\n";
						$headersMessage	= $headers . $to;
						
						if(MAILER) {
							//mail('', $subject, $message, $headersMessage);
							//	SMTP Settings
							$mail = new PHPMailer();
							$mail->IsSMTP();
							$mail->SMTPDebug	= 1;
							$mail->SMTPAuth		= true;
							$mail->SMTPSecure	= 'ssl';
							$mail->Host			= "smtp.gmail.com";
							$mail->Port			= 465;
							$mail->IsHTML(true);
							$mail->Username		= SMTP_USERNAME_DEMO;
							$mail->Password		= SMTP_PASSWORD_DEMO;
							
							$mail->SetFrom("admin@snapstate.com");
							$mail->Subject	= $subject;
							$mail->Body		= $message;
							$mail->AddAddress($email);
							$mail->Send();
						}
					}
					return $this->redirect()->toRoute('cms', array('controller' => 'user', 'action' => 'list-user', 'id' => 1));
				} else {
					$message	= 'Please try again';
				}
			}
			
		}
		$groupArray	= $this->listGroup();
		$id = (int) $this->params()->fromRoute('id', 0);
		if(trim($id) == 1)
			$message	= 'User updated successfully.';
		else if($message == '')
			$message	= '';
		
		return new ViewModel(array(
			'userObject'	=> $userSession->userSession,
			'createUserForm'=> $createUserForm,
			'message'		=> $message,
			'errorMessage'	=> $errorMessage,
			'userid'		=> $userid,
			'groupArray'	=> $groupArray,
		));
    }
	/*******************************
	 *	Action: list-user           
	 *  Module: Manage User Group   
	 ******************************/
	
	public function listUserAction()
	{
		//	Validate Authentication
		$userSession = new Container('user');
		if (!isset($userSession->userSession['_id']) || trim($userSession->userSession['_id']) == '') {
			return $this->redirect()->toRoute('cms', array('controller' => 'index', 'action' => 'index'));
		}
		//	Destroy listing Session Vars
		$listingSession = new Container('listing');
		$sessionArray	= array();
		foreach($listingSession->getIterator() as $key => $value) {
			$sessionArray[]	= $key;
		}
		foreach($sessionArray as $key => $value) {
			$listingSession->offsetUnset($value);
		}
		//	For Filter form
		$filterForm	= new FilterForm();
		$request = $this->getRequest();
		
		if ($request->isPost()) {
			$filterForm->setData($request->getPost());
			$formData	= $request->getPost();
			
			if(isset($formData['selectUserOption']) && $formData['selectUserOption'] != '')
				$listingSession->group	= $formData['selectUserOption'];
			else
				$listingSession->group	= '';
			
			if(isset($formData['keyword']) && $formData['keyword'] != '')
				$listingSession->keyword	= $formData['keyword'];
			else
				$listingSession->keyword	= '';
			
			if(isset($formData['selectOption']) && $formData['selectOption'] != '')
				$listingSession->field	= $formData['selectOption'];
			else
				$listingSession->field	= '';
			
			if(isset($formData['selectGender']) && $formData['selectGender'] != 0)
				$listingSession->gender	= $formData['selectGender'];
			else
				$listingSession->gender	= '';
			
			if(isset($formData['selectStatus']) && $formData['selectStatus'] != 2)
				$listingSession->status	= $formData['selectStatus'];
			else
				$listingSession->status	= '';
		}
		
		$message	= '';
		$id = (int) $this->params()->fromRoute('id', 0);
		if(trim($id) == 1)
			$message	= 'User updated successfully.';
		else if(trim($id) == 2)
			$message	= 'User added successfully.';
		else if($message == '')
			$message	= '';
		
		$groupArray	= $this->listGroup();
		return new ViewModel(array(
			'userObject'	=> $userSession->userSession,
			'filterForm'	=> $filterForm,
			'group'			=> $groupArray,
			'message'		=> $message,
		));
	}
	/*******************************
	 *	Action: view-user           
	 *  Module: To list user        
	 *	Note:	AJAX call with view 
	 ******************************/
	
	public function viewUserAction()
    {
		$result = new ViewModel();
	    $result->setTerminal(true);
		
		$matches	= $this->getEvent()->getRouteMatch();
		$page		= $matches->getParam('id', 1);
		$sortBy		= $matches->getParam('sortBy', '');
		$sortType	= $matches->getParam('sortType', '');
		$perPage	= $matches->getParam('perPage', '');
		
		//	Session for listing
		$listingSession = new Container('listing');
		$columnFlag	= 0;
		if($sortBy != '') {
			if($listingSession->sortBy == $sortBy)
				$columnFlag	= 1;
			$listingSession->sortBy	= $sortBy;
		} else if($listingSession->offsetExists('sortBy')) {
			$sortBy	= $listingSession->sortBy;
		}
		if($sortType != '') {
			if($listingSession->sortType == $sortType && $columnFlag == 1)
				$listingSession->sortType	= ($sortType == 1) ? 0 : 1;
			else
				$listingSession->sortType	= $sortType;
		} else if($listingSession->offsetExists('sortType')) {
			$sortType	= $listingSession->sortType;
		}
		if($perPage != '') {
			$listingSession->perPage	= $perPage;
		} else if($listingSession->offsetExists('perPage')) {
			$perPage	= $listingSession->perPage;
		} else {
			$perPage	= 10;
		}
		
		$message		= '';
		$recordsArray	= $this->listUser($page, $perPage);
		$totalRecords	= $recordsArray->count();
		$resultArray	= array();
		
		while($recordsArray->hasNext())
		{
			$resultArray[]	= $recordsArray->getNext();
		}
		$groupArray	= $this->listGroup();
		$result->setVariables(array('records'		=> $resultArray,
									'message'		=> $message,
									'page'			=> $page,
									'group'			=> $groupArray,
									'sortBy'		=> $sortBy,
									'perPage'		=> $perPage,
									'totalRecords'	=> $totalRecords,
									'controller'	=> $this->params('controller')));
		return $result;
    }
	/********************************
	 *	Action: delete-user          
	 *  Module: To delete user       
	 *	Note:	AJAX call with view  
	 *******************************/
	
	public function deleteUserAction()
    {
		$id = $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('cms', array('controller' => 'user', 'action' => 'list-user'));
        }
        $this->deleteUser($id);
        return $this->getResponse();
    }
	/*******************************
	 *	Action: create-role         
	 *  Module: Manage User         
	 ******************************/
	
	public function createRoleAction()
    {
		//	Validate Authentication
		$userSession = new Container('user');
		if (!isset($userSession->userSession['_id']) || trim($userSession->userSession['_id']) == '') {
			return $this->redirect()->toRoute('cms', array('controller' => 'index', 'action' => 'index'));
		}
		
		$activityArray	= array(1 => 'View Video Page', 'Rate Video', 'Facebook Share', 'Twitter Share', 'Post Comments', 'Contribute Layer', 'Suggestion Layer', 'Video Suggestion Page', 'Feedback Layer');
		$createRoleForm	= new CreateRoleForm();
		$createRoleForm->get('role_status')->setLabelAttributes(array('class' => 'radio inline'));
		$request 		= $this->getRequest();
		$message		= '';
		$errorMessage	= '';
		
		//	Role creation goes here
		if ($request->isPost()) {
			$createRoleForm->setData($request->getPost());
			$formPostData	= $request->getPost();
			$access_pages	= '';
			
			//Check whether the role already exists in the database or not
			$results	= $this->checkRole($formPostData, 1);
			
			if($results == 1) {	// Role already exist
				$message	= 'Role name already exist.';
				$errorMessage	= '1';
			} else {
				$results	= $this->saveRole($formPostData);
				if($results) {
					return $this->redirect()->toRoute('cms', array('controller' => 'user', 'action' => 'list-roles', 'id' => 1));
				} else {
					$message	= 'Please try again';
				}
			}
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
		if(trim($id) == 1)
			$message	= 'Role created successfully.';
		else if($message == '')
			$message	= '';
		
		return new ViewModel(array(
			'userObject'	=> $userSession->userSession,
			'createRoleForm'=> $createRoleForm,
			'message'		=> $message,
			'errorMessage'	=> $errorMessage,
			'activityArray'	=> $activityArray,
		));
    }
	/*******************************
	 *	Action: edit-role          
	 *  Module: Manage User         
	 ******************************/
	
	public function editRoleAction()
    {
		//	Validate Authentication
		$userSession = new Container('user');
		if (!isset($userSession->userSession['_id']) || trim($userSession->userSession['_id']) == '') {
			return $this->redirect()->toRoute('cms', array('controller' => 'index', 'action' => 'index'));
		}
		//	Check whether the role id exist or not
		$id = $this->params()->fromRoute('id', 0);
		//	Check whether the role is used by groups
		$roleFlag	= $this->checkRoleFlag($id);
		
        $roleid	= $id;
		if (!$id) {
            return $this->redirect()->toRoute('cms', array('controller' => 'user', 'action' => 'create-role'));
        }
		$activityArray	= array(1 => 'View Video Page', 'Rate Video', 'Facebook Share', 'Twitter Share', 'Post Comments', 'Contribute Layer', 'Suggestion Layer', 'Video Suggestion Page', 'Feedback Layer');
		$createRoleForm	= new CreateRoleForm();
		$createRoleForm->get('role_status')->setLabelAttributes(array('class' => 'radio inline'));
		
		$role = $this->selectRolebyId($id);
		if(isset($role['_id'])) {
			$createRoleForm->get('_id')->setAttribute('value', $role['_id']);
			$createRoleForm->get('role_name')->setAttribute('value', $role['role_name']);
			$createRoleForm->get('role_activity_1')->setAttribute('value', $role['role_activity_1']);
			$createRoleForm->get('role_activity_2')->setAttribute('value', $role['role_activity_2']);
			$createRoleForm->get('role_activity_3')->setAttribute('value', $role['role_activity_3']);
			$createRoleForm->get('role_activity_4')->setAttribute('value', $role['role_activity_4']);
			$createRoleForm->get('role_activity_5')->setAttribute('value', $role['role_activity_5']);
			$createRoleForm->get('role_activity_6')->setAttribute('value', $role['role_activity_6']);
			$createRoleForm->get('role_activity_7')->setAttribute('value', $role['role_activity_7']);
			$createRoleForm->get('role_activity_8')->setAttribute('value', $role['role_activity_8']);
			$createRoleForm->get('role_activity_9')->setAttribute('value', $role['role_activity_9']);
			$createRoleForm->get('role_status')->setAttribute('value', $role['role_status']);
		}
		$createRoleForm->get('submit')->setAttribute('value', 'Save Changes');
		$request 		= $this->getRequest();
		$message		= '';
		$errorMessage	= '';
		
		//	Role creation goes here
		if ($request->isPost()) {
			$createRoleForm->setData($request->getPost());
			$formPostData	= $request->getPost();
			$access_pages	= '';
			
			//Check whether the role already exists in the database or not
			$results	= $this->checkRole($formPostData, 2);
			
			if($results == 1) {	// Role already exist
				$message	= 'Role name already exist.';
				$errorMessage	= '1';
			} else {
				$results	= $this->updateRole($formPostData);
				if($results) {
					return $this->redirect()->toRoute('cms', array('controller' => 'user', 'action' => 'list-roles', 'id' => 1));
				} else {
					$message	= 'Please try again';
				}
			}
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
		if(trim($id) == 1)
			$message	= 'Role updated successfully.';
		else if($message == '')
			$message	= '';
		
		return new ViewModel(array(
			'userObject'	=> $userSession->userSession,
			'createRoleForm'=> $createRoleForm,
			'message'		=> $message,
			'roleid'		=> $roleid,
			'roleFlag'		=> $roleFlag,
			'errorMessage'	=> $errorMessage,
			'activityArray'	=> $activityArray,
		));
    }
	/********************************
	 *	Action: list-roles           
	 *  Module: Manage User Roles    
	 *******************************/
	
	public function listRolesAction()
	{
		//	Validate Authentication
		$userSession = new Container('user');
		if (!isset($userSession->userSession['_id']) || trim($userSession->userSession['_id']) == '') {
			return $this->redirect()->toRoute('cms', array('controller' => 'index', 'action' => 'index'));
		}
		//	Destroy listing Session Vars
		$listingSession = new Container('listing');
		$sessionArray	= array();
		foreach($listingSession->getIterator() as $key => $value) {
			$sessionArray[]	= $key;
		}
		foreach($sessionArray as $key => $value) {
			$listingSession->offsetUnset($value);
		}
		//	For Filter form
		$filterForm	= new FilterForm();
		$request = $this->getRequest();
		
		if ($request->isPost()) {
			$filterForm->setData($request->getPost());
			$formData	= $request->getPost();
			
			if(isset($formData['keyword']) && $formData['keyword'] != '')
				$listingSession->keyword	= $formData['keyword'];
			else
				$listingSession->keyword	= '';
			
			if(isset($formData['selectStatus']) && $formData['selectStatus'] != 2)
				$listingSession->status	= $formData['selectStatus'];
			else
				$listingSession->status	= '';
		}
		
		$message	= '';
		$id = (int) $this->params()->fromRoute('id', 0);
		if(trim($id) == 1)
			$message	= 'Roles updated successfully.';
		else if(trim($id) == 2)
			$message	= 'Role added successfully.';
		else if($message == '')
			$message	= '';
		
		$activeRoles	= $this->getActiveRoles();
		$activeRolesArray	= array();
		if(count($activeRoles) > 0) {
			foreach($activeRoles as $pkey => $pvalue) {
				$activeRolesArray[$pvalue['group_role']]	=	1;
			}
		}
		
		$activityArray	= array(1 => 'View Video Page', 'Rate Video', 'Facebook Share', 'Twitter Share', 'Post Comments', 'Contribute Layer', 'Suggestion Layer', 'Video Suggestion Page', 'Feedback Layer');
		$groupArray	= $this->listGroup();
		return new ViewModel(array(
			'userObject'	=> $userSession->userSession,
			'filterForm'	=> $filterForm,
			'group'			=> $groupArray,
			'activity'		=> $activityArray,
			'activeRoles'	=> $activeRolesArray,
			'message'		=> $message,
		));
	}
	/*******************************
	 *	Action: view-role           
	 *  Module: To list roles       
	 *	Note:	AJAX call with view 
	 ******************************/
	
	public function viewRoleAction()
    {
		$result = new ViewModel();
	    $result->setTerminal(true);
		
		$matches	= $this->getEvent()->getRouteMatch();
		$page		= $matches->getParam('id', 1);
		$sortBy		= $matches->getParam('sortBy', '');
		$sortType	= $matches->getParam('sortType', '');
		$perPage	= $matches->getParam('perPage', '');
		
		//	Session for listing
		$listingSession = new Container('listing');
		$columnFlag	= 0;
		if($sortBy != '') {
			if($listingSession->sortBy == $sortBy)
				$columnFlag	= 1;
			$listingSession->sortBy	= $sortBy;
		} else if($listingSession->offsetExists('sortBy')) {
			$sortBy	= $listingSession->sortBy;
		}
		if($sortType != '') {
			if($listingSession->sortType == $sortType && $columnFlag == 1)
				$listingSession->sortType	= ($sortType == 1) ? 0 : 1;
			else
				$listingSession->sortType	= $sortType;
		} else if($listingSession->offsetExists('sortType')) {
			$sortType	= $listingSession->sortType;
		}
		if($perPage != '') {
			$listingSession->perPage	= $perPage;
		} else if($listingSession->offsetExists('perPage')) {
			$perPage	= $listingSession->perPage;
		} else {
			$perPage	= 10;
		}
		
		$message		= '';
		$recordsArray	= $this->listRole($page, $perPage);
		$totalRecords	= $recordsArray->count();
		$resultArray	= array();
		
		while($recordsArray->hasNext())
		{
			$resultArray[]	= $recordsArray->getNext();
		}
		
		$activeRoles	= $this->getActiveRoles();
		$activeRolesArray	= array();
		if(count($activeRoles) > 0) {
			foreach($activeRoles as $pkey => $pvalue) {
				$activeRolesArray[$pvalue['group_role']]	=	1;
			}
		}
		
		$groupArray	= $this->listGroup();
		$result->setVariables(array('records'		=> $resultArray,
									'message'		=> $message,
									'page'			=> $page,
									'group'			=> $groupArray,
									'sortBy'		=> $sortBy,
									'perPage'		=> $perPage,
									'activeRoles'	=> $activeRolesArray,
									'totalRecords'	=> $totalRecords,
									'controller'	=> $this->params('controller')));
		return $result;
    }
	/********************************
	 *	Action: delete-role          
	 *  Module: To delete role       
	 *	Note:	AJAX call with view  
	 *******************************/
	
	public function deleteRoleAction()
    {
		$id = $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('cms', array('controller' => 'user', 'action' => 'list-roles'));
        }
        $this->deleteRole($id);
        return $this->getResponse();
    }
	/*********************************
	 * Model: Users                   
	 * Purpose: To validate users data
	 ********************************/
	
	public function getUsersModel()
    {
        if (!isset($this->usersModel)) {
            $sm = $this->getServiceLocator();
            $this->usersModel = $sm->get('Cms\Model\Users');
        }
        return $this->usersModel;
    }
	/*********************************
	 * Model: Group                   
	 * Purpose: To validate group data
	 ********************************/
	
	public function getGroupModel()
    {
        if (!isset($this->groupModel)) {
            $sm = $this->getServiceLocator();
            $this->groupModel = $sm->get('Cms\Model\Group');
        }
        return $this->groupModel;
    }
}
