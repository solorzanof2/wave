<?php



class Wave_Validator_Datatype_Int extends Wave_Validator_Datatype {


	public function validate(){
		$parentCheck = parent::validate();
		if($parentCheck !== true) return $parentCheck;

		if (is_array($this->input) || (!is_string($this->input) && !is_int($this->input) && !is_float($this->input))) 
            return Wave_Validator::ERROR_INVALID;
        
        
        if (is_int($this->input) || strval(intval($this->input)) == $this->input) 
            return Wave_Validator::INPUT_VALID;
        else
        	return Wave_Validator::ERROR_INVALID;

	}
	
	public function sanitize(){
		return $this->sanitized_value == null ? intval($this->input) : $this->sanitized_value;
	}

}


?>