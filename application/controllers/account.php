<?php
class Account extends SS_controller{
	function __construct(){
		$this->default_method='lists';
		parent::__construct();
		$this->load->model('achievement_model','achievement');
	}
	
	function lists(){
		$this->session->set_userdata('last_list_action', $this->input->server('REQUEST_URI'));
		
		$field=array(
			'date'=>array('title'=>'日期','td'=>'href="#account/edit/{id}"'),
			'name'=>array('title'=>'名目'),
			'_type'=>array('title'=>'方向','eval'=>true,'content'=>"
				if({amount}>0){
					return '<span style=\"color:#0F0\"><<</span>';
				}else{
					return '<span style=\"color:#F00\">>></span>';
				}
			",'td_title'=>'width="55px"','td'=>'style="text-align:center"'),
			'amount'=>array('title'=>'金额'),
			'client_name'=>array('title'=>'付款/收款人')
		);
		
		$list=$this->table->setFields($field)
				->setData($this->account->getList())
				->generate();
		$this->load->addViewData('list', $list);
		
		$this->load->view_data['account_sum']=array(
			'_heading'=>array('总创收'),
			array($this->achievement->sum('collected','total',option('date_range/from_timestamp'),option('date_range/to_timestamp'),false))
		);
		
		$this->load->view('list');
	}

	function add(){
		$data=array();
		
		if($this->input->get('case')){
			$data['case']=intval($this->input->get('case'));
		}
		if($this->input->get('client')){
			$data['people']=intval($this->input->get('client'));
		}

		$this->account->id=$this->account->add($data);
		$this->output->status='redirect';
		$this->output->data='account/edit/'.$this->account->id;
	}

	function edit($id){
		$this->account->id=$id;
		
		$this->load->model('client_model','client');
		$this->load->model('cases_model','cases');
		
		$account=$this->account->fetch($this->account->id);
		
		if(isset($account['name'])){
			$tab_title=$account['name'];
		}
		//TODO 测试一下db类对于数据库中的NULL返回的是NULL还是undefined
		if(isset($account['people'])){
			$client=$this->client->fetch($account['people']);
			
			if(isset($client['abbreviation'])){
				$tab_title=$client['abbreviation'].' '.$tab_title;
			}else{//@PBUG people.name为null时，会报错
				$tab_title=$client['name'].' '.$tab_title;
			}

			//根据客户ID获得收费array
			$case_fee_array=$this->cases->getFeeListByClient($account['people']);
		}else{
			$tab_title='未命名流水';
		}

		$this->output->setData($tab_title,'name');

		if(isset($account['case'])){
			//根据案件ID获得收费array
			$case_fee_array=$this->cases->getFeeOptions($account['case']);
		}
		
		$this->load->addViewArrayData(compact('account','client','case_fee_array','case_client_array'));
		$this->load->view('account/edit');
		
	}
	
	function submit($submit,$id){
		$this->account->id=$id;

		$account=array_merge($this->account->fetch($id),(array)post('account'))+(array)$this->input->post('account');
		
		try{
			
			if($submit=='cancel'){
				unset($_SESSION[CONTROLLER]['post'][$this->account->id]);
				//$this->account->clearUserTrash();
			}
			
			if($submit=='account'){
				$this->load->model('cases_model','cases');
				
				if(!$account['name']){
					$this->output->message('请填写摘要','warning');
					throw new Exception;
				}

				if(!$account['date']){
					$this->output->message('请填写日期','warning');
					throw new Exception;
				}
				
				if(!$account['people']){
					$this->output->message('请输入关联客户','warning');
					throw new Exception;
				}

				if($account['way']=='out'){
					post('account/amount',-abs($account['amount']));
				}
				//根据way设置amount的正负号

				post('account/case',$this->cases->getIdByCaseFee($account['case_fee']));
				//根据提交的case_fee先找出case.id

				post('account',array_trim(post('account')));//imperfect 2012/5/25 uicestone 为了让没有case_fee 和case的account能够保存

				$this->account->update($this->account->id,post('account'));
			}
			
			$this->output->status='success';
			
		}catch(Exception $e){
			$this->output->status='fail';
		}
	}
}
?>