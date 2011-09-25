<?php

class Zend_Controller_Restful extends Zend_Controller_Action
{
	
	public function init()
	{
		$this->getHelper('layout')->disableLayout();
		$this->getHelper('viewRenderer')->setNoRender(true);
		$this->setHeaders();
	}
	
	public function setError($message)
	{
		$response = array('error' => true, 'message' => $message);
		// send the error response
		$this->getResponse()->setBody($this->export($response));
	}
	
	public function getContentType()
	{
		// default format is JSON. can be also XML or PHP (serialized array) if specified in the request
		$format = $this->_getParam('format', 'json');
		
		switch($format)
		{
			case 'xml':
				return 'text/xml';
			case 'php':
				return 'text/php';
			case 'json':
			default:
				return 'text/json';
		}
	}
	
	public function setHeaders()
	{
		// $this->getResponse()->setHeader('Content-Type: ' . $this->getContentType());
	}
	
	public function postDispatch()
	{
		if( ! isset($this->data) OR ! is_array($this->data))
		{
			// send error response if no data has been returned by controller
			$this->setError('There is no response for your request.');
		}
		else 
		{
			// send the response in requested format (xml / json)
			$this->getResponse()->setBody($this->export($this->data));
		}
	}
	
	public function export(array $data)
	{
		// exports the data in format specified by content type header
		$contentType = explode('/', $this->getContentType());
		$type = ucfirst(end($contentType));
		
		$action = 'export' . $type;
		
		$callback = $this->_getParam('callback', null);
		if( ! $callback)
			return $this->$action($data);
		else  // add callback for jsonp requests
			return $callback . '(' . $this->$action($data) . ');';
	}
	
	public function exportJson(array $data)
	{
		// return data in json format. you should use this format
		return json_encode($data);
	}
	
	public function  exportPhp(array $data)
	{
		return serialize($data);
	}
	
	public function exportXml(array $data, $rootNodeName = 'Response', $xml = null)
	{ // export data in xml format. can be used by some idiots making the request from Java (bleahh) application
		if ($xml == null)
		{
			$xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><$rootNodeName />");
			// turn off compatibility mode as simple xml throws a wobbly if you don't.
			if (ini_get('zend.ze1_compatibility_mode') == 1)
			{
				ini_set ('zend.ze1_compatibility_mode', 0);
			}
		}
		 
		// loop through the data passed in.
		foreach($data as $key => $value)
        {
            // if numeric key, assume array of rootNodeName elements
            if (is_numeric($key))
            {
                $key = $rootNodeName;
            }

            // delete any char not allowed in XML element names
            $key = preg_replace('/[^a-z0-9\-\_\.\:]/i', '', $key);

            // if there is another array found recrusively call this function
            if (is_array($value))
            {
                // create a new node unless this is an array of elements
                $node = $this->isAssoc($value) ? $xml->addChild($key) : $xml;

                // recrusive call - pass $key as the new rootNodeName
                $this->exportXml($value, $key, $node);
            }
            else
            {
                // add single node.
                $value = htmlentities($value);
                $xml->addChild($key,$value);
            }

        }
        // pass back as string. or simple xml object if you want!
        return $xml->asXML();
    }

    // determine if a variable is an associative array
    public function isAssoc( $array ) {
        return (is_array($array) && 0 !== count(array_diff_key($array, array_keys(array_keys($array)))));
    }
}
