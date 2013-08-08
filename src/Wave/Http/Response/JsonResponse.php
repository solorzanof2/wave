<?php

namespace Wave\Http\Response;

use Wave\Http\Request;
use Wave\Http\Response;
use Wave\Utils\JSON;

class JsonResponse extends Response {

    private static $acceptable_types = array(
        'application/json',
        'text/javascript',
        'text/plain',
    );

    private static $default_type = 'application/json';

    /**
     * The json data for this response
     * @var mixed $data
     */
    protected $data;


    public function prepare(Request $request){
        parent::prepare($request);

        $content_types = array_map('trim', explode(',', $request->headers->get('accept', static::$default_type, true)));
        $allowed = array_intersect($content_types, static::$acceptable_types);
        $content_type = empty($allowed) ? static::$default_type : array_pop($allowed);

        $this->headers->set('Content-Type', $content_type);
        $this->headers->set('Cache-Control', 'no-cache, must-revalidate');
        $this->headers->set('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        $this->headers->set('X-Wave-Response', 'json');

        return $this;
    }

    public function setContent($data, $convert = true){
        if($convert){
            $this->data = $data;
            $data = JSON::encode($data);
        }

        parent::setContent($data);
    }

    /**
     * @return mixed
     */
    public function getData() {
        return $this->data;
    }

}
