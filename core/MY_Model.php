<?php

class MY_Model extends CI_Model
{

	protected $_table_name = false;
	protected $primaryKey  = 'id';
	//Associations
	protected $belongsTo        = array();
	protected $hasMany          = array();
	// protected $hasBelongsToMany = array();


	//Callback
	protected $beforeSave   = array();
	protected $beforeFind     = array();
	protected $beforeDelete   = array();
	protected $afterSave      = array();
	protected $afterFind      = array();
	protected $afterDelete    = array();

	
	public function __construct(){
		parent::__construct();
		$this->load->helper('Inflector');
	}

	/*==============  FIND DATA ==============*/


	/**
	*   Retrieve your data
	*	@param string $type Different request type :  All || First || List || Count || CountWhere
	*	@param array $options Different options for filter your result : Fields || Conditions || groupBy || Limit || Order || Contain
	**/
	public function find( $type, $options = array() ){

		$result = false;

		$this->callback('beforeFind');

		if($type == 'All' || $type == 'all' || $type == 'List' || $type == 'list'){

			if(isset($options['fields'])){
				$this->db->select($options['fields']);
			}
			if(isset($options['conditions'])){
				$this->db->where($options['conditions']);
			}
			if(isset($options['groupBy'])){
				$this->db->group_by($options['groupBy']);
			}
			if(isset($options['limit'])){
				$this->db->limit(implode(' ',$options['limit']));
			}
			if(isset($options['order'])){
				$this->db->order_by(implode(' ',$options['order']));
			}

			$this->db->from($this->_table_name);
			$result = $this->db->get()->result_array();


			if($type == 'List' || $type == 'list' ){

				$list = array();
				foreach ($result as $k => $v) {
					if (isset($options['displayField'])) {
						$list[$v['id']] = $v[$options['displayField'][0]];
					}else{
						$list[$v['id']] = $v['name'];
					}
				}
				$result = $list;
			}

		}elseif ($type == 'First' || $type == 'first') {

			if(isset($options['fields'])){
				$this->db->select($options['fields']);
			}
			if(isset($options['conditions'])){
				$this->db->where($options['conditions']);
			}
			if(isset($options['order'])){
				$this->db->order_by(implode(' ',$options['order']));
			}
			$this->db->limit(1);
			$this->db->from($this->_table_name);
			$result = $this->db->get()->row_array();


		}elseif ($type == 'Count' || $type == 'count') {


			$result = $this->db->count_all($this->_table_name);
			

		}elseif ($type == 'CountWhere' || $type == 'countWhere') {


			if(isset($options['conditions'])){
				$this->db->where($options['conditions']);
			}
			$this->db->from($this->_table_name);
			$result = $this->db->count_all_results();
		}
		$contain = isset($options['contain']) ? $options['contain'] : array();


		$result_after = $this->callback('afterFind',$result);
		$result = $this->getRelationships($result_after,$contain, $type);

		return $result;

	}

	/**
	* Get the related Model data
	* @param array $data 
	*/
	public function getRelationships($data, $contain=array(),$type){
		$hasMany   = $this->hasMany;
		$belongsTo = $this->belongsTo;
		
		if($type == 'first' || $type == 'First'){
			$data    = !empty($data[0]) ? $data : array($data);
		}

		//Insert "HasMany" data in data result
		if(!empty($this->hasMany)){
			foreach ($data as $k => $v) {
				foreach ($hasMany as $kk => $vv) {

					$contain_model = is_array($vv) && isset($vv['classname']) ? $vv['classname'] : !is_int($kk) ? $kk : $vv;
					//Get data from Model defined in Contain option
					if(!empty($contain) && is_array($contain) && in_array($contain_model, $contain)){

						$foreign_Key = false;
						$model 		 = false;
						
						//Get foreignKey
						if(is_array($vv) && isset($vv['foreignKey'])){
							$foreign_key = $vv['foreignKey'];
						}else{
							if(!is_int($kk)){
								$foreign_key = $v[strtolower($kk) . '_id'];
							}else{
								$foreign_key =$v[strtolower($vv) . '_id'];
							}
						}
						//Get classname
						if(is_array($vv) && isset($vv['classname'])){
							$model = $vv['classname'];
						}elseif(!is_int($kk)){
							$model = $kk;
						}else{
							$model = $vv;
						}

						$this->load->model($model);

						$data[$k][$model] = $this->{$model}->find('all',array(
							"fields"=> isset($vv['fields']) ? $vv['fields'] : array('*'),
							"conditions"=>array($foreign_key => $v['id'])
						));
					}
				}
			}
		}
		//Insert "Belongsto" data in data result
		if(!empty($this->belongsTo)){
			foreach ($data as $k => $v) {
				foreach ($belongsTo as $kk => $vv) {

					$contain_model = is_array($vv) ? $vv['classname'] : $vv;
					//Get data from Model defined in Contain option
					if(!empty($contain) && is_array($contain) && in_array($contain_model, $contain)){
						if(empty($vv['classname'])){
							$vv = ucfirst(singular($vv));
						}

						$foreign_key = false;
						$model 		 = false;

						if( is_array($vv) && isset($vv['foreignKey']) ){
							$foreign_key = $v[$vv['foreignKey']];
						}elseif (!is_int($kk)) {
							$foreign_key = $v[strtolower($kk) . '_id'];
						}else{
							$foreign_key = $v[strtolower($vv) . '_id'];
						}

						if( is_array($vv) && isset($vv['classname']) ){
							$model =  $vv['classname'];
						}elseif( !is_int($kk) ){
							$model = $kk;
						}else{
							$model = $vv;
						}

						$this->load->model($model);
						
						$data[$k][!is_int($kk) ? $kk : $vv] = $this->$model->find('first',array(
							"fields"=> is_array($vv) && isset($vv['fields']) ? $vv['fields'] : array('*'),
							"conditions"=>array($this->primaryKey => $foreign_key)
						));
					}
				}
			}
		}
		$data = $type != "first" ? $data : $data[0];
		return $data;
	}


	/**
	* Get the last recording data or 
	* The last_insert data or
	* The data belongs to the ID
	* @param int $id
	*/
	public function read($id=null){
		$data = array();
		if($id != null && is_int($id)){
			$data = $this->find('first',array(
				'conditions'=>array('id'=>$id)
			));
		}else{
			if($this->db->insert_id() != 0){
				$data = $this->find('first',array(
					'conditions'=>array('id'=>$this->db->insert_id())
				));
			}else{
				$data = $this->find('first',array(
					'order'=>array('id','DESC')
				));	
			}
		}
		return $data;
	}



	/*==============  SAVE DATA ==============*/

	/**
	* Save the data if id is empty, else Update
	* @param array $data
	*/
	public function save( $data ){

		//INSERT
		if(empty($data[$this->primaryKey])){

			$data = $this->callback('beforeSave', $data);

			$this->db->set($data);
			$this->db->insert($this->_table_name);
		}else{
		//UPDATE
			$data = $this->callback('beforeSave', $data);

			$id = intval($data[$this->primaryKey]);
			$this->db->set($data);
			$this->db->where($this->primaryKey, $id);
			$this->db->update($this->_table_name);
		}

		$this->callback('afterSave', $this->read());

		return true;
	}
	/**
	* Allow to save a field 
	* @param array $key
	* @param array $value
	* @param array $id id of the table
	*/
	public function saveField( $key, $value, $id ){
		$value = $this->callback('beforeSave', $value);
		$this->db->where($this->primaryKey, $id);
		$this->db->set($key, $value);
		$this->db->update($this->_table_name);
		$this->callback('afterSave', $this->read());
		return true;
	}

	/**
	* Allow to save all data matching to a field
	* @param array $data
	* @param array $options Conditions
	*/
	public function saveAll( $data, $where ){
		$data = $this->callback('beforeSave', $data);
		$this->db->where($where);
		$this->db->update($this->_table_name);
		$this->callback('afterSave', $this->read());

		return true;
	}




	/*==============  DELETE DATA ==============*/

	/**
	* Allow to delete a data
	* @param int $id
	*/
	public function delete( $id ){

		$this->callback('beforeDelete', $id);

		$id = intval($id);
		if(!$id){
			return false;
		}
		$this->db->where($this->primaryKey, $id);
		$this->db->limit(1);
		$this->db->delete($this->_table_name);

		$this->callback('beforeDelete', $id);

		return true;
	}
	/**
	* Allow to delete a data matching to a field
	* @param array $data
	* @param array $where conditions
	*/
	public function deleteAll( $options=array() ){
		
		foreach ($options['conditions'] as $k => $v) {
			$this->db->where($k, $v);
		}
		$this->db->delete($this->_table_name);

		return true;
	}



	/*==============  USER LOGIN ==============*/


	public function login(){
		$user = $this->find('first',array(
			"conditions"=>array(
				'username'=>$this->input->post('username'),
				"password"=>$this->hash($this->input->post('password'))
			)
		));

		if(count($user)){
			$data = array(
				'id'=> $user['id'],
				"name" => $user['username'],
				"email" => $user['email'],
				"level" => $user['level'],
				'islogged'=> TRUE
			);
			$this->session->set_userdata($data);

			return true;
		}
	}

	public function logout(){
		return $this->session->sess_destroy();
	}

	public function islogged(){
		return (bool) $this->session->userdata('islogged');
	}

	public function hash( $string ){
		return hash('sha512', $string . config_item("encryption_key"));
	}



	/**
     * callback an event and call its observers.
     */
    public function callback($function, $data = false, $last = TRUE)
    {	
    	$result = '';
        if (isset($this->$function) && is_array($this->$function)){

            foreach ($this->$function as $method)
            {
                $data = call_user_func_array(array($this, $method), array($data, $last));
            }
        }
        return $data;
    }
}