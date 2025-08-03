<?php namespace App\Models;

use CodeIgniter\Model;

class MyModel extends Model
{
    public function __construct($table)
	{
        parent::__construct();
		$db = \Config\Database::connect();
		$this->builder = $db->table($table);
    }

	public function getAllData($order="", $asc="")
	{
		if($order!="") $this->builder->orderBy($order, $asc);
		return $this->builder->get()->getResult();
	}

	public function getAllDataById($where, $orders=[])
	{
		if($where) {
			foreach($where as $key => $value){
				$this->builder->where($key, $value);
			}
		}
		if (!empty($orders)) {
			foreach ($orders as $order => $sort) {
				$this->builder->orderBy($order, $sort);
			}
		}
		return $this->builder->get()->getResult();
	}

	public function getDistinct($col)
	{
		$this->builder->select($col)->distinct();
		return $this->builder->get()->getResult();
	}

	public function getDataById($where, $id)
	{
		$this->builder->where($where, $id);
		return $this->builder->get()->getRow();
	}

	public function getMaxId($where)
	{
		$this->builder->selectMax($where, 'idmax');
		return $this->builder->get()->getRow();
	}
	
	public function getCountAll($where, $id)
	{
		$this->builder->where($where, $id);
		return $this->builder->countAllResults();
	}

	public function checkPassword($password)
	{
		$username = session()->get('username');

		$this->builder->select('password');
		$this->builder->where('username', $username);
		$get = $this->builder->get()->getRow();
		$hash = $get->password;
		$verify_pass = password_verify($password, $hash);
		return $verify_pass;
	}
    
    public function insertData($data)
    {
        $this->db->transBegin();
		$this->builder->insert($data);
		if ($this->db->transStatus() === FALSE){
			$this->db->transRollback();
			return false;
		}
		else{
			$this->db->transCommit();
			return true;
		}
	}

	public function insertDataBatch($data)
    {
        $this->db->transBegin();
		$this->builder->insertBatch($data);
		if ($this->db->transStatus() === FALSE){
			$this->db->transRollback();
			return false;
		}
		else{
			$this->db->transCommit();
			return true;
		}
	}
	
	function updateData($data, $where="", $id="")
	{
		$this->db->transBegin();
		if($where!="")
			$this->builder->where($where, $id);
		
		$this->builder->update($data);
		if ($this->db->transStatus() === FALSE){
			$this->db->transRollback();
			return false;
		}
		else{
			$this->db->transCommit();
			return true;
		}
	}

	function deleteData($where, $id)
	{
		$this->db->transBegin();
		$this->builder->where($where, $id);
		$this->builder->delete();
		if ($this->db->transStatus() === FALSE){
			$this->db->transRollback();
			return false;
		}
		else{
			$this->db->transCommit();
			return true;
		}
	}
}