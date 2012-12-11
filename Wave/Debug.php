<?php

namespace Wave;

class Debug {

	private $queries = array();
	private $used_files = array();
	private $execution_start;
	
	private static $instance = null;

	public static function getInstance(){
		if(self::$instance === null)
			self::$instance = new self();
			
		return self::$instance;
	}
	
	public function __construct(){
		$this->execution_start = microtime(true);
		if(isset($_REQUEST['_dev|debug_icons'])){
			$this->showIcons();
		}
		else if(isset($_REQUEST['_dev|debug_css'])){
			$this->showCSS();
		}
	}

	public function getMemoryUsage(){
		return round(memory_get_usage()/1000, 0);
	}

	/**
	 * Returns the time in miliseconds since the initation of the object. Used to track program execution time.
	 * @return int
	 */
	public function getExecutionTime(){
		return round((microtime(true) - $this->execution_start)*1000, 0);
	}

	/**
	 * Adds the details of a used file in to an array
	 * @return
	 * @param object $filename
	 * @param object $caller[optional]
	 */
	public function addUsedFile($filename, $caller = null){
		$this->used_files[] = array('filename' => $filename, 'caller' => $caller);
	}

	/**
	 * Returns all files used in the process
	 * @return
	 */
	public function getUsedFiles(){
		$out = array();
		foreach(get_included_files() as $i => $file){
			$out[] = array('number' => $i+1, 'filename' => str_replace(SYS_ROOT, '', $file));
		}
		return $out;
	}

	public function addQuery($time, $statement){
	
		$sql = $statement->queryString;
		$rows = $statement->rowCount();
		$success = $statement->errorCode() == \PDO::ERR_NONE ? true : false;
		$time = round($time * 1000, true);

		$sql = str_replace(chr(0x0A), ' ', $sql);
		$sql = str_replace('  ', ' ', $sql);

		$this->queries[] = array('success' => $success, 'time' => $time, 'sql' => $sql, 'rows' => $rows);
	}


	public function getNumberOfFiles(){
		return count(get_included_files());
	}


	public function getNumberOfQueries(){
		return count($this->queries);
	}
	
	/**
	 * Returns the queris involved in the render, sets a colour for bad ones
	 */
	public function getQueries(){

		$out = array();
		for($i=0; $i < count($this->queries); $i++){

			$colour = $this->queries[$i]['success'] ? "green" : "red";
			$sql = $this->queries[$i]['sql'];
			$rows = $this->queries[$i]['rows'] . ' row' . ($this->queries[$i]['rows'] == 1 ? '' : 's') ;
			$time = $this->queries[$i]['time'].' ms';

			$out[] = array('colour' => $colour, 'number' => $i+1, 'sql' => addslashes($sql), 'time' => $time, 'rows' => $rows);
		}

		return $out;

	}
	
	public function render(){
		?>
		<!--DEBUG PANEL-->
		<link rel="stylesheet" href="/?_dev|debug_css" />
		<div id="_wave_debugpanel">
	        <div id="_wave_debugclosetrigger" class="item" style="margin-top:-1px;border-right:none;"><div id="_wave_debugclose"> x </div></div>
	        <div class="item"><div class="_wave_debugicon" id="_wave_debugclock"></div><div class="itemlabel"><?php echo $this->getExecutionTime(); ?>ms</div></div>
	        <div class="item"><div class="_wave_debugicon" id="_wave_debugmemory"></div><div class="itemlabel"><?php echo $this->getMemoryUsage(); ?>kb</div></div>
	        <div class="item"><div class="_wave_debugicon" id="_wave_debugdb"></div><div class="itemlabel"><?php echo $this->getNumberOfQueries(); ?></div></div>
	        <div class="item"><div class="_wave_debugicon" id="_wave_debugfiles"></div><div class="itemlabel"><?php echo $this->getNumberOfFiles(); ?></div></div>
	        <div style="margin-bottom:-8px; visibility:hidden;" id="_wave_debugitemdetails"></div>
		</div>
		
		<script type="text/javascript">
		//      <![CDATA[
		(function(){
	        var details = new Array();
	        var oldrow;
	        var contents;
	        
	        details['_wave_debugdb'] = "<?php foreach($this->getQueries() as $query): ?><div class=\"itemrow\" style=\"color:<?php echo $query['colour']; ?>;\">[:<?php echo $query['number']; ?>]&nbsp;&nbsp;<?php echo $query['sql']; ?><span class=\"right\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(<?php echo $query['time']; ?>,&nbsp;<?php echo $query['rows']; ?>)</span></div><?php endforeach; ?>";
	        
	        details['_wave_debugfiles'] = '<?php foreach($this->getUsedFiles() as $file): ?><div class="itemrow">[<?php echo $file['number']; ?>]&nbsp;&nbsp;\'<?php echo $file['filename']; ?>\'</div><?php endforeach; ?>';
	        
	        bind();
	        
	        function showDetails(row){
	                var itemdetails = document.getElementById("_wave_debugitemdetails");
	                if(itemdetails.style.height == "auto" && row == oldrow){
	                        itemdetails.style.height = "0px";
	                        itemdetails.style.marginBottom = "-8px";
	                        itemdetails.style.visibility = "hidden";
	                        itemdetails.innerHTML = "";             
	                } else {
	                        itemdetails.style.height = "auto";
	                        itemdetails.style.marginBottom = "0px";
	                        itemdetails.style.visibility = "visible";
	                        itemdetails.innerHTML = details[row.id]
	                        oldrow = row
	                }
	        }
	        function hide(){
	                var bar = document.getElementById("_wave_debugpanel");
	                contents = bar.innerHTML;
	                bar.innerHTML = "<div id=\"_wave_debugclosetrigger\" class=\"item\" style=\"margin-top:-1px;border-right:none;\"><div id=\"_wave_debugclose\"> + </div></div>";
	                document.getElementById('_wave_debugclosetrigger').onclick = show;
	        }
	        function show(){
	                var bar = document.getElementById("_wave_debugpanel");
	                bar.innerHTML = contents;
	                bind();
	        }
	        
	        function bind(){
	        	var e_db = document.getElementById('_wave_debugdb'),
		        	e_fi = document.getElementById('_wave_debugfiles');
		        e_db.onclick = function() { showDetails(e_db); }
		        e_fi.onclick = function() { showDetails(e_fi); };
		        
		        document.getElementById('_wave_debugclosetrigger').onclick = hide;
	        }
	        
		})();
		//      ]]>
		</script>
		<!--END DEBUG PANEL-->				
		<?php
	}
	
	public static function showCSS(){
		header('Content-Type: text/css'); ?>

		#_wave_debugmemory{background-position: 0px -48px;}
		#_wave_debugfiles{background-position: 0px -32px;}
		#_wave_debugclock{background-position: 0px -16px;}
		#_wave_debugdb{background-position: 0px 0px;}
		div._wave_debugicon{background-image: url(data:image/gif;base64,R0lGODlhEABgAOZ/AJm2xYeHh7KbWdjImbCwsNarEsy5luLi4v3+/cHBweXn6MrU4yJQdYSitbm4yJubm9jj7OPq9cXV2vP2+pOTk6qrq2xtgXh5lNTc69KxR4eIo87Oz3l4eHaWqtzc3LKUNmWCm+rx86ett7eRDpaaqGyUrFJTdOLYx6qURDdhhIl2UtnZ2aOks9nUya3F0rOts0ZyjmRngNbW1snJyaOTl4aKmZeasKmquaSnt5SQnXN1kOrt8tHR0d3k8by7usTFyO3y++nu+KKjpfn5+kt4l+Hm8VmIoPn9/mOKo+vr7JSrvPj7//X5/ufz9K+70dHc47XK073J3L/ExrjO1sbKzmxsbFZ8mbjD1nugtP38+/Pz887a3FWBmkNpikdtkp/EzqvAy7/I152gqb2mUc/O3bmseJCMnMPP4bmwmdXU2GRcg/n++oB+k/77+t7e3ra2u5CRpo2euWVpkG5wkHZrhu339xU/bF9efomvvr7S3tPT18rNzczMzKCOU////97e3iH5BAEAAH8ALAAAAAAQAGAAAAf/gH8bMjwzPz4+bz4/Gzw8G39/PCszMgdJOztJBzJ8PB6RMxsHBx4epKQKB4ORCQEVK6WEGzM+BA8+rRwBPH5+WnwPHLsUBK0BFDK+Q3wUVRwUDwmhBBRCP45vFAHIFQetFBVvFQ/lD0IVt9N/CbsyCFlaPBUB0MXHycszztC4kRsViq1QoOCHMGQEZgja8KACOXPl0AlROMjdkAkK+NBDFuCHID67evkZsu8ZhXs8DgT0oSBJkh8UiD3gEenUigQE0lUQIuTNqG+R/kgBAwBGihRWXDyhEjSSlA4wiBjpUIJICiQAmDoFkaJEFAhfvjxpcBQApA0dGCgJ4ecIHgBt/wEwgLGFx5YuXJr4aoIFri8lDDoUAeAFjB8EfprgcRECypMQVl0QIQLBTxY/dbCAKIHkDAKyJVIYqZwFwRElWKEMWaNEdAoulX0hgAChtJ8JoEt0mXIYAeLDbfxEsAogjOg6h2UjRiAXiQS0DBow8UUdgYujU3jwkWDFTocnEUI0gSCXAQAFZ6FYYSCaKgz2eBTQjLRnC+GjR5FAkd9UUIg8eGDRgAtTbABJUyEUoWAPGCxwBnr9/bHDBL4sAQQEUTx4YFATyjZBBAuAmMSGElLoCxNBLIBBFE4o0FSHFQLRwxlOxKFVJDC2JeMZUQAgxYsm+sFEBA0u4IRHHJo4JP8EGDR5JJB+LBFEBEUwCcEVSOJI4QRTRtADBD1gCeQRQAAxZRE99BBGliVaaCaVaa754hAITDDBm0VEEAWbmXSJZpMYiMmhDyIUamihQpBoigwrNOroCh64GOGklFZKqRt6zOAGpR7okcAbNzjgAAtpRLiCBTY4QIYDb4gKRwKbBnXAHWrYMA4LOODAAhwkrBDrAWOoEMMcJJCggQZwwKEBGweeUAAKHNAxRw0XXKCDDtbewYIbA4zQRwYfsDHHsHeUewcbbPDhRgIGFPABDTnIYYIJd+hgAxUv5HJABiOgIEAGNAyrgQ03kPFCH5G4IYABGRRQBrZwEIyDGQbIOkBAASOgoYEOZtSQww9uxBpJCyN88MK4Ymwgcn8ttPAHpJbGLPPMNNds880456zzzjz37PPPQAct9NBEF2300TgHAgA7); width: 16px; height: 16px; float: left; cursor: pointer;}
		
		div#_wave_debugpanel{font-family: "Lucida Sans" Arial; font-size: 12px; opacity:.5; alpha:50; position:absolute; z-index:99999; right:1px; top:1px; background-color:#DDDDDD; padding:3px; border:1px solid #888888; -moz-border-radius: 5px; -webkit-border-radius: 5px;line-height: normal; text-align:left;}
		div#_wave_debugpanel:hover{opacity:.9; alpha:90;}
		
		div#_wave_debugpanel .item{float:right; padding:0 5px 0 5px; border-right: solid #888888 1px;}
		div#_wave_debugpanel .item .itemlabel{margin:1px 0 0 3px; float:left;}
		
		div#_wave_debugitemdetails{float:left; clear:both; margin-top: 5px; font-size:11px; width:100%; overflow: hidden; background-color: #EEEEEE; border-top:#888888 solid 1px; padding-bottom:3px;}
		div#_wave_debugitemdetails .itemrow{font-family:monospace; float:left; clear:both; padding:3px 5px 0 5px;}
		div#_wave_debugitemdetails .right { display:block; }
		
		div#_wave_debugclose{background-color:#DDDDDD; padding:2px;}
		div#_wave_debugclose:hover{cursor: pointer; background-color: #CCCCCC;}
		<?php die();
	}
	
}