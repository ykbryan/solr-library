<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Search extends CI_Controller {

	function __construct()
	{
		parent::__construct();
		$this->load->library('search_library');
	}

	function index(){
		if($this->input->post()){
			$this->load->library('search_library');
			$result = $this->search_library->search($this->input->post('keyword'));
		}

	}

}