<?php
class Project extends SS_controller{
	
	var $section_title='事项';
	
	var $form_validation_rules=array();
	
	var $list_args;
	
	var $people_list_args;
	
	var $account_list_args;
	
	var $miscfee_list_args;
	
	var $status_list_args;
	
	var $schedule_list_args;
	
	var $plan_list_args;

	var $document_list_args;
	
	var $relative_list_args;
	
	function __construct(){
		parent::__construct();
		
		$controller=CONTROLLER;
		
		$this->form_validation_rules['people'][]=array('rules'=>'required','label'=>'相关人员姓名','field'=>'people[name]');
		$this->form_validation_rules['account']=array(
			array('field'=>'account[type]','label'=>'收费类型','rules'=>'required'),
			array('field'=>'account[amount]','label'=>'金额','rules'=>'required|numeric'),
			array('field'=>'account[date]','label'=>'收费日起','rules'=>'required')
		);
		
		$this->list_args=array(
			'name'=>array('heading'=>'案名','cell'=>'{name}'),
			'people'=>array('heading'=>'人员','cell'=>array('class'=>'ellipsis'),'parser'=>array('function'=>array($this->$controller,'getCompiledPeople'),'args'=>array('{id}'))),
			'labels'=>array('heading'=>'标签','parser'=>array('function'=>array($this->$controller,'getCompiledLabels'),'args'=>array('{id}')))
			/*
			 * 此处被迫使用了$this->$controller来调用被继承后的model。
			 * 因为Project::__construct()时，Cases::__construct()尚未运行，
			 * $this->project=$this->cases也尚未运行，因此$this->project未定义
			 */
		);
		
		$this->people_list_args=array(
			'name'=>array('heading'=>'名称','cell'=>'{abbreviation}<button type="submit" name="submit[remove_people]" id="{id}" class="hover">删除</button>'),
			'role'=>array('heading'=>'角色')
		);
		
		$this->schedule_list_args=array(
			'name'=>array('heading'=>array('data'=>'标题','width'=>'150px'),'wrap'=>array('mark'=>'span','class'=>'show-schedule','id'=>'{id}')),
			'time_start'=>array('heading'=>array('data'=>'时间','width'=>'60px'),'eval'=>true,'cell'=>"
				if('{time_start}') return date('m-d H:i','{time_start}');
			"),
			'username'=>array('heading'=>array('data'=>'填写人','width'=>'90px'))
		);
		
		$this->plan_list_args=array(
			'name'=>array('heading'=>array('data'=>'标题','width'=>'150px'),'wrap'=>array('mark'=>'span','class'=>'show-schedule','id'=>'{id}')),
			'time_start'=>array('heading'=>array('data'=>'时间','width'=>'60px'),'eval'=>true,'cell'=>"
				if('{time_start}') return date('m-d H:i','{time_start}');
			"),
			'username'=>array('heading'=>array('data'=>'填写人','width'=>'90px'))
		);
		
		$this->status_list_args=array();
		
		$this->account_list_args=array(
			'account'=>array('heading'=>'帐目编号'),
			'type'=>array('heading'=>'类型','cell'=>'{type}'),
			'amount'=>array('heading'=>array('data'=>'数额','width'=>'30%'),'eval'=>true,'cell'=>"
				\$return='{total}'.('{received}'==''?'':' <span title=\"{received_date}\">（到账：{received}）</span>');
				return \$return;
			"),
			'receivable_date'=>array('heading'=>'预计时间'),
			'comment'=>array('heading'=>'备注','cell'=>array('class'=>'ellipsis','title'=>'{comment}'))
		);
		
		$this->relative_list_args=array(
			'name'=>array('heading'=>'名称'),
			'relation'=>array('heading'=>'关系'),
		);
	}
	
	function index(){
		
		$this->config->set_user_item('search/people', $this->user->id, false);
		$this->config->set_user_item('search/orderby', 'project.id desc', false);
		$this->config->set_user_item('search/limit', 'pagination', false);
		
		$search_items=array('name','labels');
		
		foreach($search_items as $item){
			if($this->input->post($item)!==false){
				if($this->input->post($item)!==''){
					$this->config->set_user_item('search/'.$item, $this->input->post($item));
				}else{
					$this->config->unset_user_item('search/'.$item);
				}
			}
		}
		
		if($this->input->post('submit')==='search' && $this->input->post('labels')===false){
			$this->config->unset_user_item('search/labels');
		}
		
		if($this->input->post('submit')==='search_cancel'){
			foreach($search_items as $item){
				$this->config->unset_user_item('search/'.$item);
			}
		}
		
		$table=$this->table->setFields($this->list_args)
			->setRowAttributes(array('hash'=>CONTROLLER.'/edit/{id}'))
			->setData($this->project->getList($this->config->user_item('search')))
			->generate();
		
		$this->load->addViewData('list',$table);
		$this->load->view('list');
		$this->load->view('project/list_sidebar',true,'sidebar');
	}
	
	function add(){
		$data=array();
		if($this->config->item('project/index/search/type')!==false){
			$data['type']=$this->config->item('project/index/search/type');
		}
		$this->project->id=$this->project->add($data);
		$this->project->addPeople($this->project->id, $this->user->id, NULL, '创建人');
		$this->edit($this->project->id);
		redirect('#'.CONTROLLER.'/edit/'.$this->project->id);
	}
	
	function edit($id){
		$this->project->id=$id;
		
		try{
			$this->project->data=array_merge($this->project->fetch($id),$this->input->sessionPost('project'));

			$this->project->labels=array_merge($this->project->getLabels($this->project->id),$this->input->sessionPost('labels'));

			if(!$this->project->data['name']){
				$this->section_title='未命名'.$this->section_title;
			}else{
				$this->section_title=$this->project->data['name'];
			}
			
			$this->load->addViewData('project', $this->project->data);
			
			$this->load->addViewData('labels', $this->project->labels);

			$this->load->addViewData('people_list', $this->peopleList());
			
			$this->load->addViewData('schedule_list', $this->scheduleList());
			
			$this->load->addViewData('document_list', $this->documentList());
			
			$this->load->addViewData('relative_list', $this->relativeList());

			$this->load->view('project/edit');
			
			$this->load->view('project/edit_sidebar',true,'sidebar');
		}
		catch(Exception $e){
			$this->output->status='fail';
			if($e->getMessage()){
				$this->output->message($e->getMessage(), 'warning');
			}
		}

	}
	
	function peopleList(){
		$this->load->model('people_model','people');
		
		return $this->table->setFields($this->people_list_args)
			->setRowAttributes(array('hash'=>'people/edit/{id}'))
			->setAttribute('name', 'people')
			->generate($this->people->getList(array('project'=>$this->project->id)));
	}

	function accountList(){
		
		$this->load->model('account_model','account');
		
		$list=$this->table->setFields($this->account_list_args)
				->setAttribute('name','account')
				->generate($this->account->getList(array('project'=>$this->project->id,'group'=>'account')));
		
		return $list;
	}
	
	function documentList(){
		$this->load->model('document_model','document');

		$this->document_list_args=array(
			'name'=>array('heading'=>'文件名','cell'=>'<a href="/document/download/{id}">{name}</a>'),
			'time_insert'=>array('heading'=>'上传时间','parser'=>array('function'=>function($time_insert){return date('Y-m-d H:i:s',$time_insert);},'args'=>array('{time_insert}'))),
			'labels'=>array('heading'=>'标签','parser'=>array('function'=>array($this->document,'getCompiledLabels'),'args'=>array('{id}')))
		);
		
		return $this->table->setFields($this->document_list_args)
			->setAttribute('name','document')
			->generate($this->document->getList(array('project'=>$this->project->id)));
	}
	
	function scheduleList(){
		
		$this->load->model('schedule_model','schedule');
		
		return $this->table->setFields($this->schedule_list_args)
			->setAttribute('name','schedule')
			//@TODO 点击列表打开日程尚有问题
			->setRowAttributes(array('onclick'=>"$.viewSchedule(\{id:{id}\})"))
			->generate($this->schedule->getList(array('limit'=>10,'project'=>$this->project->id,'completed'=>true)));
	}
	
	function planList(){
		return $this->table->setFields($this->plan_list_args)
			->setAttribute('name','plan')
			->generate($this->schedule->getList(array('limit'=>10,'project'=>$this->project->id,'completed'=>false)));
	}
	
	function statusList(){
		
	}
	
	/**
	 * 相关项目列表
	 */
	function relativeList(){
		return $this->table->setFields($this->relative_list_args)
			->setAttribute('name','relatives')
			->setRowAttributes(array('hash'=>CONTROLLER.'/edit/{id}'))
			->generate($this->project->getList(array('limit'=>10,'is_relative_of'=>$this->project->id)));
	}
	
	function submit($submit,$id,$button_id=NULL){
		
		$this->project->id=$id;
		
		$this->project->data=array_merge($this->project->fetch($id),$this->input->sessionPost('project'));
		$this->project->labels=array_merge($this->project->getLabels($this->project->id),$this->input->sessionPost('labels'));
		
		$this->load->library('form_validation');
		
		try{
		
			if(isset($this->form_validation_rules[$submit])){
				$this->form_validation->set_rules($this->form_validation_rules[$submit]);
				if($this->form_validation->run()===false){
					$this->output->message(validation_errors(),'warning');
					throw new Exception;
				}
			}

			if($submit=='cancel'){
				unset($_SESSION[CONTROLLER]['post'][$this->project->id]);
				$this->output->status='close';
			}
		
			elseif($submit=='project'){
				$this->project->labels=$this->input->sessionPost('labels');
				$this->project->update($this->project->id,$this->project->data);
				$this->project->updateLabels($this->project->id,$this->project->labels);
				
				unset($_SESSION[CONTROLLER]['post'][$this->project->id]);
				$this->output->message($this->section_title.' 已保存');
			}
			
			elseif($submit=='people'){
				
				$this->load->model('people_model','people');
				
				$people=$this->input->sessionPost('people');
				if(!$people['id']){
					$people['id']=$this->people->check($people['name']);
					
					if($people['id']){
						post('people/id',$people['id']);
					}else{
						$this->output->message('请输入人员名称','warning');
						throw new Exception;
					}
				}
				
				if($this->project->addPeople($this->project->id,$people['id'],NULL,$people['role'])){
					$this->output->setData($this->peopleList(),'content-table','html','.item[name="people"]>.contentTable','replace');
					unset($_SESSION[CONTROLLER]['post'][$this->project->id]['people']['id']);
				}else{
					$this->output->message('人员添加错误', 'warning');
				}

				unset($_SESSION[CONTROLLER]['post'][$this->project->id]['people']);
			}
			
			elseif($submit=='remove_people'){
				if($this->project->removePeople($this->project->id,$button_id)){
					$this->output->setData($this->peopleList(),'content-table','html','.item[name="people"]>.contentTable','replace');
				}
			}
			
			elseif($submit=='account'){
				
				$this->load->model('account_model','account');
				
				$account=$this->input->sessionPost('account');

				if(!is_numeric($account['amount'])){
					$this->output->message('请预估收费金额（数值）','warning');
				}
				
				if(!$account['date']){
					$this->output->message('请预估收费时间','warning');
				}
				
				if(count($this->output->message['warning'])>0){
					throw new Exception;
				}
				
				if(in_array('咨询',$this->project->labels)){
					$subject='咨询费';
				}elseif(in_array('法律顾问',$this->project->labels)){
					$subject='顾问费';
				}else{
					$subject='律师费';
				}
				
				$this->account->add($account+array('project'=>$this->project->id,'subject'=>$subject));
				$this->output->setData($this->accountList(),'content-table','html','.item[name="account"]>.contentTable','replace');
				
				unset($_SESSION[CONTROLLER]['post'][$this->project->id]['account']);
			}
			
			elseif($submit=='remove_fee' || $submit=='remove_miscfee'){
				$this->project->removeFee($this->project->id,$button_id);
				
				if($submit=='remove_fee'){
					$this->output->setData($this->accountList(),'content-table','html','.item[name="account"]>.contentTable','replace');
				}else{
					$this->output->setData($this->miscfeeList(),'content-table','html','.item[name="miscfee"]>.contentTable','replace');
				}
			}
			
			elseif($submit=='case_fee_misc'){
				
				$misc_fee=$this->input->sessionPost('case_fee_misc');
				
				if(!$misc_fee['receiver']){
					$this->output->message('请选择办案费收款方','warning');
				}
				
				if(!$misc_fee['fee']){
					$this->output->message('请填写办案费约定金额（数值）','warning');
				}
				
				if(!$misc_fee['pay_date']){
					$this->output->message('请填写收费时间','warning');
				}
				
				if(count($this->output->message['warning'])>0){
					throw new Exception();
				}
				
				if($this->project->addFee($this->project->id,$misc_fee['fee'],$misc_fee['pay_date'],'办案费',NULL,$misc_fee['receiver'],$misc_fee['comment'])){
					$this->output->setData($this->miscfeeList(),'content-table','html','.item[name="miscfee"]>.contentTable','replace');
				}else{
					$this->output->message('收费添加错误', 'warning');
				}
				unset($_SESSION[CONTROLLER]['post'][$this->project->id]['case_fee_misc']);
			}
			
			elseif($submit=='document'){
				$this->load->model('document_model','document');
				
				$document=$this->input->sessionPost('document');
				
				$document_labels=$this->input->sessionPost('document_labels');
				
				if(!$document['id']){
					$this->output->message('请选择要上传的文件', 'warning');
					throw new Exception;
				}
				
				$this->document->update($id, $document);
				
				$this->document->updateLabels($document['id'],$document_labels);
				
				$this->project->addDocument($this->project->id, $document['id']);
				
				$this->output->setData($this->documentList(),'content-table','html','.item[name="document"]>.contentTable','replace');
				
				unset($_SESSION[CONTROLLER]['post'][$this->project->id]['document']);
			}
			
			elseif($submit=='remove_document'){
				if($this->project->removeDocument($this->project->id,$button_id)){
					$this->output->setData($this->documentList(),'content-table','html','.item[name="document"]>.contentTable','replace');
				}
			}
			
			elseif($submit=='new_case'){
				$this->project->removeLabel($this->project->id, '已归档');
				$this->project->removeLabel($this->project->id, '咨询');
				$this->project->addLabel($this->project->id, '等待立案审核');
				$this->project->addLabel($this->project->id, '案件');
				$this->project->update($this->project->id,array(
					'num'=>NULL,
					'time_contract'=>$this->date->today,
					'time_end'=>date('Y-m-d',$this->date->now+100*86400)
				));
				
				$this->output->message('已立案，请立即获得案号');
				
				$this->output->status='refresh';
			}
			
			if(is_null($this->output->status)){
				$this->output->status='success';
			}

		}catch(Exception $e){
			$e->getMessage() && $this->output->message($e->getMessage(), 'warning');
			$this->output->status='fail';
		}
	}
	
	function removePeopleRole($project_id,$people_id){
		
		$this->project->id=$project_id;
		
		$role=$this->input->post('role');
		
		$this->project->removePeopleRole($project_id,$people_id,$role);
		$this->output->setData($this->staffList(),'content-table','html','.item[name="staff"]>.contentTable','replace');
	}
	
	function match(){

		$term=$this->input->post('term');

		$result=$this->project->getList(array('people'=>$this->user->id));//只匹配到当前用户参与的案件

		$array=array();

		foreach ($result as $row){
			if(strpos($row['case_name'], $term)!==false){
				$array[]=array(
					'label'=>strip_tags($row['case_name']).' - '.$row['num'],
					'value'=>$row['id']
				);
			}
		}
		
		$this->output->data=$array;
	}
}
?>