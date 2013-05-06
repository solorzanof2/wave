<?php

namespace Wave;

use Wave\Http\Exception\ForbiddenException;
use Wave\Http\Exception\NotFoundException;
use Wave\Http\Exception\UnauthorizedException;
use Wave\Router\Action;
use Wave\Utils\JSON;
use Wave\Utils\XML;
use Wave\Http\Request;
use Wave\Http\Response;

class Controller {

    /** @var \Wave\Http\Response */
    public $_response;

    /** @var \Wave\Http\Request */
    protected $_request;
	
	protected $_response_method;
	
	protected $_data;
    protected $_cleaned = array();
	protected $_action;
	protected $_input_errors;

	protected $_is_post = false;
	protected $_is_get = false;

    protected $_status;
    protected $_message;


    /**
     * @param Action   $action
     * @param Request  $request
     * @param Response $response
     * @param array    $data
     *
     * @return Http\Response
     * @throws Http\Exception\UnauthorizedException
     * @throws Http\Exception\NotFoundException
     * @throws Exception
     * @throws Http\Exception\ForbiddenException
     */
    public static final function invoke(Action $action, Request $request, Response $response, $data = array()){
		
		list($controller_class, $action_method) = explode('.', $action->getAction(), 2) + array(null, null);
        if(!isset($action_method))
            $action_method = Config::get('wave')->controller->default_method;

		if(class_exists($controller_class, true) && method_exists($controller_class, $action_method)){

            /** @var \Wave\Controller $controller */
			$controller = new $controller_class();

            $controller->_action = $action;
            $controller->_request = $request;
            $controller->_response = $response;
            $controller->_response_method = $response->getFormat();

            switch($controller->_request->getMethod()){
                case Request::METHOD_GET:
                    $controller->_is_get = true;
                    break;
                case Request::METHOD_POST:
                    $controller->_is_post = true;
                    break;
            }
            $data = array_replace($controller->_request->getData(), $data);

            $controller->_data = $data;
            $controller->init();

            if(!$action->canRespondWith($response->getFormat())){
                throw new NotFoundException(
                    'The requested action ' . $action->getAction().
                    ' can not respond with ' . $response->getFormat() .
                    '. (Accepts: '.implode(', ', $action->getRespondsWith()).')', $request, $response);
            }
            else if(!$action->checkRequiredLevel($request)){

                $auth_obj = Auth::getIdentity();
                $auth_class = Auth::getHandlerClass();

                if(!in_array('Wave\IAuthable', class_implements($auth_class)))
                    throw new Exception('A valid Wave\IAuthable class is required to use RequiresLevel annotations', Response::STATUS_SERVER_ERROR);
                else if(!$auth_obj instanceof IAuthable)
                    throw new UnauthorizedException('You are not authorized to view this resource');
                else
                    throw new ForbiddenException('The current user does not have the required level to access this page');
            }
            else if($action->needsValidation() && !$controller->inputValid($action->getValidationSchema())){
                return $controller->request();
            }


            return $controller->{$action_method}();

		}
		else
            throw new Exception('Could not invoke action '.$action->getAction().'. Method '.$controller_class.'::'.$action_method.'() does not exist', Response::STATUS_SERVER_ERROR);
		
	}
	
    /**
     * Use the Wave Validator to check form input. If errors exist, the offending
     * values are inserted into $this->_input_errors.
     * 
     * @param		$schema		-		The validation schema for the Jade Validator
     * @param		$data		-		[optional] Supply a data array to use for validation
     * @return		Boolean true for no errors, or false.
     */
    protected function inputValid($schema, $data = null) {

        if ($data === null)
            $data = $this->_data;

        if($output = Validator::validate($schema, $data)){
            $this->_cleaned = $output;
            return true;
        }

        $this->_input_errors = Validator::$last_errors;
		return false;
    }

	public function _setResponseMethod($method){
		$this->_response_method = $method;
	}

	public function _getResponseMethod(){
		return $this->_response_method;
	}
	
	
	final public function __construct(){
			
		$this->_post =& $_POST;
		$this->_get =& $_GET;
		
		$this->_identity = \Wave\Auth::getIdentity();
	
	}
	
	public function init() {}
	
	protected function _buildPayload($status, $message = '', $payload = null){
		if($payload === null)
			$payload = $this->_getResponseProperties();
		
		return array(
			'status' => $status,
			'message' => $message,
			'payload' => $payload
		);
	}
	
	protected function _buildDataSet(){
		$this->_setTemplatingGlobals();
		$properties = $this->_getResponseProperties();
		return array_merge($properties);
	}
	
	protected function _getResponseProperties(){
		$arr = array();
		foreach ($this as $key => $val) {
            if ($key[0] === '_')
                continue;
            $arr[$key] = $val;
        }
        return $arr;
	}
	
	protected function _setTemplatingGlobals(){
		View::registerGlobal('input', isset($this->_sanitized) ? $this->_sanitized : $this->_data);
		View::registerGlobal('errors', isset($this->_input_errors) ? $this->_input_errors : array());
		View::registerGlobal('_identity', $this->_identity);
	}
	
	final protected function respond(){
		return $this->_invoke('respond');
	}
	
	final protected function request(){
		return $this->_invoke('request');
	}
	
	final private function _invoke($type){
		$response_method = $type.strtoupper($this->_response_method);
		if(method_exists($this, $response_method) && $response_method !== $type)
			return $this->{$response_method}();
		else
			throw new Exception(
				'The action "'.$this->_action.'" tried to respond with "'.
				$this->_response_method.'" but the method does not exist'
			);
	}

	protected function respondHTML(){
		if(!isset($this->_template))
			throw new Exception('Template not set for '.$this->_response_method.' in action '.$this->_action->getAction());

        //header('X-Wave-Response: html');
		//header('Content-type: text/html; charset=utf-8');
        $content = View::getInstance()->render($this->_template, $this->_buildDataSet());
        $this->_response->setContent($content);

        return $this->_response;
	}
	
	protected function requestHTML(){
		if(isset($this->_request_template))
			$this->_template = $this->_request_template;
		return $this->respondHTML();
	}
	
	protected function respondDialog(){
		$this->_template .= '-dialog';
		
		$html = View::getInstance()->render($this->_template, $this->_buildDataSet());
		return $this->respondJSON(array('html' => $html));
	}
	
	protected function requestDialog(){
		if(isset($this->_request_template))
			$this->_template = $this->_request_template;
		return $this->respondDialog();
	}
	
	protected function respondJSON($payload = null){
		if(!isset($this->_status)) $this->_status = Response::STATUS_OK;
		if(!isset($this->_message)) $this->_message = Response::getMessageForCode($this->_status);

        // @todo This should be extracted into a response object of some sort.
        // added in so json can be returned as text/plain if something needs it (js uploader in this case)
        //$content_type = 'application/json';
        //if(isset($_SERVER['HTTP_ACCEPT'])){
        //    $accepts = explode(',', $_SERVER['HTTP_ACCEPT']);
        //    foreach($accepts as $accept){
        //        if(in_array($accept, array('application/json', 'text/plain'))){
        //            $content_type = $accept;
        //        }
        //    }
        //}

        //header('X-Wave-Response: json');
		//header('Cache-Control: no-cache, must-revalidate');
        //header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        //header('Content-type: ' . $content_type);

        $this->_response->setStatusCode($this->_status);
        $this->_response->setContent($this->_buildPayload($this->_status, $this->_message, $payload));

        return $this->_response;
	}
	
	protected function requestJSON(){
		if(!isset($this->_status)) $this->_status = Response::STATUS_BAD_REQUEST;
		if(!isset($this->_message)) $this->_message = 'Invalid request or parameters';
        $payload = array('errors' => isset($this->_input_errors) ? $this->_input_errors : array());
		return $this->respondJSON($payload);
	}
	
	protected function respondXML(){
		if(!isset($this->_status)) $this->_status = Response::STATUS_OK;
		if(!isset($this->_message)) $this->_message = Response::getMessageForCode($this->_status);
        //header('X-Wave-Response: xml');
		//header('Cache-Control: no-cache, must-revalidate');
        //header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		//header("content-type: text/xml; charset=utf-8");

        $this->_response->setStatusCode($this->_status);
        $this->_response->setContent($this->_buildPayload($this->_status, $this->_message));

		return $this->_response;
	}
	
	protected function requestXML(){
		if(!isset($this->_status)) $this->_status = Response::STATUS_BAD_REQUEST;
		if(!isset($this->_message)) $this->_message = Response::getMessageForCode($this->_status);
		return $this->respondXML();
	}

}


?>