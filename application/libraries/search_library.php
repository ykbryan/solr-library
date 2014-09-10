<?php
require_once(APPPATH.'libraries/SolrPHPClient/Service.php');
class Search_library {
	private $CI, $solrconfig; 

	public function __construct() {
		$this->CI = & get_instance();
		$this->CI->load->config('solr');
		$this->solrconfig = $this->CI->config->item('solr');
		
		$this->solr = new Apache_Solr_Service($this->solrconfig['host'], $this->solrconfig['port'], '/solr');		
		if(!$this->solr->ping()){
			return false;
		}
	}
	
	/*** SOLR functions ***/
	
	function addDocumentByTable($tablename, $table_id, $data)
	{
		$document = new Apache_Solr_Document();
		$existing_document = $this->getDocumentById($tablename, $table_id);
		
		if(!$existing_document)
			$document->id = uniqid(); #this is a new document
		else
			$document->id = $existing_document->id; #this document exists
		
		#add your field and values 
		if(isset($data->name))
			$document->name = $data->name;
		if(isset($data->content))
			$document->description = $data->content;
		if(isset($data->category))
			$document->category = $data->category;
		if(isset($data->keywords))
			$document->keywords = $data->keywords;
		if(isset($data->author))
			$document->author = $data->author;
		if(isset($data->data))
			$document->data = $data->data;
		
		//update solr server
		$this->solr->addDocument($document);
		$this->commit();
		//$this->optimize();
		
	}
	
	function getDocumentById($tablename, $table_id)
	{
		$result = $this->solr->search(array('+tableid:'.$table_id.' +table:'.$tablename),0,1);
		if(!$result->response->numFound)
			return false; //there is nothing to clear
			
		return $result->response->docs[0];
	}
	
	function deleteDocumentById($tablename, $table_id)
	{
		$solr_document = $this->getDocumentById($tablename, $table_id);
		if(!$solr_document)
			return false; //no such data

		$solr_id = $solr_document->id;
		$delete_result = $this->solr->deleteById($solr_id);
		if(!$delete_result)
			return false; //delete failed
			
		//update solr server
		$this->commit();
		//$this->optimize();
		
		return true;
	}
	
	function count()
	{
		if($count == false || !isset($count)){
			$count = $this->solr->search(array('*:*'),0,0)->response->numFound;
		}
		return $count;
	}
	
	function clear()
	{
		//getAll
		$offset = 0;
		$limit = $this->count();
		$result = $this->solr->search(array('*:*'),$offset,$limit);
		if($result->response->numFound){
			//putIdsInArray
			$ids = array();
			foreach ($result->response->docs as $data){
				array_push($ids, $data->id);
			}

			//deleteByMultipleIds
			$deleteResult = $this->solr->deleteByMultipleIds($ids);
			if(!$deleteResult)
				return false;

			//update solr server
			$this->commit();
			$this->optimize();
		}
		
		return true;
	}
	
	function commit()
	{
		return $this->solr->commit()->getHttpStatus();
	}
	
	function optimize()
	{
		return $this->solr->optimize()->getHttpStatus();
	}

	/** SEARCHING FOR DOCUMENT **/
	
	function search($text, $offset = 0, $limit = 0)
	{	
		//continue
		$params = array();
		$suggestion = $text;
		$text = '*' . $text . '*';
		$result = $this->solr->search(array('(keywords:'.$text.'^1 OR content:'.$text.'^2 OR name:'.$text. '^3) AND table:stores'),$offset, $limit);
		if(!$result->response->numFound){
			#if spellcheck is configured
			$params['spellcheck'] = 'true';
			$params['spellcheck.q'] = $suggestion;
		}else{ 
			#by default, solr returns 10 rows only
			$limit = $result->response->numFound;
		}
		
		$result = $this->solr->search(array('(keywords:'.$text.'^1 OR content:'.$text.'^2 OR name:'.$text. '^3) AND table:stores'),$offset, $limit, $params);
		if($result->response->numFound)
			return $result->response->docs;
		
		#if no documents is found, return the spellcheck result
		return $result->spellcheck;
	}
	
	function getByAuthor($author, $offset = 0, $limit = 0)
	{
		$query = array('author:"'.$author.'"');
		$result = $this->solr->search($query,$offset, $limit);
		if(!$result->response->numFound)
			return false; //there is nothing to grab
		
		//if $limit is 0, we should return all results
		if($result->response->numFound > 10 || !$limit){ //by default, solr returns 10 rows only
			if($result->response->numFound > 1000)
				$limit = 1000; #if required, limit the number of results
			else
				$limit = $result->response->numFound;
		}
		
		$result = $this->solr->search($query,$offset, $limit);
			
		return $result->response->docs;
	}

}
